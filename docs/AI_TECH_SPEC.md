# AI Technical Specification — Task Manager (Current Implementation)

> Status: **CURRENT STATE ONLY** — verified against code Oct 2026 (Laravel 12 + Blade + Vite).
> Convention: each section has **Current** (what the code does, with file:line) and **→ Recommendation** (what to change).
> Token numbers are **estimates** via `chars/4` (same heuristic the codebase uses in `NoteExportController::preview`) unless stated otherwise.
> Where code is ambiguous: marked **Not Verified**. Nothing is guessed.
> Supersedes `docs/AI_SYSTEM.md` where they conflict (corrections listed in §12).

---

## 1. Architecture

### 1.1 Components

| Layer | Files |
|---|---|
| Entry (web UI) | `resources/views/ai/index.blade.php` (~1800 lines), `resources/views/layouts/_ai_chat.blade.php` (floating widget), `resources/views/ai/settings.blade.php` (~340 lines), `resources/views/planner/_ai-schedule-modal.blade.php` |
| Controllers | `AiChatController` (~1320 lines), `AiSettingsController`, `AiProviderController`, `AiActionController`, `AiPlanController`, `NoteAiController` (notes AI), `PlannerController::aiOptimize` (heuristic, NOT an LLM call — §1.4) |
| Services | `AiProviderService` (resolve/transport), `AiToolService` (~2400 lines: 22 tools), `AiLogger` (file log), `LinaFallbackBrain` (~500 lines, offline), `Notes/NoteAiService` (notes modes), `WorkoutImportService::parseWithAi/requestModel` (workout JSON parse), `FaDateParser` (Persian date cues) |
| Models/tables | `AiSetting`, `AiProvider`, `AiConversation`, `AiMessage`, `AiPendingAction` (15-min expiry, cap 30), `AiPlan` (caps 5/3/30/100/10/10) |
| Config | `config/ai.php` (5 built-ins + model catalog), `config/services.php` (env keys), `config/logging.php:68-73` (channel `ai` → `storage/logs/ai.log`, `single` driver) |
| Routes | `routes/web.php:183-219` (19 AI routes; throttle only on confirm paths) |
| Background jobs | **None** — `app/Jobs/` does not exist; every AI operation is synchronous in the HTTP request |
| Tests | `tests/Feature/Ai{Tools,TaskResolve,Plans,PageRender,MultiProject,Modes,CollabReport}Test.php` (7 files) |

### 1.2 Full request path (streaming chat)

```
Blade JS fetch → POST /ai/stream {message≤8000, conversation_id?, history?≤40×8000, mode}
 → AiChatController::stream (routes/web.php:195)
   1. Validator → 422 JSON (never redirect; fetch would follow to HTML)
   2. Drop empty turns
   3. AiProviderService::resolve($user) → {provider,key,model,type,config} | null
   4. Find-or-create AiConversation scoped by user_id; save user AiMessage; auto-label (first 60 chars)
   5. No provider → LinaFallbackBrain → chunked SSE + persist, return
   6. buildContext($user) + buildMessages(...) → $messages
   7. agent && type!=openai → blocked SSE message, persist, return (nothing changes)
   8. type==openai → streamOpenAi(): Guzzle POST stream:true, 256-byte reads, forward deltas,
      accumulate tool_calls by index; on [DONE]: persist assistant msg + touch + emitToolProposals +
      emitWorkoutFallbackIfNeeded + emitToolMissedNotice
      Mid-stream {"error"} with empty buffer → sync fallback (callOpenAiSyncRaw) then chunk
      Non-200 upfront → sync-fallback branch (same emits)
   9. type!=openai → callProviderSync() then fake-chunk SSE (5-char chunks, 12ms sleep), persist
```

Non-streaming `POST /ai/chat` (`AiChatController::chat:126-189`): same validate/resolve/context, returns JSON `{model,provider,reply?,proposal/proposals?}`; **does not persist conversation**; errors returned as HTTP 200 with `reply: "AI error: …"`.

### 1.3 Mode matrix (server is authoritative)

| | chat | agent |
|---|---|---|
| tools sent | none (`toolsForResolved` → null unless agent) | full `definitions()` (22 tools) iff `type==openai` |
| system block | `MODE: CHAT` read-only; creation requests → "switch to Agent" | `MODE: AGENT` full instruction block (~2k tokens) |
| Gemini/Anthropic | normal text reply | blocked message, nothing changes |
| frontend | ignores proposal packets in chat mode (console.warn) | renders proposal/plan/workout cards |

Toggle in chat header, stored in `localStorage`, sent as `mode` per request.

### 1.4 “Planner AI” is NOT AI (correction of old doc)

**Current:** `PlannerController::aiOptimize:123-170` loads today's open tasks and assigns `time_period` by **hardcoded rule** (high/≥1.5h→morning, medium→afternoon, else evening). Zero LLM/provider calls. The modal (`_ai-schedule-modal.blade.php`) and button labels call it “Lina AI Copilot” — misleading.

**→ Recommendation:** rename UI to “Smart schedule” or route through a real model call.

### 1.5 Corrections to `docs/AI_SYSTEM.md`

- Old §3.4 says “16 tools / cap 5” — **outdated**: code has **22 tools** (`AiToolService::TOOLS:23-33`) and `MAX_OPEN = 30`.
- Old token section implied per-plan caps only; caps also exist per-phase and per-day (`MAX_EXERCISES_PER_DAY`, `array_slice` in `normalizeDay`).
- `AiToolService` is ~2400 lines (do not cite 2555).

---

## 2. Data & Context

### 2.1 Current — exactly what enters the prompt

`AiChatController::buildContext:1247-1285` — **no LIMIT, no pagination**, whole workspace every message:

| Block | Columns selected | Per-item size |
|---|---|---|
| PROJECTS | `name,status,end_date,budget` (+`project:id,name` eager on tasks) | ~1 line each |
| TASKS | `title,status,priority,due_date,project_id` (ALL user tasks) | ~1 line each |
| NOTES | `title,content,tags` → `title [tags]: strip_tags(substr(content,0,120))` | ≤~150 chars each |
| REMINDERS | `title,date,time,priority,is_completed,tags` | ~1 line each |
| ROUTINES | `title,frequency` | ~1 line each |
| FILES | `name,type` only (content never read) | ~1 line each |

Empty blocks omitted. Failure → literal `(Could not load user data)`.

`buildMessages:1161-1229`: system = identity (`You are Lina … built … by {$user->name}` — **bug: claims the user built it**, §12-P1) + `Today is …` + capability list + lang directive (fa→Persian / en→English) + CHAT or AGENT block + `--- USER WORKSPACE DATA ---` + history (client-supplied, filtered empties) + new message. All parts `cleanUtf8()`.

History: **client-supplied** `history[]` (≤40 × ≤8000 chars), NOT loaded from `ai_messages`. Server only filters whitespace-only turns.

Notes AI (separate path): `NoteAiService::contextFor($notes, $maxChars=60000)` → `### title [kind · date]` + `> summary` + full content until budget; `analyzeCollection` default budget 60000, `summarizeSubject` 50000, `extractFromNote` single note, maxTokens 2000–3000, temp 0.3. Collection queries `limit(200)` (`NoteAiController:49-53`); export preview `limit(2000)` (`NoteExportController`).

Workout parse: `WorkoutImportService::parseWithAi:27-51` — system (strict JSON schema) + `Parse this workout plan:\n\n{source≤30000}`; `max_tokens 6000` (anthropic) / `response_format json_object` (openai) / `responseMimeType application/json` (gemini).

### 2.2 Current — volume estimate (must measure per user)

For a user with 300 tasks / 100 notes / 50 reminders: context ≈ 300×80 + 100×150 + 50×80 + overhead ≈ **~45k chars ≈ ~11k tokens per message** — before system prompt (~2.5k) and history (up to 320k chars allowed). **→ Recommendation:** §3 + §13.

### 2.3 Current vs needed

- Needed every turn: ~20–50 relevant items + counts for the rest. Currently: everything.
- Needed: file/note content only on explicit attach. Currently: 120-char note snippets always (neither a summary nor usable content), file content never.
- Needed: history from DB. Currently: trusted client blob (security §5.2).

---

## 3. Token & Cost

### 3.1 Current — all AI I/O and caps

| Call | Input cap | Output cap | Temp |
|---|---|---|---|
| chat/stream system+context+history | unbounded (see §2.2) | 2048 plain / 4096 with tools / **16000** if workout tool present (`openAiPayload:389-419`) | 0.7 |
| Gemini/Anthropic via chat | same | `maxOutputTokens`/`max_tokens` 2048 | 0.7 |
| `testConnection` ping | `ping` | 5 tokens | n/a |
| NoteAi complete | 50–60k chars context | 2000–3000 | 0.3 |
| Workout `requestModel` | source ≤30000 chars | 6000 (anthropic) / default 2048+ (openai payload, no override!) | default |
| Fallback brain | 0 external | 0 | — |

Note: openai path in `WorkoutImportService::requestModel:221-223` uses `openAiPayload()` default max_tokens (2048/4096) then sets `response_format json_object` — a full 7-day plan may truncate (the 16000 special-case exists only in `AiChatController` path).

### 3.2 Current — pure waste (verified)

1. Full AGENT block (~2k tokens) sent on **every** agent message including “hi”.
2. All 22 tool definitions sent on every agent call regardless of intent (~3k tokens est.).
3. Whole-workspace context rebuilt per message, no cache (same user, 5s apart → 2 full builds + N queries).
4. Client history up to 320k chars accepted into prompt.
5. `emitToolMissedNotice` path: full context+tools paid, zero tools returned, user must resend (double spend).
6. Non-openai agent: full context paid, then blocked message (tools never sent, but context was built).

### 3.3 Current — limits & cost-risk points

- No throttle on `POST /ai/chat`, `POST /ai/stream` (only confirm paths have `throttle:30,1`).
- No per-user daily/monthly cap, no `ai_usages` table, provider never returns usage (`choices.0.message` only; `usage` field ignored everywhere — verified by grep: zero `prompt_tokens` reads).
- `.env` fallback: keyless user silently spends admin's key (`AiProviderService::getKey:110-116`).
- `POST /ai/chat` errors return HTTP 200 — clients/retry loops cannot distinguish failure (retry amplification risk).

**→ Recommendation:** throttle 15/min on chat/stream; server-side history (last 10 from DB); context budget ≤6000 chars w/ cache 60s; split system prompts (chat ~300 tok); intent-routed tool subsets; disable `.env` fallback in prod; persist provider `usage` per request (§10 schema).

---

## 4. Agent & Tool System

### 4.1 Current — tool inventory (22)

Read-only: `report_generate`. Everything else writes. Full I/O table:

| Tool | Key inputs (*required) | Validation highlights | Side effect on confirm |
|---|---|---|---|
| `task_create` | `title*`, `project_id?/project?`, `parent_id?`, `due_date?`, `priority?`, `status?`, `description?` | parent must be user's + same project (project inherited); project must be user's; null project allowed | `tasks` insert (user_id forced) |
| `task_update/complete/delete` | `id?/task?` + scope `project?/project_id?` | `resolveTask`: ID scoped, or title LIKE (+project scope); ambiguous → error | update / set completed / delete (+subtasks) |
| `reminder_create` | `title*`, `date?`, `time? HH:MM`, `priority?`, `description?`, `location?` | empty date/time → `FaDateParser` on title+desc (fa cues: امروز/فردا/پس‌فردا، ساعت ۶ عصر→18:00, Jalali dates, fa digits) | `reminders` insert |
| `reminder_complete/delete` | `id*` | ownership | complete (may spawn next recurrence — verify in Reminder model) / delete |
| `note_create/update/delete` | `title*/content*` / `id*` | ownership (update allows partial) | `notes` write |
| `project_create` | `name*`, `parent?/parent_id?`, `status?`, `description?` | parent owned; depth ≤5 | `projects` insert |
| `project_add_member` | `project?/project_id?`, `user*`, `role?` | project owned; member via `resolveMemberUser` (numeric→find, @→email, else `LIKE name%` first) — **wrong-person risk**; self → error | `project_teams syncWithoutDetaching` |
| `note_link` | `note?/note_id?`, `target_type*`, `target?/target_id?` | both sides `where user_id`; no self-link | `NoteLinkService::attach` |
| `report_generate` | `range? today/week/month` | enum | none (read-only) |
| `checklist_add/toggle` | `task(+scope)/task_id?` + `name*` / `id*` | task owned | `checklist_items` write |
| `routine_create` | `title*, frequency*` + `days?` (weekly REQUIRED), `month_days?`, `every_n_days?`, `time_period?`, `tracking_mode?`, `value_kind/unit/label?`, `steps[]≤20` | mirrors `RoutineController` rules | `routines` + steps insert |
| `routine_complete` | `id*`, `date?` | owned; never un-completes; repeat → “already done” | completion row |
| `routine_log` | `routine?/routine_id?`, `value*`, `date?`, `item?/set_no?` | tracking-mode checks; time-kind accepts HH:MM→minutes | log row; full sets → auto-complete |
| `routine_delete` | `id*` | owned | soft-delete (history kept) |
| `plan_propose` | `title*` + `projects[]≤5` XOR `project+subprojects[]≤3` XOR `routines[]≤7` + `reminders[]≤10` + `notes[]≤10`; task titles ≤120 | counts enforced; members are free-text emails/names | creates `AiPlan` only (phased exec §4.3) |
| `workout_plan_propose` | `title*`, `days[7]` w/ exercises (`name*` + sets/reps/weight/RIR/tempo/side/amrap/circuit), `start_date?`, `rules[]?` | shape validated; ≤7 days, ≤40 ex/day | creates `WorkoutImport(PREVIEW,+30min)` only |

`validateCall()` first normalizes (incl. camelCase aliases `projectId/dueDate/monthDays`) and legacy dotted names; validators are read-only.

### 4.2 Current — lifecycle

```
tool_call (raw args JSON) → createPendingFromToolCall: normalize → cap (30 open, unexpired)
 → validateCall → AiPendingAction{pending,+15min,idempotency} + preview{}
 → SSE packet {tool_proposal | plan_proposal | workout_import_proposal | error}
 → user confirm → runAction: dedupe(executed) → expiry check → RE-VALIDATE args
 → status=confirmed → execute() in DB::transaction → executed+executed_at
 → assistant ✅ message in conversation (if conversation_id) + AiLogger
reject → rejected (dedupe if not pending). confirmAll/rejectAll: oldest 20 only.
```

Double-execution guards: `idempotency_key` unique (DB), status transitions (`executed`→dedupe), plan `phase == pointer` check. **Gap:** `confirmAll` limit 20 < `MAX_OPEN` 30 (10 stranded); no Undo after execute; streaming retry could `createPendingFromToolCall` twice for same tool_call (no dedupe key from model side — idempotency is random per row, not derived from call).

### 4.3 Current — plan execution & phases

`buildPlanPhases()` → fixed order: projects → subprojects → tasks → subtasks → reminders → notes (or single `routines` phase); zero-count phases start `done`. `confirm-structure` flips proposed→confirmed (+`advancePastDone`, sliding `touchExpiry`). `confirm-phase{phase?,run_all?}`: rejects stale `phase != pointer` as deduped; loops `executePlanPhase()` (one `DB::transaction` per phase); each phase resolves prior-phase result IDs server-side (model never sees IDs); unknown member refs reported as `skipped` in message. `cancel` keeps created items.

### 4.4 Current — duplicate/misfire points

- Double-click confirm: guarded (status + phase pointer) — OK.
- `run_all` partial failure: stops, earlier phases stay (message lists what ran) — acceptable, must be surfaced (it is, via 422 + fresh plan).
- Stream reconnect: user message already persisted before provider call → retry creates a **second** user message + second set of pendings (no dedupe).
- `emitWorkoutFallbackIfNeeded` + `parseWithAi` second LLM call on same user text (extra spend, plus `WorkoutImport` row even if user never confirms — capped at 3 open).
- `emitToolMissedNotice` regex (`"(title|description|due_date|…)"` ×2 + `{`) can false-positive on code examples pasted by user.

**→ Recommendation:** derive idempotency from (conversation_id + tool + args hash); cap fallback to explicit user intent; move missed-notice to a structured `type` packet instead of appended text.

---

## 5. Security

Severity: **Critical / High / Medium / Low**. All locations verified.

| # | Sev | Area | Location | Problem | Why it matters | Suggested fix |
|---|---|---|---|---|---|---|
| S1 | Critical | Cost abuse — no throttle on LLM ingress | `routes/web.php:194-195` (`ai.chat`, `ai.stream` — no middleware) | unbounded 8k-char + 40-turn-history requests per user | single user/script can run arbitrary provider spend & DoS PHP workers (60–90s holds) | `throttle:15,1` + daily cap + concurrent-stream guard |
| S2 | High | Cost abuse — env fallback | `AiProviderService::getKey:110-116`, `resolve:173-196` | keyless users silently consume admin `.env` key | BYOK intent defeated; surprise bill | disable fallback when `APP_ENV=production` (or explicit `AI_ALLOW_ENV_FALLBACK=false`); show BYOK wizard instead |
| S3 | High | XSS — unsanitized markdown | `ai/index.blade.php:826,1060-1061`, `_ai_chat.blade.php:465-466,528` (`innerHTML = marked.parse(...)`, no DOMPurify — verified by grep) | model output (or injected history text) can inject `<script>`/event handlers | stored/reflected XSS in victim's own session; poisoned shareable content | bundle `DOMPurify`, `innerHTML = DOMPurify.sanitize(marked.parse(...))` |
| S4 | High | TLS disabled | `AiProviderService::postJson:339`, `AiChatController::streamOpenAi` (`verify => false`) | MITM on provider traffic carrying user API keys + workspace dumps | key + PII interception on hostile networks | `verify => true`; make configurable only for local dev |
| S5 | High | Debug log cross-user leak | `AiChatController::debug:1288-1319` + `AiLogger::tail:95-108` (whole-file read, no user filter) + frontend poll `index.blade.php:1128` | `ai.log` is global; previews contain user text | user A reads user B's message previews | scope tail by `user_id`, or gate `/ai/debug` to admin/debug mode |
| S6 | High | SSRF via custom provider URL | `AiProviderController::validated:76-83` (`base_url: url` only) + server-side `postJson`/`testConnection` | any internal URL (169.254.169.254, intranet) reachable by Laravel server | cloud metadata / internal service probing | allowlist public DNS, block private ranges, `timeout` already 30s — add method/path pinning |
| S7 | Medium | History injection | `AiChatController::chat:128-140`, `stream:217-220` (history from client) | forged `assistant` turns steer system behavior / fake context | prompt-injection primitive, bypasses “read-only” narrative | load last N from `ai_messages` by `conversation_id`; ignore client history |
| S8 | Medium | User enumeration / wrong member | `AiToolService::resolveMemberUser:908-922` (`LIKE name%` first match) | attacker learns user existence; honest user adds wrong person to project | privacy + integrity | require exact email (or numeric ID); confirm candidate preview before execute |
| S9 | Medium | API key handling | `AiSettingsController:update:86-101`, `AiProviderController` | masked-placeholder skip relies on `••••` prefix check; keys in `TEXT` cols (encrypted at rest — OK) | pasting a key starting with `••••` silently ignored; keys never rotated/logged | compare against stored suffix server-side instead of prefix sniffing; add “last used” timestamp |
| S10 | Medium | Race — double confirm | `AiActionController::runAction:115-167` (check-then-set without row lock) | two concurrent confirms both pass `isActionable` | double execute (two rows) | `SELECT … FOR UPDATE` / atomic `UPDATE … WHERE status='pending'` |
| S11 | Medium | Race — plan phase | `AiPlanController::confirmPhase:69-91` (pointer check then execute, no lock) | two `run_all` calls interleave phases | out-of-order/duplicate phase writes | row lock on `ai_plans` row for the request duration |
| S12 | Low | Error oracle | `chat:183-188` returns 200 `"AI error: {provider message}"` | provider/model existence oracle; noisy | low | keep generic message to user, log detail server-side |
| S13 | Low | Stale docs | `docs/PROJECT-GUIDE.md:94` (“16 tools / cap 5”) | misleads reviewers into wrong threat model | low | update to 22 / 30 |

AuthN/Z positives (keep): all AI reads/writes scoped `where user_id` + `abort_if(403)`; tool validators re-check ownership at execute time; keys encrypted (`encrypted` cast), never sent to browser (masked `••••last4`), never logged (`sanitize`).

**→ Recommendation order:** S1+S2+S4+S5 (MVP), then S3+S6+S7, then S8–S11.

---

## 6. Provider System

| Aspect | OpenAI-compatible (openai/deepseek/meta + customs) | Gemini | Anthropic |
|---|---|---|---|
| Transport | `POST {base}/chat/completions`, Bearer | `POST {base}/{model}:generateContent?key=` | `POST {base}`, `x-api-key` + `anthropic-version 2023-06-01` |
| Message shape | native | converted (`toGeminiContents`; assistant→model; system→systemInstruction) | converted (`toAnthropicMessages`; system split) |
| Streaming | true SSE passthrough | sync → fake chunks | sync → fake chunks |
| Tools/agent | full | **none** (blocked msg) | **none** (blocked msg) |
| JSON mode (workout) | `response_format json_object` | `responseMimeType application/json` | prompt-only (no JSON constraint) |
| Retry | 3× on 429/5xx (1.5s/3s sleeps) in `postJson` | same | same |
| Timeout | 60 (chat/stream), 90 (notes/workout), 30 (test ping) | same | same |

Fallback behavior: default-provider-with-key → first-enabled-provider → offline brain. No per-request fallback (if chosen provider 500s after retries → error, no auto-switch). No model-level fallback except OpenRouter `models[]` when model contains comma (`openAiPayload:409-415`).

Custom providers: `type ∈ {openai,gemini,anthropic}` + arbitrary `base_url` + free-text model + encrypted key; `endpointFor` normalizes chat-completions suffix; `testConnection` pings with 5 tokens. Built-ins have no Test button (gap).

Agent limitation (current): function-calling exists **only** for `type==openai`. Gemini/Anthropic — including their native function-calling APIs — are not wired; agent toggle still visible (UX §9).

---

## 7. Performance

### Current — verified hotspots

1. **Unbounded context queries every message** (`buildContext`): `tasks`/`notes`/`reminders`/`routines`/`files` full-table per user per message, no `limit`, no cache, no indexes mentioned on AI path (migrations show FK indexes only). A 1k-task user pays ~1k-row fetch + ~80k-char prompt on each keystroke-send.
2. **N+1-adjacent**: `Task::…->with('project:id,name')` is eager (good); but `buildMessages` re-runs everything per request; `serialize()`/`previewPlan()` recompute counts repeatedly.
3. **Synchronous long holds**: provider calls block PHP workers 60–90s (`postJson` timeout + `streamOpenAi` 60s + retry sleeps). No queue (`app/Jobs` absent), no horizon config.
4. **Fake streaming** for Gemini/Anthropic: full wait, then `usleep(12000)`-paced echo — TTFB equals full latency.
5. **Frontend monolith**: `index.blade.php` ~1800 lines inline CSS/JS; CDN `marked`+`hljs` (network-gated render); full `innerHTML` re-render of streamed markdown per chunk (`:1060-1061`) — O(n²) DOM churn on long replies.
6. **Log tail**: `AiLogger::tail()` loads entire `ai.log` via `file()` per `/ai/debug` call.
7. **Workout fallback double-LLM**: text-only reply → `parseWithAi` second model call (up to 30k chars input) + row creation, unprompted.

### Current — indexes (from migrations)

`ai_pending_actions(user_id,status)`, `(user_id,expires_at)`; `ai_plans(user_id,status)`; `ai_providers(user_id,sort_order)`; FK cascades everywhere. **Missing:** composite `(conversation_id,created_at)` on `ai_messages` (history pagination), no token/cost columns (nothing to index yet).

**→ Recommendation:** context budget + 60s cache; server history (10 msgs, indexed); move long parses to jobs; DOMPurify+bundled assets; incremental stream render (append, not full re-parse); `daily` log driver + prune command.

---

## 8. AI Personalization

### Current — what the AI actually knows

- Per message: full workspace dump (§2.1) + user `name` (misused as creator) + locale (fa/en directive) + today’s date. Nothing else persists.
- No preferences table/columns (tone, language override, wake time, goals — **none exist**).
- No memory: `ai_messages` is verbatim history only; no summaries, no fact extraction, no cross-conversation recall.
- No pattern learning: task/project distributions recomputed nowhere; `report_generate` is stateless per call.
- Personality: fixed “Lina” strings; per-user custom name **not implemented** (decision recorded, code unchanged).
- Permanent vs ephemeral context (current): permanent = `ai_settings` (provider/model/keys); ephemeral = everything else (rebuilt per request, then discarded).

### → Recommendation (no big token rise)

1. `ai_settings` += `assistant_name(40)`, `tone(friendly/formal)`, `reply_lang(fa/en/auto)` → injected as 3-line system addition (~60 tokens).
2. Per-conversation rolling summary (one extra model call per ~20 turns, stored on `ai_conversations.summary`) instead of growing history.
3. `ai_user_facts` (key/value, user-editable, explicit “remember this”) — top ~20 facts prepended (~300 tokens), full control + deletable (GDPR-friendly).
4. Keep workspace dump replaced by budgeted relevant-context (§3); never add “learned embeddings” in MVP scope.

---

## 9. UI/UX — current state

| Surface | Current | Confusing points (verified) |
|---|---|---|
| Chat | bubbles + `marked` + `hljs` + copy btn; typing dots; char counter (8000); chips; auto-label convs; sidebar (new/rename/delete/clear) | “No provider” dead-end (offline text only); errors as 200-text; no Stop/Retry/Edit-resend; no per-conv model badge |
| Agent toggle | header switch, `localStorage` | visible & clickable for Gemini/Claude → mid-stream English block message; user already paid context |
| Confirmation cards | rows table + red impact for deletes + expiry display + Confirm/Cancel + confirm-all | `confirmAll` caps at 20 while 30 may pend (silent 10); no Undo after execute |
| Plan cards | stepper (6 phases) + per-phase Run + Run-all + Cancel + skip-zero phases | `cancel` keeps partial items (stated, but easy to miss); phase failure leaves half-built tree |
| Workout cards | preview + `workouts.imports.show` link + confirm-in-place | fallback import row created without explicit consent (cap 3) |
| Loading/streaming | “thinking…” then token append; `[DONE]` handling; reconnect → Not Verified (no explicit resume) | long Gemini waits with zero intermediate feedback (fake stream starts only after full response) |
| Errors | inline error text / `⚠️ …nothing was saved` appended notice | appended notice is part of persisted message (pollutes history); 200-error conflation |
| Mobile | collapsible sidebar | composer overlaps floating time widget (patched via CSS offset); tables in cards overflow |
| Settings | default provider+model selects (JS-populated), 5 key rows (masked+Clear), custom table (Test/Edit/Delete/Use-as-default) | no Test for built-ins; masked-placeholder prefix check fragile; `is_default` column unused |

**→ MVP UX asks:** Stop button (AbortController), Retry (server history resend), per-conv model pin, disable agent toggle when `agent_ready=false` with FA tooltip, BYOK 3-step wizard with provider key links, Undo ≤30s for delete/complete, structured (non-persisted) notices, `DOMPurify`, bundled assets.

---

## 10. Observability

### Current — what is logged

- `AiLogger` → `ai.log` (`single` driver, unbounded): `request.received/resolved/invalid/offline_fallback/no_provider`, `context.failed`, `request.completed/failed`, `stream.openai_start/sync_fallback_provider/no_tool_calls/tool_calls_received/proposal_emitted/text_only_no_tools/tool_missed_text_json`, `provider.tool_calls/empty_response`, `tool.proposed/proposal_capped/proposal_invalid`, `plan.proposed/structure_confirmed/structure_expired/phase_executed/phase_failed/cancelled`, `action.executed/confirm_expired/confirm_invalid/execute_failed/rejected/confirm_all/reject_all`. Fields: `rid, user_id, endpoint, mode, provider, model, type, message_preview≤300, reply_len, tools[], counts, errors`. Secrets redacted; blobs truncated at 800.
- Scattered `Log::info/warning/error` to `laravel.log` (`ai.tool.*`, `ai.plan.*`, `AI chat/stream …`).
- **Not logged anywhere:** latency, input/output tokens, cost, context_chars, history_count, HTTP status per provider call, user-agent, IP, concurrent streams.

### → Recommended `ai_request_logs` schema

```
id, request_id(string, indexed), user_id(FK, indexed), endpoint(chat|stream|note|workout),
mode, provider, model, message_chars, context_chars, history_count, tool_names(JSON),
http_status, latency_ms, prompt_tokens?, completion_tokens?, status(ok|error|offline|blocked),
error_preview, created_at (index user_id+created_at). Prune >30d via ai:prune-logs.
```

Beta metrics (5): p50/p95 `latency_ms` by provider; error rate by `status`; `context_chars` distribution; tool-call rate + confirm rate; daily requests/user (abuse signal). Plus: offline-rate (BYOK friction), agent-block rate (Gemini/Claude share).

---

## 11. Failure & Edge Cases (per flow — current behavior)

| Flow × failure | Current behavior | Gap? |
|---|---|---|
| Provider 500/429 | `postJson` retries ≤3 (1.5s/3s) then throw → chat: 200 `"AI error: HTTP …"`; stream-openai: sync fallback attempt | no cross-provider failover; user sees raw-ish error |
| Timeout (60–90s) | exception → same as above | worker held whole time; no job offload |
| Stream cut mid-way | partial text persisted only if non-empty (`exists` check); tool_accum of partial call **dropped silently** | partial tool_call JSON lost, no recovery |
| Mid-stream `{"error"}` | if buffer empty → sync fallback; else persist partial + `{error}` packet + `[DONE]` | OK-ish; error packet schema ad-hoc |
| Double confirm (click/race) | second returns `deduped:true` (status check) — no row lock, tiny race window | §S10 |
| Concurrent manual edit + AI execute | `runAction` re-validates at execute time; plan phases resolve IDs fresh | last-writer-wins on scalar fields (no version check) — acceptable, note it |
| Expired confirmation | `markExpired` + `code:expired` + “Ask Lina again” | no re-propose shortcut |
| Invalid/malformed tool args | `validateCall` rejects → `{error}` packet, nothing stored (except plan/workout error packets) | model not told why (no feedback loop into same turn) |
| Text-instead-of-tools | `emitToolMissedNotice` appended warning persisted into message | pollutes history; no retry-with-hint |
| Partial plan (`run_all` fails mid-way) | stops, 422 + fresh plan, created items remain | must disclose clearly (does via message) — no rollback by design |
| Workout fallback fail | Persian message pointing to manual Import flow | OK |
| Gemini/Anthropic + agent | blocked SSE, persisted | paid context, zero value — gate earlier |
| No provider | offline brain reply persisted as `lina-offline` | fine; FA coverage weak |
| `conversation_id` of another user | `where(id).where(user_id)->first()` → miss → **new conversation created** (no 403!) | IDOR-adjacent: silent fork instead of error — log + 403 preferred |
| `phase != pointer` | deduped OK | OK |
| `clearConversation` | hard-deletes messages | audit trail lost; prefer soft/archive |

---

## 12. Current Problems (registry)

| ID | Sev | File | Line/Method | Problem | Why it matters | Suggested fix |
|---|---|---|---|---|---|---|
| P1 | Critical | `routes/web.php` | 194-195 | `ai.chat`/`ai.stream` have no throttle | automated spend/DoS | `throttle:15,1` + daily cap |
| P2 | Critical | `AiChatController` | `buildContext:1247-85` | unbounded full-workspace context, no limit/cache | 10k+ tokens/msg; cost + latency + context overflow | budget ≤6k chars + relevant-first + 60s cache |
| P3 | Critical | `resources/views/ai/index.blade.php` + `_ai_chat` | `:826`,`:1060-61`; `:465-66,528` | `innerHTML = marked.parse()` without sanitizer | XSS via model/history content | DOMPurify + bundled assets |
| P4 | High | `AiProviderService` | `getKey:110-16` | `.env` fallback bills admin for keyless users | surprise cost; breaks BYOK | gate by env flag; BYOK wizard |
| P5 | High | `AiChatController` + `AiProviderService` | `streamOpenAi` opts; `postJson:339` | `verify => false` | MITM steals keys + workspace | `verify => true` |
| P6 | High | `AiChatController` | `debug:1288-1319`, `AiLogger::tail:95-108` | global log tail exposed per user + frontend poll | cross-user PII leak | per-user scope or admin-only |
| P7 | High | `AiProviderController` | `validated:76-83` | `base_url: url` only; server fetches it | SSRF to internal/metadata endpoints | private-range block + allowlist |
| P8 | High | `AiChatController` | `chat:128-40`, `stream:217-20` | client-supplied history trusted | injection / context forgery | server history from DB (last 10) |
| P9 | Medium | `AiChatController` | `buildMessages:1201-02` | “created by {$user->name}” | false identity claim per user | static maker credit |
| P10 | Medium | `AiToolService` | `resolveMemberUser:908-22` | name-fragment first-match member | wrong person added + enumeration | exact-email-only |
| P11 | Medium | `AiToolService` + `AiChatController` | `openAiPayload:397`; `requestModel:221` | workout needs 16k in chat path but `requestModel` openai branch uses default budget | truncated 7-day plans in Import flow | set explicit `max_tokens 8000+` + `response_format` in `requestModel` |
| P12 | Medium | `AiActionController` | `confirmAll:40-48`, `MAX_OPEN=30` | batch takes 20, cap is 30 | 10 stranded pendings, user confusion | raise batch to 30 or page |
| P13 | Medium | `AiChatController` | `stream:230-35` | foreign `conversation_id` silently forks new convo | masks probing; state confusion | 403 on mismatch instead of fork |
| P14 | Medium | `PlannerController` | `aiOptimize:123-70` | labeled “Lina AI” but zero LLM | false capability, wrong feedback | rename or wire real call |
| P15 | Medium | `WorkoutImportService`/`AiChatController` | fallback creators | unprompted `WorkoutImport` rows + extra LLM call | surprise rows + spend | only on explicit intent signal |
| P16 | Low | `AiSettingsController` | `update:94-100` | masked-key skip via `••••` prefix sniff | key starting with `•` mishandled | compare suffix server-side |
| P17 | Low | `ai_providers` table | `is_default` col | written, never read (default lives in `ai_settings`) | dead field, confusion | remove or wire |
| P18 | Low | `AiChatController` | `chat:183-88` | errors as HTTP 200 text | clients can’t distinguish failure | 4xx/5xx + `{error}` schema |
| P19 | Low | `config/logging.php` | `68-73` | `ai` channel `single` driver | unbounded file; `tail()` full-read | `daily` + prune |
| P20 | Low | `NoteExportController` | `preview:47` | `est_tokens = chars/4` shown to users | rough for CJK/FA text (undercounts) | label “estimate” or per-script factor |

---

## 13. Optimization Roadmap

### MUST FIX BEFORE MVP (multi-user beta)

- P1 throttle + daily caps on chat/stream; P4 gate `.env` fallback; P5 `verify=>true`; P6 scope `/ai/debug`.
- P2 context budget + server-side history (last 10) — biggest cost lever.
- P3 DOMPurify + bundled `marked/hljs` (remove CDN gate).
- P8 history-from-DB (kills injection primitive).
- BYOK 3-step wizard (new-user dead-end is the #1 funnel loss).
- `ai_request_logs` table (§10) + `daily` log driver — without this, beta flies blind.
- Disable agent toggle when `agent_ready=false` (stop charging context for guaranteed blocks).

### SHOULD FIX SOON AFTER MVP

- P7 SSRF guard; P10 exact-email members; P11 workout token budget; P12 batch=30; P13 403-on-fork; S10/S11 row locks.
- Intent-routed tool subsets + split chat/agent system prompts (second-biggest token lever).
- Persist provider `usage` (tokens) per request; per-user “my usage” view.
- Stop/Retry/Edit-resend in chat; Undo ≤30s for delete/complete; structured (non-persisted) notices.
- Planner rename-or-wire (P14); consent-gated workout fallback (P15).
- Per-conversation model pin; Test buttons for built-ins; fix P9 identity string.

### FUTURE / SCALE

- Queue long parses/summaries (`ai:parse` jobs) + concurrency guards.
- Rolling conversation summaries; `ai_user_facts` memory (explicit, editable).
- Semantic note search to replace `LIKE` resolvers.
- Native Gemini/Anthropic function-calling (unlock agent for all providers).
- Scheduled briefings (“7am summary”) — needs scheduler + notification center.
- Shared team chats; admin cost dashboard; abuse ML.

---

## 14. AI System Audit Checklist (next phase — check off item by item)

**Security**
- [ ] A1 Throttle on `ai.chat`/`ai.stream` verified with HTTP test (429 on flood).
- [ ] A2 `.env` fallback disabled in prod; keyless user gets wizard, zero spend.
- [ ] A3 `marked` output passes through DOMPurify (attempt `<img onerror>` in history → inert).
- [ ] A4 Outbound TLS verify enabled (packet capture or `verify=>true` in code).
- [ ] A5 `/ai/debug` returns only own-user entries (test with 2 users).
- [ ] A6 Custom `base_url` to `169.254.169.254`/intranet rejected.
- [ ] A7 Forged `history` (fake assistant turn) has no effect on behavior.
- [ ] A8 `project_add_member` requires exact email; fuzzy name rejected.
- [ ] A9 Double-confirm race: 2 concurrent confirms → exactly 1 execution.
- [ ] A10 Plan `run_all` ×2 concurrently → phases execute once each, in order.

**Token & Cost**
- [ ] B1 Measure `context_chars` p50/p95 for 3 real users; record baseline.
- [ ] B2 Context budget enforced (≤6k chars) + cache hit logged.
- [ ] B3 AGENT system block split; chat-only requests exclude tool text (diff token count).
- [ ] B4 Tool definitions subset per intent (log `tool_names` count per request).
- [ ] B5 Provider `usage` persisted per request; sums match dashboard.
- [ ] B6 `emitToolMissedNotice` rate tracked; target <5% of agent requests.

**Correctness**
- [ ] C1 Stream reconnect mid-tool-call → single pending set (no dupes).
- [ ] C2 Foreign `conversation_id` → 403 (not silent fork).
- [ ] C3 Expired pending → `expired` + re-propose path works.
- [ ] C4 Plan partial failure → user sees exactly what was created.
- [ ] C5 Workout fallback creates rows only with explicit user signal.
- [ ] C6 Planner modal no longer claims LLM (or wired to one).

**Performance**
- [ ] D1 `buildContext` query count per message (target ≤7 queries, all limited).
- [ ] D2 p95 stream TTFB by provider recorded.
- [ ] D3 Long-reply render: no full `innerHTML` re-parse per chunk.
- [ ] D4 `/ai/debug` latency independent of log size.

**UX (manual pass, FA + EN, desktop + mobile)**
- [ ] E1 New keyless user reaches first successful reply in ≤3 steps.
- [ ] E2 Gemini/Claude user cannot enter dead-end agent flow.
- [ ] E3 Stop/Retry/Edit-resend all work on a live stream.
- [ ] E4 Confirm-all with 25 pendings → all 25 resolved, none stranded.
- [ ] E5 Undo works for AI-made delete/complete within window.
- [ ] E6 Mobile: composer usable, cards readable, no widget overlap.

**Observability**
- [ ] F1 Every chat/stream/note/workout call emits one `ai_request_logs` row.
- [ ] F2 Dashboard shows latency p50/p95, error rate, context distribution, confirm rate, req/user/day.
- [ ] F3 Prune job keeps tables bounded; verified after 30d simulation (or short TTL in staging).

---

*End of spec. All file:line claims verified Oct 2026. Open question for owners: keep assistant brand “Lina” or switch to per-user custom name (decision recorded, not implemented).*
