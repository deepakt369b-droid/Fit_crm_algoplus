# FitCRM Coolify Testing Environment

Use these values for a temporary testing deployment. Do not commit real passwords, API keys, or tokens to Git.

## Required Application Variables

Paste these into Coolify under **Environment Variables**. Replace every value marked `<...>`.

```dotenv
APP_NAME=FitCRM
APP_ENV=testing
APP_KEY=<generate-a-Laravel-key>
APP_DEBUG=true
APP_URL=http://<your-testing-domain>
TRUSTED_PROXIES=*

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=4

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=<mysql-hostname>
DB_PORT=3306
DB_DATABASE=fitcrm
DB_USERNAME=fitcrm
DB_PASSWORD=<mysql-password>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
DB_QUEUE_RETRY_AFTER=120
REDIS_QUEUE_RETRY_AFTER=120

CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=<redis-hostname>
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=resend
RESEND_KEY=re_placeholder_REPLACE_ME
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=testing@example.com
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

WHATSAPP_APP_SECRET=placeholder-app-secret
WHATSAPP_VERIFY_TOKEN=placeholder-verify-token
```

## Hostname Values

Use the option that matches how the test services are running:

| Setup | `DB_HOST` | `REDIS_HOST` |
|---|---|---|
| Same Docker Compose network | `mysql` | `redis` |
| Separate Coolify MySQL/Redis resources | The resource hostname | The resource hostname |
| External test services | Their private/network hostnames | Their private/network hostnames |

Do not use `127.0.0.1` or `localhost` for MySQL/Redis from the application container. Those addresses point back to the FitCRM container itself.

## Generate `APP_KEY`

Run this from the repository root on a machine with PHP and Composer:

```powershell
php artisan key:generate --show
```

Copy the complete output, including the `base64:` prefix, into `APP_KEY` in Coolify. Do not use the same key for production.

If dependencies are not installed locally, run:

```powershell
composer install
php artisan key:generate --show
```

## Option B — Single-container SQLite (no MySQL resource)

If the Coolify application has no MySQL service attached, SQLite works out of the box with just:

```dotenv
DB_CONNECTION=sqlite
```

(`DB_HOST`/`DB_PORT`/etc. are ignored on this connection.) The image pre-creates `/var/www/html/database/database.sqlite` and the entrypoint enforces `www-data` ownership of `database/`, `storage/`, and `bootstrap/cache/` on every boot, so writes no longer fail with "attempt to write a readonly database".

For data that must survive redeploys, attach a persistent volume in Coolify for `/var/www/html/database` (the sqlite file) — and `/var/www/html/storage` if uploaded files matter. The entrypoint re-applies ownership to mounted volumes automatically.

Note: sessions use `SESSION_DRIVER=database` and queue/cache default to Redis; with SQLite-only deployments keep or set `CACHE_STORE=file` unless a Redis resource is attached.

## MySQL Test Database

Create a database and user with values matching the variables above:

```sql
CREATE DATABASE fitcrm;
CREATE USER 'fitcrm'@'%' IDENTIFIED BY '<mysql-password>';
GRANT ALL PRIVILEGES ON fitcrm.* TO 'fitcrm'@'%';
FLUSH PRIVILEGES;
```

The MySQL service must accept connections from the FitCRM container on port `3306` before the application starts. The deployment runs migrations during startup.

## Redis Test Service

Redis must accept connections from the FitCRM container on port `6379`. For an unauthenticated test Redis instance, keep:

```dotenv
REDIS_PASSWORD=null
```

For password-protected Redis, set the actual password instead.

## Coolify Application Settings

Confirm these settings before redeploying:

```text
Build Pack: Dockerfile
Dockerfile Location: /Dockerfile
Exposed Port: 80
Health Check Enabled: true
Health Check Path: /healthz
Health Check Port: 80
Health Check Start Period: 60
```

## Placeholder API Template

Every integration below is wired in code and fails gracefully (clean error notifications, no crashes) when given placeholder values. Replace placeholders with real values as you obtain them — no code changes needed.

| Integration | Configured in | Placeholder value | Replace with |
|---|---|---|---|
| WhatsApp webhook signature | Coolify env `WHATSAPP_APP_SECRET` | `placeholder-app-secret` | Meta App secret (App dashboard → WhatsApp → API Setup) |
| WhatsApp webhook verification | Coolify env `WHATSAPP_VERIFY_TOKEN` | `placeholder-verify-token` | Any string you also enter in Meta's webhook config |
| WhatsApp sending number | In-app: **Phone Numbers → New** | see below | Real Cloud API values |
| → `display_phone_number` / `verified_name` | Phone Numbers form | `+15550000000` / `Placeholder Number` | Your Meta number + its verified name |
| → `waba_id` / `phone_number_id` | Phone Numbers form | `PLACEHOLDER_WABA_ID` / `PLACEHOLDER_PHONE_ID` | IDs from Meta App dashboard → WhatsApp → API Setup |
| → `access_token` | Phone Numbers form | `PLACEHOLDER_ACCESS_TOKEN` | Permanent system-user token with `whatsapp_business_messaging` |
| WhatsApp webhook callback URL | Meta App dashboard → WhatsApp → Webhooks | — | `https://your-domain/api/v1/webhooks/whatsapp` |
| Email (Resend) | Coolify env `MAIL_MAILER=resend`, `RESEND_KEY` | `re_placeholder_REPLACE_ME` | Resend API key + verified sending domain |
| AI assistant (per branch) | In-app: **Settings → Marketing → AI Assistant** | provider + any key string | Real key from Anthropic/OpenAI/Kimi/GLM |

Notes:
- With placeholder WhatsApp credentials, **Sync templates** and broadcast sends return Meta's authentication error as an in-app notification — expected until real values are entered.
- Resend requires a verified sending domain; until then password-reset/test mails fail with Resend's domain-verification error.

## Camera & External Devices (HTTPS required)

The member photo camera (`CameraCapture` form component) uses `getUserMedia`, which browsers only expose in **secure contexts**. On plain HTTP it is silently unavailable — the field now shows a dedicated "camera requires HTTPS" message instead of a generic error.

- **Production:** add your domain in Coolify (Domains field) so Traefik provisions a Let's Encrypt certificate, set `APP_URL=https://your-domain`, and open the app over HTTPS. The app auto-forces HTTPS when `APP_URL` is https.
- **Local testing:** `http://127.0.0.1.nip.io` is NOT a secure context. Either use `http://localhost` directly, or add the site to `chrome://flags/#unsafely-treat-insecure-origin-as-secure` (testing only).
- **External device integrations** (attendance devices via `/api/v1/devices/pair` → heartbeat/enrol/check-in) work over plain HTTP but should always run behind HTTPS in production — device pairing tokens are bearer credentials.

## WhatsApp Marketing Modules

Broadcasts, Automations, and Knowledge Base access is feature-flagged **per gym** via Settings (`marketing.broadcasts`, `marketing.automations`, `marketing.knowledge_base`). When disabled, the pages show Forbidden by design — enable them from the Settings page for the branch.

## Testing Checklist

- [ ] Replace `<generate-a-Laravel-key>` with a generated `APP_KEY`.
- [ ] Replace `<mysql-hostname>`, `<mysql-password>`, and `<redis-hostname>`.
- [ ] Confirm MySQL and Redis are reachable from the application network.
- [ ] Set `APP_URL` to the testing URL.
- [ ] Set `RESEND_KEY` (real key) once the sending domain is verified in Resend; password-reset mail depends on it.
- [ ] Leave WhatsApp values blank unless webhook testing is required.
- [ ] Add persistent storage for `/var/www/html/storage` if uploaded files must survive redeploys.
- [ ] Redeploy after saving the variables.
- [ ] Check `/healthz` after the container starts.
- [ ] Check the Coolify deployment logs if migrations fail.

## Important

The current Coolify application previously had no MySQL, Redis, or persistent storage resources attached. These variables will only work after the referenced services exist and are reachable from the FitCRM container.

Revoke any API token that has been pasted into chat or committed to a shell history, then create a replacement token for future administration.
