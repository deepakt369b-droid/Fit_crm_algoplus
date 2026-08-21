# FitCRM

FitCRM is a Laravel-based gym & fitness club management platform, developed and powered by
[Algo Plus](https://algoplusit.com).

## Overview

FitCRM covers day-to-day gym operations — members, plans, subscriptions, invoicing, expenses,
enquiries and follow-ups — through a Filament admin panel, plus a versioned JSON API for
integrations.

## Requirements

-   PHP >= 8.2
-   Laravel Framework ^12.0
-   Filament Admin Panel 5.x
-   Livewire ^3.0
-   nnjeim/world ^1.1
-   barryvdh/laravel-dompdf ^3.1
-   Laravel Herd _(optional for local development)_

## Installation

To set up FitCRM, follow these steps:

### 1. Clone the Repository

```bash
git clone <your-repository-url> fitcrm
```

### 2. Go to folder

```bash
cd fitcrm
```

### 3. Install dependencies

```bash
composer install
```

### 4. Prepare the environment

Run the following script to prepare your environment:

```bash
composer run prepare-env
```

This will:

-   Copy `.env.example` to `.env` (if missing)
-   Clear config cache
-   Generate application key
-   Create a symbolic link to the storage folder

### 5. Configure the `.env` file

-   Set your database credentials.
-   Update other relevant configuration values.
-   Set your application URL:
    ```env
    APP_URL=https://fitcrm.test
    ```

### 6. Database Setup

You can set up the database in one of two ways, depending on your requirements:

**Option 1: Blank Setup (Recommended for Production)**

Run the following command:

```bash
composer run setup
```

> [!NOTE]
> This command will prompt you to create an admin user via the terminal.

This will:

-   Set up the environment (.env, app key, storage link)
-   Run a fresh migration to create database tables
-   Seed the world data (countries, states, cities)
-   Create a default Filament admin user

**Option 2: Demo Setup**

If you want to explore the system with all demo data preloaded, use:

```bash
composer run setup-demo
```

This command will:

-   Reset the database
-   Seed all available demo data
-   Prepare the environment automatically

> [!CAUTION]
> This process will erase all existing data. Use it only in a local or demo environment.

Login credentials:

```bash
Email: test@example.com
Password: test
```

## Troubleshooting

**Memory Errors**

Ensure PHP has enough memory allocated. Edit your php.ini:

```ini
memory_limit = 512M
```

**Seeder Performance**

Seeders (like WorldSeeder) can add significant data and slow down performance. For production, avoid full seeding and run only necessary seeders:

```bash
php artisan db:seed --class=WorldSeeder
```

## Development

### 1. Start the development server:

```bash
php artisan serve
```

Or with Laravel Herd:

```bash
herd
```

### 2. Start the queue worker

To process background jobs:

```bash
php artisan queue:work
```

### 3. Start the Laravel scheduler

```bash
php artisan schedule:work
```

> [!NOTE]
> The scheduler must be running continuously to trigger time-based tasks (e.g., status updates).
>
> If those tasks dispatch queued jobs (like import/export or notifications), then the queue worker must also be running to process them.

## API (JSON, v1)

FitCRM ships with a versioned JSON API under `routes/api.php` for integrations.

### Authentication (Sanctum Bearer Tokens)

-   Login: `POST /api/v1/auth/login`
-   Current user: `GET /api/v1/me`
-   Logout: `POST /api/v1/auth/logout`

Example:

```bash
curl -sX POST "$APP_URL/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'
```

Use the returned token:

```bash
curl -s "$APP_URL/api/v1/me" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

Notes:

-   The API is bearer-token only. Being logged into Filament in the browser does not authenticate API requests.
-   `/api/v1/me` always includes roles and permissions. Other user endpoints include permissions only when requested:
    -   `GET /api/v1/users?include=permissions` or `GET /api/v1/users?include_permissions=1`

### Index Query Parameters (Rich Filtering)

All index endpoints support allowlisted query params:

-   Search: `?q=...`
-   Pagination: `?page=...&per_page=...`
-   Sort (multi-sort): `?sort=-created_at,name`
-   Soft deletes (where supported): `?trashed=with|only`
-   Includes (allowlisted): `?include=service,subscription.member`
-   Filters (allowlisted): `?filter[field]=value`
    -   Range syntax for date/datetime: `?filter[date]=2026-03-01..2026-03-31`

Allowlists (searchable/sortable/includes/filters) are defined per resource in:

-   `app/Services/Api/Schemas/*Schema.php` via `::queryRules()`

## Multi-Branch

FitCRM supports multiple branches (gyms) under one install:

-   A **superadmin** panel at `/superadmin` manages branches and cross-branch users. Access is restricted to the `super_admin` role.
-   The main admin panel is tenant-aware: branch admins see and manage only their own branch (members, plans, invoices, settings, etc.); a superadmin can switch between branches from the same panel.
-   Every branch has its own settings, invoice/member numbering, and gate devices.
-   To create the first superadmin, assign the `super_admin` role to a user after `make:filament-user` — the command itself does not assign it:
    ```bash
    php artisan tinker
    >>> $user = \App\Models\User::first();
    >>> $user->assignRole('super_admin');
    ```

## Biometric Gate Integration

Face/fingerprint access-gate hardware pairs to a specific branch and talks to a dedicated, narrowly-scoped API:

-   Pair a device from **Access Control → Devices** in the admin panel — it shows a QR code and a plain-text fallback code, valid for 15 minutes.
-   `POST /api/v1/devices/pair` exchanges that code for a long-lived Sanctum token restricted to the `attendance:write` ability only — a stolen device token cannot reach member or billing data.
-   `POST /api/v1/attendance/check-in` (single event) and `/api/v1/attendance/sync` (batched replay of a device's offline buffer) record visits; both are idempotent, so a device retrying the same event never double-counts it.
-   `POST /api/v1/devices/enrol` maps a member to the device's own biometric identifier — the biometric template itself never leaves the device — and requires `consent: true` to be recorded.

## Photo Requirement

A photo is mandatory when creating a member or staff user, captured directly from the camera in the admin panel (with a file-upload fallback if no camera is available). Existing records created before this requirement was added keep whatever photo they had — use the **Photo missing** filter on the Members/Users tables to find and fill in the gaps.

## Deployment (Coolify)

Two build paths are provided:

-   **Dockerfile + `docker-compose.yaml`** (recommended) — multi-stage build (Node for assets, PHP-FPM + nginx + supervisor at runtime), with separate `queue` and `scheduler` services sharing the same image via a `CONTAINER_ROLE` environment variable, plus `mysql` and `redis`. Only the `web` container runs migrations/cache warming on boot (`scripts/coolify-deploy.sh`), so the other replicas don't race it.
-   **`nixpacks.toml`** — a lighter-weight fallback for Coolify's Nixpacks builder. It runs as a single process (`php artisan serve`, not production-grade), so if you use this path, run the queue worker and scheduler as separate Coolify resources yourself with an overridden start command.

Before the first deploy:

-   `storage/` must be a persistent volume — branch settings, gym logos, member/user photos, and generated invoice PDFs all live there, and a redeploy replaces the rest of the container filesystem.
-   Set `TRUSTED_PROXIES=*` (already the `.env.example` default) — required for correct HTTPS URL generation and secure-cookie behavior behind Coolify's Traefik reverse proxy.
-   `GET /healthz` is a shallow health check (no DB round-trip) for Coolify's zero-downtime monitor; `GET /up` is Laravel's own deeper check.
-   If you plan to use the WhatsApp marketing module, set `WHATSAPP_APP_SECRET` and `WHATSAPP_VERIFY_TOKEN` before the first deploy — Meta requires a reachable, verified webhook URL before it will deliver anything.

## About Algo Plus

FitCRM is developed and powered by [Algo Plus](https://algoplusit.com).
For support, contact support@algoplusit.com.

## License

FitCRM is licensed under the [MIT license](LICENSE).
