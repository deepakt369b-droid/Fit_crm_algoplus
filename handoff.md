# FitCRM Build

## Deployment handoff - 2026-08-24

### Current state

- Coolify application UUID: `eu4zxqgxdtqpnaa4kwkwqogo`.
- Coolify uses the repository `Dockerfile`, exposing port `80`, with health
  check `/healthz` on port `80`.
- Commit `cc60618` built successfully and Coolify reported the application as
  `running:healthy`.
- Testing configuration was added directly in Coolify: SQLite database, file
  sessions/cache, synchronous queue, log mailer, and `APP_ENV=testing`.
- Local testing hostname: `http://fitcrm.127.0.0.1.nip.io`. It is not public
  and requires the local Coolify Traefik proxy.
- Testing startup runs `ShieldSeeder` followed by `UserSeeder`.
- Intended test superadmin: `test@example.com` / `test`.

### Current blocker

The browser reaches the application, but the page reports:

```text
Fatal error: Allowed memory size of 536870912 bytes exhausted (tried to allocate 262144 bytes)
```

This is a 512 MB PHP memory exhaustion in Laravel container/bootstrap code,
not a Docker image build failure. Do not increase memory first. Investigate
recursive provider/container bootstrapping, especially the interaction between
`SuperAdminPanelProvider` and `AdminPanelProvider`.

### First tasks tomorrow

1. Inspect container logs and identify the first request path causing the
  fatal error.
2. Check whether `SuperAdminPanelProvider::panel()` recursively constructs or
  boots `AdminPanelProvider`.
3. As a diagnostic only, remove `SuperAdminPanelProvider` from
  `bootstrap/providers.php` and verify whether the normal admin panel loads.
4. Test `php artisan about`, `php artisan route:list`, and `/healthz` inside
  the container after each change.
5. Keep `APP_DEBUG=false` after debugging and redeploy with a forced rebuild.
6. Verify the test login, then remove or change the test account before
  production.

### Deployment history

| Commit | Deployment | Result |
|---|---|---|
| `05153a3` | `xlm0fgyghc7fadknuhbbd7qh` | Composer install failure |
| `6a38246` | `txza0raiyjf67o3qbi7hxttq` | Composer lock refresh failure |
| `19923e9` | `rwpr7z65lhhldxw2wgaxebgk` | Provider constructor failure |
| `1531cb4` | `ygcbvqfffna83cufc3jthqpb` | Container dependency failure |
| `da57a76` | `ana4c5ck3g9kjz2sr9sg9ngm` | MySQL connection refused |
| `4d23f6e` | `sa9kizhbaoko2qf8lqcooqft` | SQLite extension build failure |
| `cc60618` | `rh1dtiv04kqqc3aowba6ypjo` | Image and health check successful |
| `eccaa26` | `e01gglxmwnlg1xxdcsuxlaip` | Faker seed failure |
| `df638bb` | `v0llpgtvtbplytqiobjiuwh8` | Missing `super_admin` role |
| `d88e671` | `nv1erpeau62oi4aflt6ihvpy` | 512 MB memory exhaustion |

Revoke the Coolify API token used during this session before continuing.
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
> the M1 notes below.
>
> **Third update:** Node 4 M3 (automations) is now complete and
> committed as `8124977` — lang keys were finished for ar/fa/fr, the
> bracket-balance and brand-string sweeps were re-run clean, and the
> commit follows the same detail level as M1/M2 (see `git log` for the
> full message). The "M3 status — pick up here" section below is kept
> as-is for the historical record of what was in progress at the time;
> treat "uncommitted"/"pick up here" language there as describing that
> point in time, not the current state.
>
> **Fourth update:** the user asked to proceed straight to M4 without a
> pause at the M3 gate, so M4 (AI reply assistant + knowledge base) is
> now also complete and committed as `5e70cff` — new `anthropic-ai/sdk`
> dependency, `wa_ai_settings`/`wa_knowledge_base_articles` schema, the
> `AiReplyAssistant` service behind an `AnthropicMessagesClient` seam
> for testability, a `WhatsappKnowledgeBaseArticleResource`, an "AI
> Suggest Reply" action on the conversation view, and a new AI Assistant
> section in the superadmin Marketing settings tab. Two real bugs caught
> before commit: `AiReplyAssistant` checked `direction === 'inbound'`
> when the schema actually uses `'in'`/`'out'` (would have made every
> suggestion request fail), and `Settings::mount()` discarded its own
> AI-field additions by filling the form from the wrong variable. Full
> detail in the commit message — see `git log`. **Node 4 (M1–M4) is now
> functionally complete.** Awaiting a Human Gate review of M4 (and, if
> the user wants it, a combined look back at the whole node) before
> considering the WhatsApp merge done.

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

## M3 status — pick up here (in progress, uncommitted)

Node 4 M3 (automations: trigger + branch/wait/tag/webhook builder) is
functionally complete in code but **not yet lang-complete, not verified,
not committed**. `git status` shows 19 files (5 modified, 14 new).

### What's built (all written, none tested/executed)
- **Migrations**: `wa_automations` (trigger_type, trigger_config JSON,
  steps JSON, status, gym_id/phone_number_id both nullable), `wa_automation_runs`
  (status, current_step_index, context JSON, resume_at — indexed on
  `[status, resume_at]` for the resume sweep).
- **Models**: `WhatsappAutomation` (auto-fills `created_by` like
  `WhatsappBroadcast` does), `WhatsappAutomationRun`.
- **`AutomationStepExecutor`** (`app/Services/WhatsApp/`) — executes
  exactly one step, returns an outcome (`advance`/`jump`/`wait`/`fail`)
  for the job to act on. Step types: `send_template`, `add_tag`,
  `remove_tag`, `wait`, `condition` (branches via `true_step`/
  `false_step` indices), `webhook` (fire-and-forget POST, now correctly
  checks `$response->failed()` and logs rather than only catching
  connection-level exceptions — this was a real gap I caught and fixed
  before the corresponding test would have been testing nothing).
- **`ProcessWhatsappAutomationRun`** job — the execution loop. Has a
  **`MAX_STEPS_PER_INVOCATION = 200` safety cap** that fails a run with
  a clear error if a misconfigured `condition` step loops back on
  itself, instead of consuming a queue worker forever. A `wait` step
  ends the job entirely (status → `waiting`, `resume_at` set) rather
  than using a queue delay, since a wait can be days long and queue
  delays that long aren't reliable across every driver.
- **`ResumeWhatsappAutomations`** artisan command (`fitcrm:automations:resume`,
  scheduled every 5 minutes in `routes/console.php`) — finds `waiting`
  runs whose `resume_at` has passed and re-dispatches them.
- **`AutomationTriggerService`** — matches an inbound-message event
  against active automations (`contact_created`, `keyword_received`,
  `opted_in`) and starts a run. Wired into `InboundWebhookProcessor`,
  which now **also recognizes "START" as an opt-in keyword** (the
  reciprocal of the existing "STOP" opt-out) — added because the
  `opted_in` trigger needed a real event to fire on, and it's the
  obvious/expected counterpart to STOP.
  - **Important correctness fix already applied**: trigger-firing calls
    were moved to *after* the `DB::transaction()` block in
    `processInboundMessage()`, not inside it — dispatching a queued job
    from inside an uncommitted transaction risks a separate queue
    worker (on a different DB connection, in production with a real
    queue driver) reading the contact before it's actually committed.
    The M1/M2 code didn't have this hazard since it didn't dispatch
    anything from inside that transaction.
  - Also fixed in passing: the new-contact name extraction used chained
    array access on a possibly-null value (`$profiles->get($waId)['profile']['name']`),
    which emits PHP warnings when a payload has no `contacts` array.
    Switched to `data_get()`.
- **Filament `WhatsappAutomationResource`**: form is a `Repeater` over
  `steps` with per-step-type conditionally-visible fields (Select for
  type, then Select/TextInput fields shown only for the relevant step
  type). No dedicated create/edit pages — same modal-based pattern as
  `GymResource`/`DeviceResource`. Has a `view` page (unlike those two)
  specifically so `RunsRelationManager` (read-only run history) has
  somewhere to attach — relation managers need a ViewRecord/EditRecord
  page, they don't work from modal-only actions, which I initially got
  wrong and had to fix.
- **Factories**: `WhatsappAutomationFactory`, `WhatsappAutomationRunFactory`.
- **Tests written** (not executed): `AutomationStepExecutorTest` (all
  six step types, including the unknown-step-type failure and the
  no-phone-number failure), `ProcessWhatsappAutomationRunTest`
  (sequential execution, wait/resume round-trip, **the infinite-loop
  cap actually tripping**, inactive-automation failure, already-finished
  run no-op), `ResumeWhatsappAutomationsTest` (due run resumes, future
  run left alone), `AutomationTriggerIntegrationTest` (contact_created
  fires for a new contact and not a returning one, keyword_received
  matches, START both opts in and fires `opted_in`).

### What's NOT done yet — exactly where I stopped
1. **Lang keys are half-done.** English (`resources/lang/en/app.php`)
   is complete for M3 (`resources.whatsapp_automations.*` and a large
   `whatsapp.*` block: triggers, steps, step_types, operators, etc.).
   Arabic has **only** the `resources.whatsapp_automations.{singular,plural}`
   pair added so far (I was mid-edit on the ar file when stopped) — it
   still needs the full `whatsapp.*` block that English has. **Farsi
   and French have neither yet.** Copy the English key *names* exactly
   (see `resources/lang/en/app.php`, search for `'trigger' => 'Trigger',`
   through `'status_updated' =>`) into ar/fa/fr with translated values,
   the same way M1/M2's lang additions were done — find each locale's
   equivalent insertion point (same line numbers as English is usually
   close but not guaranteed after this partial edit; search by
   surrounding key names, don't assume line numbers).
2. **Bracket-balance check not re-run since the ar edit landed.** Before
   doing anything else, run the same sweep used throughout this build:
   ```
   for loc in ar en fa fr; do
     o=$(grep -o "\[" resources/lang/$loc/app.php | wc -l)
     c=$(grep -o "\]" resources/lang/$loc/app.php | wc -l)
     echo "$loc: [ =$o  ] =$c"
   done
   ```
   and the PHP brace/paren sweep over `git status --porcelain` files
   (see any prior commit message for the exact one-liner used).
3. **`gymie|lubus` grep sweep** — not re-run for M3 files specifically
   (should be a non-issue since nothing in M3 touches branding, but
   confirm rather than assume).
4. **Commit** — once 1–3 are clean, commit as "Node 4 M3: WhatsApp
   automations (trigger + branch/wait/tag/webhook builder)", matching
   the detail level of the M1/M2 commit messages: what was built, the
   two real bugs caught (transaction-timing on trigger dispatch, the
   webhook step's silent-on-5xx gap), and the loop-safety-cap design
   decision and why.
5. **Human Gate for M3** — present a change summary to the user before
   touching M4, same pattern as M1/M2.

### Design decisions worth restating if asked
- **Step JSON contract** lives in the `wa_automations` migration's
  docblock and is authoritatively implemented in
  `AutomationStepExecutor::execute()` — if the two ever disagree,
  the executor is the ground truth since it's what actually runs.
- **`true_step`/`false_step` are raw step-array indices**, not a
  friendlier reference. Admin has to count position (0-based). This is
  a real UX rough edge, disclosed rather than hidden — a nicer
  "click to select target step" UI is a reasonable future improvement,
  not attempted here given the effort budget.
- **`context` column on `wa_automation_runs` is currently unused** — no
  step type reads or writes it yet. Kept as a forward-compatible
  placeholder (cheap to keep, clearly documents intent) rather than
  removed, since the plan's step vocabulary may grow to need
  cross-step state.

## Next session — start here
1. Re-read the plan file for full Node 4 context if anything here is ambiguous.
2. Node 4 is functionally complete: M1 (`git log` for the commit),
   M2 (`f03818c`), M3 (`8124977`), M4 (`5e70cff`). Nothing is
   uncommitted as of this update.
3. Run `composer update anthropic-ai/sdk` (or a full `composer update`)
   before `composer install` will succeed — M4 added a dependency the
   committed `composer.lock` doesn't know about yet.
4. Present a combined Human Gate for the whole WhatsApp merge (or just
   M4, if the user already reviewed M1–M3 separately) before treating
   Node 4 as done. Once cleared, the only environment this has ever run
   in is static analysis — the very first real validation should be
   `composer install && php artisan test` on a machine with PHP 8.2, or
   the first CI/Coolify build.

## Environment reminders
- No `php`, `composer`, or `docker` binaries available here — verification is static only (grep-based brace/bracket balance, targeted WebFetch against upstream docs/source for anything version- or API-specific). Say so plainly rather than implying something was tested when it wasn't.
- `sh` *is* available (Git Bash) — used to lint the two shell scripts (`docker/entrypoint.sh`, `scripts/coolify-deploy.sh`) via `sh -n`, which is real signal, unlike the PHP brace-counting which is just a heuristic.
