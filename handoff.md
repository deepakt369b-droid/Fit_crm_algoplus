# FitCRM Build — Handoff

> **Update:** Node 4 M1 (below) is now complete and committed — tests,
> `CREDITS.md`, and the verification sweep were finished after this doc
> was first written. See `git log` for the actual commit. The rest of
> this document is kept as-is for the historical record of decisions
> made during the build; treat "uncommitted"/"pick up here" language
> below as describing that point in time, not the current state.
>
> **Second update:** Node 4 M2 (broadcasts) is also now complete and
> committed — see `git log` for that commit's message, which documents
> what M2 added (wa_broadcasts/wa_broadcast_recipients, chunked/paced
> sending via SendWhatsappBroadcastBatch, the messaging-tier throttle,
> per-recipient status propagation from WhatsappMessage, and the
> Filament WhatsappBroadcastResource) in the same level of detail as
> the M1 notes below. Next up per the plan: M3 (automations) or M4 (AI
> assistant + knowledge base), each with its own Human Gate first.

Working directory: `C:\Users\DK\Downloads\FitCRM\Fit_crm_algoplus` (git repo, not pushed anywhere).
Plan file: `C:\Users\DK\.claude\plans\markdown-system-directive-rebrand-whimsical-hummingbird.md` — read this first for full context, decisions, and rationale.

No PHP/Composer/Docker runtime is available in this environment. Nothing here has been executed — everything is reasoned through and cross-checked by static inspection (grep, bracket-balance sweeps, and targeted WebFetch verification of Filament/Laravel/Meta API internals). Whoever picks this up should run `composer install && php artisan test` (or push through CI) as the first real validation.

## Committed so far (6 commits, clean working tree up to `15c77e5`)

| Commit | Node | Summary |
|---|---|---|
| `7c8f9dd` | −1 | Pristine Gymie v3 baseline |
| `4ab4808` | 2A | Rebrand → FitCRM / Algo Plus |
| `30692fc` | 3B | Multi-branch tenancy, superadmin panel, per-branch settings |
| `3ed5068` | 3A | Biometric gate: Device tokenable, pairing, check-in/sync API |
| `ea00472` | 3C | Mandatory camera-first photo, consent-gated fingerprint enrolment |
| `15c77e5` | 2B | Dockerfile + Nixpacks, `/healthz`, production `.env.example` |

Real bugs found and fixed along the way (see individual commit messages for detail): cross-branch unique-constraint collisions (invoice numbers, plan codes, member codes), a missing `ext-mbstring` in the Docker image, `Device` model missing `Authenticatable`/`Authorizable` contracts, an unwired `consent_given_at` column, and — most recently — **two closure-based routes that would have hard-failed `php artisan route:cache`** in the deploy script (the pre-existing `/user` route and my own `/healthz` route). Both fixed by converting to controller classes.

## In progress, UNCOMMITTED: Node 4 M1 (WhatsApp foundation)

This is a large, uncommitted change set (~31 files) implementing the merge-in from `ArnasDon/wacrm` per the plan's Node 4 M1 scope: phone numbers, contacts, template sync, send/receive, shared inbox. **Do not treat this as done** — it needs the remaining steps below before it's commit-ready.

### What's built (uncommitted)
- **Migrations**: `wa_phone_numbers`, `wa_contacts` (+`wa_tags`/`wa_contact_tag`), `wa_templates`, `wa_conversations`+`wa_messages` — all `2026_08_21_12000{0,1,2,3}_*`. All gym-scoped tables use **nullable** `gym_id` (a shared phone number's inbound contacts can't be auto-assigned a branch — see plan/models for the reasoning), matching the established pattern from Node 3B/3C.
- **Models**: `WhatsappPhoneNumber`, `WhatsappContact`, `WhatsappTag`, `WhatsappTemplate`, `WhatsappConversation`, `WhatsappMessage` — all under `App\Models\`, using `BelongsToGym`.
- **Services** (`app/Services/WhatsApp/`):
  - `MetaCloudApiClient` — send text/template, fetch templates. Meta Graph API shapes verified via live doc fetch (not guessed) — see plan for the exact payload examples pulled from developers.facebook.com.
  - `WebhookSignatureVerifier` — `X-Hub-Signature-256` HMAC check.
  - `InboundWebhookProcessor` — turns a verified webhook payload into contact/conversation/message rows; **honors "STOP" as an automatic opt-out**.
  - `OutboundMessageSender` — enforces the 24-hour customer service window (free text only within it, template required outside), and now correctly marks a message `failed` (with Meta's error) if the API call throws, instead of leaving it stuck at `queued`.
  - `TemplateSyncer` — pulls a phone number's WABA-approved templates into `wa_templates`.
- **Webhook endpoint**: `GET|POST /api/webhooks/whatsapp` via `WhatsappWebhookController` (two explicit routes, not `Route::match` with a closure — see below). POST processing is queued (`ProcessWhatsappWebhook` job), not synchronous, so Meta gets a fast 200.
- **Feature flags**: `NormalizesSettings` gained a `marketing` section (`inbox` on by default, `broadcasts`/`automations`/`pipelines`/`ai_assistant`/`knowledge_base` off — those are M2–M4, not built). `Helpers::marketingFeatureEnabled()` checks it; every new Resource's `canAccess()` gates on it. A **superadmin-only** "Marketing" tab was added to the existing `Settings` page (`app/Filament/Pages/Settings.php`) with toggles for all six — gated on `auth()->user()->hasRole('super_admin')`.
- **Filament UI**: `WhatsappPhoneNumberResource` (CRUD + "Sync templates" action), `WhatsappContactResource` (CRUD, opt-in status, optional Member link), `WhatsappTemplateResource` (read-only), `WhatsappConversationResource` (the shared inbox — list + a custom `ViewWhatsappConversation` page with a message-thread partial and a "Reply" action that branches between free-text and template-select based on the 24h window). All registered under a new "Marketing" nav group in `AdminPanelProvider`.
- **Two real bugs already caught and fixed in this uncommitted work** (both are the kind that would only surface at runtime, so flagging prominently):
  1. `wa_templates.status` was initially a DB `ENUM` — changed to a plain `string`, because Meta's template status vocabulary (approved/pending/rejected/paused/disabled/in_appeal/pending_deletion/...) is external and larger than what I could safely enumerate; a value outside the enum would have hard-failed the *entire* sync batch on one bad row.
  2. Two closure-based HTTP routes (`routes/api.php`'s pre-existing `/user`, and my own `routes/web.php` `/healthz` from Node 2B) — both silently break `php artisan route:cache`, which `scripts/coolify-deploy.sh` runs on every deploy. Converted both to controller classes (`HealthCheckController`, and `/user` now points at the existing `AuthController::me`).
- Lang keys added in full across all four locales (en/ar/fa/fr) — bracket-balance-verified after every edit.

### What's NOT done yet for M1 (pick up here)
1. **Tests** — none written yet for Node 4. Plan commits to: webhook signature verification (valid/invalid), inbound message → contact/conversation/message creation, STOP → opt-out, 24h-window enforcement blocking free-text send outside the window, template-required-outside-window path, `wa_templates.status` accepting an unrecognized value without erroring.
2. **`CREDITS.md`** — plan commits to crediting `ArnasDon/wacrm` (MIT) even though this is a reimplementation, not a copy. Not created yet.
3. **Final verification sweep** — re-run the brace/paren balance check and the `gymie|lubus` grep sweep across the full diff before committing (both were clean as of the last check, before tests/CREDITS were added — re-check after).
4. **Commit** — once 1–3 are done, commit as "Node 4 M1: WhatsApp foundation" following the same style as the prior five commits (see `git log` for tone/structure — lead with what changed, then a paragraph on real bugs found/fixed, matching the honesty bar set so far).
5. **Human Gate** — per the plan, Node 4 gets its own gate *per milestone* (M1–M4), not one gate at the end. Present the M1 change table to the user before moving to M2.

### Known gaps / deliberately deferred (say so if asked, don't silently fix without checking)
- **No auto-linking of inbound WhatsApp contacts to existing Members/Enquiries by phone number** — considered and deliberately skipped for M1 (phone-format normalization across E.164/local formats was judged too fragile to do blind, without a runtime to test against). Contacts are created standalone; staff can link them to a Member manually via the Filament form. Flagged in code comments.
- **Shared phone numbers** (`is_shared = true`, `gym_id = null`) have no routing logic to guess which branch an inbound message belongs to — by design, they land unscoped (`gym_id = null`) until a staff member manually assigns a branch. This is the "build for both" number-model decision from the plan; the dedicated-number path is fully scoped, the shared-number path is intentionally minimal.
- **M2 (broadcasts), M3 (automations), M4 (AI assistant + knowledge base)** are not started — their feature flags exist (all default `false`) but no schema/UI/logic behind them.

## Next session — start here
1. Re-read the plan file for full Node 4 context if anything here is ambiguous.
2. Run `git status` / `git diff` to see the exact uncommitted state (should match the file list in this doc — if it doesn't, something changed outside this handoff, look before proceeding).
3. Finish items 1–5 above (tests, CREDITS.md, verification sweep, commit, Human Gate for M1).
4. Then either continue to M2 (broadcasts) if the user asks, or stop at the M1 gate for review — per the plan, don't barrel into M2 without a checkpoint.

## Environment reminders
- No `php`, `composer`, or `docker` binaries available here — verification is static only (grep-based brace/bracket balance, targeted WebFetch against upstream docs/source for anything version- or API-specific). Say so plainly rather than implying something was tested when it wasn't.
- `sh` *is* available (Git Bash) — used to lint the two shell scripts (`docker/entrypoint.sh`, `scripts/coolify-deploy.sh`) via `sh -n`, which is real signal, unlike the PHP brace-counting which is just a heuristic.
