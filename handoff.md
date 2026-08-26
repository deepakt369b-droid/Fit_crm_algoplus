# FitCRM — Handoff (updated 2026-08-25, end of session)

Working directory: `C:\Users\DK\Downloads\FitCRM\Fit_crm_algoplus` (repo pushed to
`https://github.com/deepakt369b-droid/Fit_crm_algoplus.git`, branch `master`).

## Current state (live, verified)

- Coolify application UUID: `eu4zxqgxdtqpnaa4kwkwqogo` (project `lxkdwbzqznm6yi4w0pwuzf7h`).
  Deployed commit: `f694b73` (or later), `running:healthy`, build pack Dockerfile.
- **HTTPS is live**: `https://fitcrm.127.0.0.1.nip.io` (Traefik default self-signed
  cert — browser shows a one-time "Privacy error"; click Advanced → Proceed).
  HTTP redirects to HTTPS. `APP_URL` is `https://…` in Coolify env.
- Camera capture on the member form **works over HTTPS** — user confirmed a
  successful capture. A camera device picker (`enumerateDevices` dropdown) ships
  for multi-camera machines; front camera is the default.
- **20/20 admin pages verified rendering** for the super_admin role (browser
  sweep via the OpenCode-Browser bridge).
- Testing deployment runs with `APP_ENV=testing` → `ShieldSeeder`, `UserSeeder`,
  `SuperAdminSeeder`, `MarketingFeaturesSeeder` run on every deploy. **Every
  deploy wipes the database** (no persistent volume yet — see Loop 6).

### Accounts (testing only — rotate/delete before production)

| Account | Password | Purpose |
|---|---|---|
| `test@example.com` | `test` | Legacy shared test login (super_admin) |
| `superadmin@fitcrm.local` | `FitCRM-Super-2026!` | Dedicated operator login (super_admin) — verify `/superadmin` panel access manually |

### Access notes

- Coolify MCP endpoint: `http://localhost:8000/mcp` (Sanctum bearer token from
  the CRM's Security → API Tokens — **the token used on 2026-08-25 was pasted
  into chat; rotate it**). MCP sessions expire; re-run the `initialize`
  handshake and use the new `MCP-Session-Id` when calls start returning null.
- Browser automation: OpenCode-Browser extension (vymalo) → bridge daemon
  `C:\Users\DK\AppData\Local\Temp\opencode\vymalo-bridge.mjs` (WS :3002 for the
  extension, HTTP control on :4599 — `POST /tool/<name>` with JSON). Start with
  `Start-Process node <script>`; logs land in `vymalo-out.log`.
- The registered `browsermcp` entry in `~/.config/opencode/opencode.jsonc` is
  the WRONG package (Mytai20100) — the working one is the `@vymalo/opencode-browser`
  plugin family. Clean that entry up when browser_* tools are wanted natively.

## Fixed on 2026-08-25 (all root causes, deployed)

| Commit | Fix |
|---|---|
| `05b6e9a` | 11 Filament actions generated `{tenant}`-scoped routes without the tenant parameter (the original "Missing parameter: tenant" crash) |
| `f0e4eae` | Plans/Services register no `create` page — empty-state links target the index (modal-based creation) |
| `9090030` | Shield roles page crashed on tenant scoping (roles are global → `RoleResource::scopeToTenant(false)`); missing `WhatsappBroadcast/Automation/KnowledgeBaseArticle` policies created; camera HTTPS messaging added |
| `b7599ff` | **Deep one:** `SetAppLocale` (first middleware) read settings before auth/tenant resolution, poisoning the singleton settings cache with example defaults for the whole request → every per-branch feature flag unreadable → "Forbidden" pages. Cache is now keyed per branch context. |
| `427f28e` | `DatabaseSettingsRepository` ignored the database entirely under `APP_ENV=testing` (reads AND writes) — now prefers the persisted `gym_settings` row |
| `434bf7a`/`ecaf038` | `MarketingFeaturesSeeder` — fresh branches get marketing flags; testing deploys re-assert them ON |
| `f694b73` | Privilege-escalation guard: branch operators can no longer grant the `super_admin` role from the user form |
| `aeabe36` | Camera device picker, `SuperAdminSeeder`, placeholder API templates (`.env.example` + `COOLIFY_TESTING_ENV.md`) |

## Remaining work — loop plan

### Loop 1 — HTTPS + camera: **DONE** (capture confirmed by user)
Only leftover: the one-time certificate warning click per browser (self-signed
Traefik cert has no SAN — Chrome will always warn). Permanent fix options when
a real domain exists, or use `fitcrm.217.165.236.207.nip.io` if ports 80/443
are forwarded to the server (LE-issuable → no warning).

### Loop 2 — Superadmin account: **DONE** (needs one manual check)
Log in as `superadmin@fitcrm.local` and confirm `/superadmin` loads Gyms +
Users management. Then decide whether to keep or delete `test@example.com`.

### Loop 3 — Placeholder APIs: **DONE** (user fills real values)
Template table lives in `COOLIFY_TESTING_ENV.md`. Webhook callback URL for
Meta: `https://<domain>/api/v1/webhooks/whatsapp`. Resend needs a verified
sending domain + real `RESEND_KEY`. WhatsApp credentials go in **Phone
Numbers → New**; AI keys in **Settings → Marketing**. Placeholder credentials
fail gracefully (in-app notifications, no crashes).

### Loop 4 — Multi-branch operations: **~80% done, verification pending**
- Machinery already existed: `Gym` tenancy, branch switcher in the sidebar,
  `/superadmin` panel manages Gyms + Users, `UserForm` has a gym selector
  (superadmin panel only; branch panels auto-fill `gym_id`).
- Added: super_admin role-grant guard (above).
- **PENDING (blocked on one browser click):** the Chrome certificate
  interstitial re-appeared and the CDP keypress bypass (`thisisunsafe`) does
  not register. User must click **Advanced → Proceed** once on
  `https://fitcrm.127.0.0.1.nip.io`, then run this verification:
  1. `/superadmin/gyms` → create "Branch B".
  2. `/superadmin/users` → create/edit a user with `gym_id` = Branch B.
  3. Log in as that user → sidebar switcher shows ONLY Branch B; data is
     branch-scoped (spot-check members/subscriptions).
  4. Confirm the role dropdown no longer offers `super_admin` to
     non-super-admin editors.

### Loop 5 — Gated entry (biometrics + subscription enforcement): **~50% done**

5.1 **DONE (2026-08-26, needs deploy + live verification)** — explicit
allow/deny contract implemented:
- New `App\Services\GateAccessService` (+ `GateDecision` DTO). Every real-time
  check-in response now carries `gate: {allowed, reason, message}`. Denials
  return HTTP **200** (expected outcome for gate hardware; avoids retry loops)
  and record nothing. Reason codes: `granted | unknown_member |
  member_inactive | no_subscription | subscription_expired |
  subscription_not_started | subscription_cancelled | device_revoked`.
- Subscription rule: a non-cancelled/non-renewed subscription covering today
  grants entry; renewals handled (expired original + active renewal = allow).
- Security hardening: `pair()` now deletes all previous tokens before issuing
  a new one (one live token per device — "revoke + re-pair" fully rotates);
  both controllers reject any device whose `status !== 'paired'` even if a
  token survives (defense-in-depth behind the panel's revoke action).
- Tests: `tests/Feature/Api/GateAccessEnforcementTest.php` (11 cases) +
  existing idempotency test pinned to an active member w/ subscription.
- Loop 5.4 audit pass done for pairing/enrol/check-in: pairing codes hashed
  (sha256) + 15-min expiry + 10/min IP throttle; enrol requires consent,
  branch-scoped, enum+length validated; attendance index scoped via GymScope.
  Residual accepted risks: stolen device token can fabricate attendance
  (inherent; revoke rotates tokens), client-supplied `recognized_at`
  (offline-replay design, dedupe-hash protected), `sync()` deliberately does
  not re-evaluate current policy against historical buffered events.

1. ~~Explicit allowed/deny contract~~ → done above.
2. Mobile kiosk capture page (phone front camera at the gate — HTTPS now
   available; `CameraCapture` pattern is the reference). **NEXT UP.**
3. Fingerprint: browsers cannot read sensors — requires native hardware
   path (Android kiosk app or ZKTeco-class SDK). **User must choose the
   hardware family before this half is planned.** Existing schema already
   supports it: `member_device_identifiers.biometric_type` +
   `finger_position`, templates stay on-device by design.

### Loop 6 — Production closeout: **NOT STARTED**
1. Persistent volumes in Coolify for `/var/www/html/database` (SQLite) and
   `/var/www/html/storage` (uploads/sessions) — currently every deploy wipes
   data. The entrypoint already fixes volume ownership on boot.
2. Backups for the SQLite file.
3. Queue worker + scheduler containers (`docker-compose.yaml` has them; the
   single-container Dockerfile deploy currently runs neither — WhatsApp
   automations/broadcast pacing depend on the queue).
4. Real domain + real certificate (kills the interstitial), real Resend key,
   real Meta credentials.
5. Delete/rotate seeded test accounts and the leaked API token.
6. Final `security-audit` + `database-optimizer` pass.

## Gotchas (read before debugging anything)

- **`APP_ENV=testing` is load-bearing**: it makes the deploy script run
  seeders and (since `427f28e`) the settings repository prefers the DB row.
  Before `427f28e`, testing env froze all settings to the bundled example
  blob — if feature flags mysteriously read false, check this first.
- **Settings cache is keyed by branch** (`b7599ff`): `SetAppLocale` (first
  middleware) reads settings pre-auth; a contextless read caches under
  `none` and must never leak into tenant reads.
- **Every deploy wipes the DB** (no volumes): sessions die (logins drop),
  data resets, seeders rebuild. Don't chase "disappeared data" ghosts.
- **Traefik default cert has no SAN** → Chrome interstitial on every new
  origin until the user clicks Advanced → Proceed. The CDP `thisisunsafe`
  bypass does NOT work through the bridge.
- **Browser-automation login is flaky** (Livewire state sync race): fill →
  wait 3–6 s → click, and retry. Human login always works. `browser_type`
  by ref/selector fails with "not a text-editable element" on Filament
  inputs — use `browser_fill` + `browser_click`, and re-snapshot after
  every interaction (refs go stale instantly).
- **Filament tab clicks** work via `browser_query` refs (`q1…`), not
  always via snapshot refs.
- **PowerShell → curl JSON**: build bodies as single-quoted strings with
  `\"` escapes into a variable first; inline `"{`\"…`\"}"` interpolation
  produces "bad json".
- **`rg -rn` pitfall**: `-r` is --replace — it silently rewrites matched
  output text (made method names look like `n()`). Use `-n` alone.
- **MCP session expires**: re-initialize (`initialize` + `notifications/initialized`)
  when Coolify tool calls return null.
- `composer update` in the Dockerfile needs network on cold builds — a
  packagist connection blip fails the deploy (seen once); just retry.
- No local PHP/Docker CLI on this machine — runtime verification happens
  through deploys + the browser bridge, never locally.

## Historical (Node 4 build, completed)

Node 4 M1–M4 (WhatsApp foundation, broadcasts, automations, AI assistant +
knowledge base) are complete — see `git log` for the detailed commit
messages. Design decisions that still matter:
- Automation step JSON contract: authoritatively implemented in
  `AutomationStepExecutor::execute()`; `true_step`/`false_step` are raw
  0-based step indices (known UX rough edge, disclosed).
- Shared WhatsApp numbers (`is_shared = true`) land inbound messages
  unscoped (`gym_id = null`) by design; dedicated numbers are fully
  branch-scoped.
- Inbound WhatsApp contacts are NOT auto-linked to Members/Enquiries
  (deliberate M1 deferral — phone normalization judged too fragile blind);
  staff link manually.
- `wa_automation_runs.context` column is an unused forward-compatibility
  placeholder.
- Biometric templates stay on the device; only the device↔member mapping
  is stored (`member_device_identifiers`) — privacy decision, do not
  "fix" by uploading templates server-side without revisiting the plan.
- The old in-file blocker notes (memory exhaustion, M1/M3 pick-up-here
  lists) are all resolved — see git history of this file if needed.
