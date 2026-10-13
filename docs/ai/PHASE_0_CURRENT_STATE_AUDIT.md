# فاز ۰ — ممیزی وضع فعلی معماری AI (Task Manager)

> **تاریخ:** ۲۰۲۶-۱۰-۱۲ · **شاخه:** `feature/ai-edition`
> **روش جمع‌آوری شواهد:** مطالعه مستقیم کد (خواندن کامل فایل‌های کلیدی)، اجرای کامل تست‌ها (`php artisan test`)، تحلیل لاگ‌های واقعی `storage/logs/ai*.log`، و مقایسه ادعاهای مستندات موجود با کد.
> **محدودیت رعایت‌شده:** این گزارش صرفاً مشاهده است — هیچ کدی تغییر نکرده، هیچ جدولی ساخته نشده و هیچ معماری‌ای بازنویسی نشده است.
> هر ادعای «وضعیت: باز/بسته» بر اساس خودِ کد تأیید شده است. مواردی که قطعی قابل اثبات نبودند در بخش ۱۲ با برچسب **فرضیه** آمده‌اند.

---

## ۰. خلاصه اجرایی

**چه چیزهایی عملاً پیاده‌سازی شده‌اند (نه فقط طراحی):**

- یک خط لوله‌ی کامل و سمت‌سروری: `درخواست → Intent Router → Context Engine → انتخاب زیرمجموعه Tool → فراخوانی مدل → اعتبارسنجی چندمرحله‌ای → کارت تأیید → اجرای اتمیک → Undo محدود`.
- سه لایه‌ی کنترل مستقل روی هر فراخوانی ابزار: **visibility** (چه ابزاری به مدل نمایش داده شد)، **pipeline** (schema/security/business/ownership/state/policy)، و **تأیید کاربر** (هیچ ابزاری بدون کارت تأیید اجرا نمی‌شود؛ `AiToolService::requiresConfirmation` برای غیر-READ همیشه `true` است — `app/Services/AiToolService.php:128`).
- Idempotency واقعی با کلید پایدار `sha256(user|conversation|tool|canonicalArgs)` + قید یکتای دیتابیس + مدیریت برخورد کلید (`ToolPipeline.php:360-536`)؛ اجرای همزمان با `lockForUpdate` + claim اتمیک `pending→executing` (`AiActionController.php:289-410`).
- تست‌های گسترده: **۱۶ فایل، ~۱۹۷ تست AI** که همگی در اجرای کامل سبز هستند.

**چه چیزهایی هنوز ناقص/بازند (تأییدشده روی کد):**

1. **XSS مرورگری:** خروجی مدل با `marked.parse()` مستقیم به `innerHTML` می‌رود، بدون هیچ sanitizer (مانند DOMPurify) — `resources/views/ai/index.blade.php:838,1107-1108,1434` و `resources/views/layouts/_ai_chat.blade.php:~466,~528`.
2. **fallback کلید `.env`:** کاربر بدون کلید، بی‌سروصدا کلید سراسری را مصرف می‌کند (`AiProviderService.php:94-119`) — هزینه/امنیت BYOK.
3. **عضویت پروژه با `LIKE %name%` اولین تطابق** (`AiToolService.php:1200-1218`) — ریسک اضافه‌شدن شخص اشتباه + enumeration.
4. **هیچ متریک هزینه/توکن/تأخیری ذخیره نمی‌شود** — هیچ‌جا `usage`/`prompt_tokens` خوانده نمی‌شود (تأیید با grep).
5. **شکاف‌های مشاهده‌پذیری مهم** (بخش ۱۱): نبود رویداد پایانی برای مسیر stream، نبود `rid` روی پیشنهاد/اجرا، و آلودگی فایل لاگ به‌واسطه‌ی تست‌ها.
6. **مستندات موجود `docs/AI_SYSTEM.md` و `docs/AI_TECH_SPEC.md` در چندین بخش نسبت به کد عقب‌اند** (بخش ۱۳) — ادعاهایی مثل «بدون throttle»، «history از کلاینت»، «verify=false» دیگر درست نیستند.

---

## ۱. نقشه اجزا (فایل‌ها و نقش هرکدام)

| لایه | فایل | حجم | نقش |
|---|---|---|---|
| Route | `routes/web.php:195-233` | — | ۲۴+ مسیر AI داخل گروه `auth`؛ `throttle:15,1` روی chat/stream (`:201-202`)؛ `throttle:30,1` روی confirm/reject/choice/undo/plans (`:205-225`) |
| کنترلر چت | `app/Http/Controllers/AiChatController.php` | ۲۰۲۷ | `chat()` (:127)، `stream()` (:210)، ساخت pending (:594)، capability routing (:777)، SSE stream (:1383)، system prompt (:1877)، debug (:1993) |
| کنترلر اکشن | `app/Http/Controllers/AiActionController.php` | ۴۳۸ | `confirm` (:22)، `undo` (:45)، `confirmAll` (:99)، `rejectAll` (:146)، `choice` (:196)، `runAction` (:289)، `reject` (:414) |
| کنترلر پلن | `app/Http/Controllers/AiPlanController.php` | ۲۳۸ | `confirmStructure` (:22)، `confirmPhase` (:58)، `cancel` (:130) |
| Context Engine | `app/Services/AiContextEngine.php` | ۷۴۷ | ساخت بودجه‌بندی‌شده‌ی context + تاریخچه سمت‌سرور |
| Intent Router | `app/Services/AiIntentRouter.php` | ۴۳۴ | طبقه‌بندی قاعده‌ای intent (فارسی/انگلیسی)، advisory |
| Capability Router | `app/Services/AiTooling/ToolCapabilityRouter.php` | ۲۴۰ | انتخاب زیرمجموعه‌ی tool بر اساس intent+context+provider |
| Registry | `app/Services/AiTooling/ToolCapabilityRegistry.php` | ۳۳۷ | نقشه دسته↔ابزار، `SAFE_FALLBACK`، اعتبارسنجی سازگاری |
| Pipeline | `app/Services/AiTooling/ToolPipeline.php` | ۶۲۰ | ۸ مرحله اعتبارسنجی، idempotency، turn guard، نتیجه‌ی ساخت‌یافته |
| Schema | `app/Services/AiTooling/ToolSchema.php` | ۴۰۶ | شکل آرگومان‌ها، سقف ۴۸KB/عمق ۱۲ |
| Tool Service | `app/Services/AiToolService.php` | ۳۴۰۵ | ۲۴ ابزار: definitions (:139)، validate (:536)، preview (:586)، execute (:625)، فازهای پلن (:2301,2345) |
| Security | `app/Services/AiSecurity.php` | ۲۱۰ | کلیدهای ممنوع (:23)، گارد SSRF (:103)، نشانه‌ی untrusted (:202) |
| Provider | `app/Services/AiProviderService.php` | ۵۱۱ | resolve (:174)، کلید (:94)، transport (:337)، payload (:438) |
| Logger | `app/Services/AiLogger.php` | ۱۹۹ | کانال `ai`، rid، sanitize، tail per-user (:163) |
| Undo | `app/Services/AiUndoService.php` | ۱۸۶ | فقط ۴ ابزار برگشت‌پذیر، پنجره ۱۰ دقیقه |
| Offline | `app/Services/LinaFallbackBrain.php` | ۵۰۱ | حالت بدون پروایدر — **فقط‌خواندنی (تأیید شد: هیچ write ندارد)** |
| مدل‌ها | `AiConversation, AiMessage, AiPendingAction, AiPlan, AiSetting, AiProvider` | — | بخش ۸ |
| Cron | `routes/console.php:12` + `app/Console/Commands/PruneAiPendings.php` | ۲۹ | `ai:prune-pendings` روزانه: انقضا + پاک‌سازی ردیف‌های terminal با retention ۳۰ روز |
| UI | `resources/views/ai/index.blade.php` (۱۹۴۷)، `layouts/_ai_chat.blade.php` (۷۰۲) | — | SSE، کارت‌ها، dock تأیید (بازیابی از `/ai/debug` + پولینگ ۲۵ ثانیه‌ای — `index.blade.php:1172,1942-1944`) |

---

## ۲. مسیر کامل درخواست: از دریافت تا اجرای Tool

### ۲.۱ ورودی و پیش‌پردازش (`POST /ai/stream` — مسیر اصلی UI)

1. `routes/web.php:202` → `throttle:15,1` + `auth` → `AiChatController::stream` (`:210`).
2. اعتبارسنجی: پیام ≤۸۰۰۰ کاراکتر، `history ≤ 40`، `mode ∈ {chat,agent}`، پیوست تا ۵ نوت/۵ فایل. خطا → **422 JSON** (هرگز redirect، چون fetch دنبال redirect می‌رود).
3. تولید `rid` (`AiLogger::newRequestId()` — پیشوند `ai_`) و رویداد `request.received` با `message_preview ≤300`.
4. `AiIntentRouter::route($message)` (`AiIntentRouter.php:57`) — **قبل از** ساخت context و toolها.
5. `resolve($user)` (`AiProviderService.php:174`) — ترتیب: پیش‌فرض کاربر → پروایدر فعال → fallback `.env`.

### ۲.۲ Intent Router (فقط مشورتی، هرگز مجوز نیست)

- ۷ intent با اولویت تطبیق: `workout_plan → plan_build → report → task_mutation → workspace_query → general_chat → ambiguous` (`AiIntentRouter.php:57-104`).
- سه سطح اعتماد: `high` (باریک‌سازی)، `medium` (**fail-open مجموعه کامل**)، `low` (مجموعه امن فقط‌خواندنی `SAFE_FALLBACK` + سؤال شفاف‌سازی).
- خروجی: `intent, confidence, entities(objects/date/list/question), context_profile, tool_filter` + رویداد `intent.routed`.
- تأثیر واقعی: انتخاب **پروفایل context** (`profileFor:109` — فقط `minimal|chat|agent`) و **زیرمجموعه tool** (از مسیر `ToolCapabilityRouter::select` — نه `toolsFor`؛ بخش ۱۳).

### ۲.۳ مکالمه و ذخیره پیام

- `conversation_id` داده‌شده → جستجو با `where user_id`؛ پیدا نشد → **403 با کد `forbidden_conversation`** (دیگر fork بی‌صدای IDOR نیست — `AiChatController` انتهای `stream`).
- بدون `conversation_id` → ساخت مکالمه‌ی جدید؛ پیام کاربر فوراً در `ai_messages` ذخیره و در صورت اولین پیام، برچسب خودکار (۶۰ کاراکتر).
- بدون پروایدر → `LinaFallbackBrain` با SSE قطعه‌قطعه؛ پیام با مدل `lina-offline` ذخیره می‌شود.

### ۲.۴ Context Engine (`AiContextEngine::build:108`)

```
پروفایل (minimal/chat/agent) → keywords + cues → snapshot کش‌شده (۶۰ ثانیه)
→ تطبیق پروژه → بازیابی مرتبط (tasks/reminders/routines/notes/files)
→ پیوست‌های صریح (ownership-check) → تاریخچه سمت‌سرور
→ مونتاژ خط‌به‌خط با بودجه کاراکتری (اولویت: global < attachments < projects < tasks < …)
→ SUMary همیشه نگه داشته می‌شود؛ بقیه whole-line حذف/تریم می‌شوند
```

- بودجه‌ها (`PROFILES:47`): minimal 800 / chat 4000 / agent 6000 کاراکتر؛ تاریخچه ۴-۱۰ پیام با سقف ۸۰۰ کاراکتر هر پیام (`HISTORY_PER_MESSAGE_CHARS:41`, `loadHistory:558`).
- intent عمومی → `skip_workspace=true` (صفر کوئری ورک‌اسپیس).
- تاریخچه **فقط از `ai_messages`**، با `exclude_message_id` برای پیام جاری؛ هیچ history از کلاینت استفاده نمی‌شود (در `chat()` و `stream()` شمارش آن فقط لاگ می‌شود: `request.client_history_ignored`).
- همه‌ی خطوط داده‌ی کاربر با `AiSecurity::markUntrusted` علامت می‌خورند (`AiContextEngine.php:614-712`).

### ۲.۵ ساخت پیام مدل (`buildMessages:1877`)

- System: هویت «Lina» + تاریخ + دستور زبان (fa/en) + بلوک `MODE: AGENT` یا `MODE: CHAT` + `ROUTER HINT` (فقط مشورتی) + داده‌ی ورک‌اسپیس بین `--- UNTRUSTED ---`.
- بلوک AGENT شامل قواعد رفتاری صریح است: search-before-create، `ask_user_choice` در ابهام، حداقل ساختار جدید، نقشه‌ی درخواست→ساختار، سقف‌های پلن، و «شما فقط پیشنهاد می‌دهید، هرگز مجاز نمی‌کنید» (`:1885-1935`).

### ۲.۶ انتخاب و ارسال Toolها (`toolsForResolved:777`)

- فقط `type == openai` و فقط در agent mode tool ارسال می‌شود؛ chat mode → `[]` (هیچ tool).
- `ToolCapabilityRouter::select(:63)`: general_high → هیچ؛ ambiguous/low → `SAFE_FALLBACK` (`report_generate, workspace_search, ask_user_choice`)؛ medium → null (مجموعه کامل)؛ query/report → دسته‌های `report,search,question`؛ workout → فقط `workout_plan_propose`؛ plan → fail-open؛ mutation → اجتماع دسته‌های entity-محور + همیشه `report,search,question`.
- رویداد `tools.routed` با مقایسه‌ی توکن تخمینی (chars/4).
- **مجوز نیست — دید است:** لیست اسامی ارسال‌شده (`$allowedNames`) هم به payload مدل می‌رود و هم به‌عنوان allowlist ورودی استفاده می‌شود: هر فراخوانی مدل خارج از آن با کد `tool_not_routed` رد و نمایان می‌شود (`AiChatController` در sync path و `emitToolProposals` در stream path؛ لایه‌ی سوم `ToolPipeline::validateForProposal:48` با `not_allowed_for_intent`).

### ۲.۷ جمع‌آوری tool_calls و تبدیل به «اکشن معلق»

- مسیر stream: خواندن SSE با انباشتن `tool_calls` بر اساس index؛ در `[DONE]` → `emitToolProposals` (`AiChatController.php:1760` به بعد).
- Turn guard (`ToolPipeline::filterTurnCalls:542`): سقف **۲۰ فراخوانی در نوبت** و حذف تکرارهای یکسان بیش از **۳** با کد `loop_detected` (حذف‌شده‌ها به‌صورت کارت خطا نمایش داده می‌شوند؛ هرگز بی‌صدا).
- `createPendingFromToolCall` (`:594`):
  1. `ToolSchema::envelope` (شکل/اندازه، سقف ۴۸KB).
  2. `plan_propose` → مسیر جدا (`createPlanFromToolCall`، سقف ۳ پلن باز)؛ `workout_plan_propose` → ساخت `WorkoutImport(PREVIEW)` با عمر ۳۰ دقیقه.
  3. سقف **۳۰ اکشن معلق** (`AiPendingAction::MAX_OPEN:58`) → خطا با کد `too_many_pending` + لیست موجود.
  4. `ToolPipeline::validateForProposal(:48)` — ۸ مرحله: ناشناخته → allowlist دید → envelope → **securityScreen** (کلیدهای `user_id/policy/auto_confirm/...` ممنوع، `AiSecurity.php:23`) → `normalizeArgs` (aliasها و حذف identity) → `ToolSchema::check` → اعتبارسنجی کسب‌وکار `validateCall(:536)` → **ownership مستقل** (`checkOwnership:208`) → **state** (`checkState:288`) → **policy** (`checkPolicy:320`).
  5. Dedupe: کلید زنده موجود → `deduped:true`؛ اجرای‌شده‌ی اخیر → `already_executed`؛ برخورد کلید → چرخش (`resolvePendingKeyCollision:464`)؛ درج با ۳ تلاش (`insertPendingIdempotent:723`).
  6. ساخت `AiPendingAction{pending, +15min, idempotency_key, args, preview}` + رویداد `tool.proposed` → بسته‌ی SSE `tool_proposal`.

### ۲.۸ تأیید و اجرای نهایی (`runAction:289`)

```
DB::transaction
 ├ SELECT … FOR UPDATE روی ردیف
 ├ مالکیت خود action + مالکیت conversation مرتبط (403)
 ├ executed/executing → dedupe (هرگز اجرای دوباره)
 ├ منقضی/لغوشده/ناموفق → کدهای ساخت‌یافته expired/cancelled/execution_error
 ├ ToolPipeline::revalidateForExecute(:128) — همان مراحل با خوانش تازه
 ├ claim اتمیک: UPDATE … WHERE status='pending' → executing (برنده دقیقاً یکی)
 ├ AiUndoService.capture (قبل از اجرا، فقط ۴ ابزار برگشت‌پذیر)
 ├ AiToolService::execute(:625) داخل DB::transaction — نتیجه‌ی ساخت‌یافته ok/code/message
 ├ استثنا → markFailed با دلیل ( Zombies claim نمی‌ماند )
 ├ executed + undo_data + رویداد action.executed + پیام '✅' در مکالمه
 └ پاسخ {ok, message, undoable?, undo_expires_at?}
```

- **Undo** (`undo:45`): پنجره ۱۰ دقیقه (`UNDO_WINDOW_MINUTES:53`)، فقط `task_complete/reminder_complete/routine_complete/task_update` (`AiUndoService.php:26`)، یک‌بار، با قفل سطر؛ پیام `↩`.
- **choice** (`choice:196`): قفل → مالکیت → فقط `ask_user_choice` → مصرف اتمیک (`pending→rejected`) → گزینه انتخاب‌شده از **همان مسیر عادی پیشنهاد** عبور می‌کند (schema/security/business/ownership/policy) و کارت تأیید خودش را می‌گیرد — گزینه هرگز مستقیم اجرا نمی‌شود.
- **confirmAll/rejectAll** (`:99/:146`): تا `MAX_OPEN=30`، قدیمی‌ترین اول؛ هر ردیف مستقل؛ flip شرطی تا ردیف‌های اجراشده دست‌نخورده بمانند.

### ۲.۹ پلن‌های ساختاری (`AiPlan`)

- پیشنهاد: `plan_propose` → اعتبارسنجی سقف‌ها (۵ پروژه، ۳ زیرپروژه، ۳۰ تسک، ۱۰۰ زیرتسک، ۱۰ یادآور، ۱۰ نوت، ۷ روتین — `AiPlan.php:21-26`، مجموع کل ≤۲۰۰ در `checkPolicy:320`) → `buildPlanPhases(:2301)` (projects→subprojects→tasks→subtasks→reminders→notes؛ فازهای صفرتایی از ابتدا `done`) → کلید پایدار از title+structure.
- `confirm-structure` (`:22`): قفل سطر، `proposed→confirmed`، **هیچ چیزی ساخته نمی‌شود** تا فازها اجرا شوند، تمدید انقضا.
- `confirm-phase` (`:58`): تطبیق `phase` با اشاره‌گر سرور (کلیک دوباره‌ی کهنه dedupe می‌شود)، حلقه‌ی `run_all` با پرش فازهای خالی؛ شکست فاز → 422 + فهرست پیشرفت انجام‌شده (بدون rollback، صریحاً اعلام‌شده).
- `cancel` (`:130`): فقط غیر-terminal؛ آیتم‌های ساخته‌شده می‌مانند.

---

## ۳. جست‌وجو و انتخاب Entity

| مکانیزم | محل | رفتار | ضعف مشاهده‌شده |
|---|---|---|---|
| تسک با عنوان | `AiToolService::resolveTask:1400` | id (اولویت اول) ← تطابق دقیق عنوان ← `LIKE`؛ محدوده با `project/project_id`؛ ابهام → خطای `conflict` با فهرست کاندیداها | نقطه قوت؛ اما `LIKE %needle%` از ایندکس استفاده نمی‌کند (performance — فرضیه در باب بزرگی دیتا) |
| روتین | `resolveRoutine:1463` | `routine_id` ← اولین `LIKE %title%` | **ابهام را چک نمی‌کند** — با دو روتین شبیه، اولین ردیف بی‌صدا انتخاب می‌شود (شکل‌گرفته از کد؛ مقایسه با resolveTask که CONFLICT می‌دهد) |
| پروژه در context | `AiContextEngine::matchProject:353` | substring دوطرفه + امتیاز overlap کلمات (stem ۴ حرفی) | صرفاً advisory است (فقط برای اولویت‌بندی context) |
| پروژه در fallback تسک | `AiChatController::resolveProjectForTaskFallback` | overlap کلمات با stem | می‌تواند پروژه‌ی نادرست را انتخاب کند — **فرضیه** |
| جست‌وجوی سراسری | `execWorkspaceSearch:2161` | نرمال‌سازی فارسی (عربی→فارسی، ZWNJ) + امتیازدهی در PHP، سقف ۴۰۰/۲۰۰ ردیف هر نوع، scoped به user | READ-only و بعد از اجرا به مدل پیام می‌دهد «نیست → نساز، بپرس» |
| پیوست پلن به پروژه‌ی موجود | `validatePlanPropose:1582` (نام → id) + فاز اجرا با re-check | search-before-create را عملیاتی می‌کند؛ تست‌های `AiSearchBeforeCreateTest` پوشش داده‌اند | — |
| عضو پروژه | `resolveMemberUser:1200` | عددی → id؛ شامل `@` → email دقیق؛ وگرنه `LIKE %name%` اولین تطابق | **ریسک شخص اشتباه + enumeration** (باز) |

---

## ۴. Toolها — طبقه‌بندی و سیاست تأیید

- **۲۴ ابزار** (`AiToolService::TOOLS:24`): ۲۰ نوشتنی + `report_generate` و `workspace_search` فقط‌خواندنی + `plan_propose`/`workout_propose` که خودشان اجرا نمی‌شوند (پلن فازبه‌فاز؛ ورک‌اوت از مسیر import preview).
- `TOOL_RISK` (`:78`): WRITE / DESTRUCTIVE / SENSITIVE — این نقشه سمت‌سرور است و مدل هرگز به آن دسترسی ندارد؛ هیچ ابزاری argument مربوط به permission/policy نمی‌پذیرد (`AiSecurity::FORBIDDEN_ARG_KEYS`).
- **هر ابزار غیر-READ نیازمند تأیید است** (`requiresConfirmation:128`) — استثنا ندارد و تست دارد (`AiConfirmationTest::test_policy_read_needs_no_confirmation_but_destructive_sensitive_always_do`).
- هشدارهای اثر در preview: delete ها impact-پیش‌نمایش دارند (`previewTaskDelete:2655`, `previewRoutineDelete:2668`).

---

## ۵. حفظ انتخاب‌ها و اصلاحات کاربر در مکالمات چندمرحله‌ای

**مکانیسم‌های موجود (تأییدشده):**

1. **تاریخچه سمت‌سرور:** هر نوبت از `ai_messages` خوانده می‌شود (`loadHistory:558`) با سقف‌های پروفایل (agent: ۱۰ پیام / ۴۰۰۰ کاراکتر / هر پیام حداکثر ۸۰۰ کاراکتر). history کلاینت نادیده گرفته و شمارشش لاگ می‌شود.
2. **وضعیت اکشن‌های معلق پایدار است:** `ai_pending_actions.args` همه‌ی پارامترهای تأییدنشده را نگه می‌دارد؛ رفرش صفحه یا reload مرورگر با `/ai/debug` کارت‌ها را برمی‌گرداند (`index.blade.php:1172`) و هر ۲۵ ثانیه با `setInterval` همگام می‌شود (`:1944`) — یعنی تأیید از تب/دستگاه دیگر هم دیده می‌شود.
3. **پاسخ به ابهام (clarification):** `ask_user_choice` به‌صورت pending ذخیره می‌شود؛ انتخاب کاربر → پیام `↪ برچسب گزینه` در مکالمه + پیشنهاد جدید از همان pipeline. بنابراین انتخاب کاربر در تاریخچه می‌ماند.
4. **بازخورد اجرای اکشن‌ها:** هر اجرای موفق پیام `✅ …`، هر undo پیام `↩ …`، و هر فاز پلن `📋/✅/⚠ …` به‌عنوان پیام assistant در همان مکالمه ثبت می‌شود (`AiActionController:380`, `AiPlanController:note:180`) — تاریخچه دقیقاً نشان می‌دهد چه چیزی ساخته/لغو شده.
5. **اصلاح پلن:** system prompt صریحاً می‌گوید ویرایش پلن = ارسال دوباره‌ی `plan_propose` با ساختار اصلاح‌شده (`AiChatController:~1910`). کلید پلن از title+structure مشتق می‌شود، پس ساختار جدید = پلن جدید؛ پلن قدیمی خودکار cancel نمی‌شود (کاربر باید دستی لغو کند — سقف ۳ پلن باز).
6. **پاسخ‌های tool-only:** اگر مدل فقط tool برگرداند و متنی نداشته باشد، در پایان stream یک خلاصه‌ی فارسی هاردکد به‌عنوان پیام assistant ذخیره می‌شود تا تاریخچه خالی نماند (`AiChatController:1708-1716` «✅ N تسک برای تایید آماده شد…»).
7. **Undo پس از اجرا:** دکمه‌ی Undo فقط در همان کارت نمایش داده می‌شود (`index.blade.php:1439-1454`)، اما از سمت سرور تا ۱۰ دقیقه از هر مکان با `action_id` قابل فراخوانی است.

**شکاف‌ها/محدودیت‌های مشاهده‌شده:**

- **`POST /ai/chat` هیچ مکالمه‌ای ندارد** (کامنت کد: «server history is empty by design» — `AiChatController:164`)؛ هر درخواست عملاً بدون تاریخچه است. اگر clientی روی این endpoint تکیه کند، اصلاحات نوبت‌های قبل از دست می‌رود.
- **برش ۸۰۰ کاراکتر هر پیام:** یک اصلاح بلند (مثلاً لیست ۲۰تایی تسک) در تاریخچه نصفه می‌ماند؛ مدل فقط ابتدای آن را می‌بیند (کد تأیید شد؛ شدت اثر **فرضیه**).
- **ریکانکت/retry:** پیام کاربر قبل از فراخوانی مدل ذخیره می‌شود؛ retry پس از قطعی یک پیام کاربری دوم می‌سازد و پیشنهادهای دوم هم با کلید idempotency هم‌پوشانی دارند **فقط وقتی conversation یکسان باشد**؛ fallbackهای سمت سرور (`buildTaskCreationFallbackProposals:1116`) با `conversationId=null` فراخوانی می‌شوند (کلید `none`) — یعنی dedupe آن‌ها به همراهی args و user وابسته است نه مکالمه.
- **پلن قدیمیِ جایگزین‌شده خودکار لغو نمی‌شود** (کد تأیید شد) — تا سقف ۳، سپس کاربر با «Too many open plans» مواجه می‌شود؛ رفتار UX آن **فرضیه** است.
- پیام‌های system/assistantِ هاردکد فارسی (خلاصه‌ی tool-only، پیام‌های خطا) در مکالمه‌ی کاربران انگلیسی‌زبان هم فارسی ثبت می‌شوند (کد: رشته‌های ثبت `✅/⚠/«تسک‌ها…»` بدون ترجمه).

---

## ۶. تست‌ها، نقاط ضعف، مشکلات امنیتی

### ۶.۱ تست‌ها

- **۱۶ فایل AI با ~۱۹۷ تست** که پوشش می‌دهند: confirmation/دهانه‌ی race (`AiConfirmationTest`، ۲۳)، idempotency (`AiIdempotencyTest`، ۱۶)، مرز امنیت (`AiSecurityBoundaryTest`، ۱۸)، capability routing (`AiToolCapabilityRoutingTest`، ۱۹)، اعتبارسنجی ابزار (`AiToolValidationTest`، ۱۵)، Context Engine (۱۵)، Intent Router (۲۱)، resolve عنوانی (۹)، پلن (۱۱)، search/choice (۸+۱۲)، مدها، صفحه، چندپروژه‌ای، همکاری.
- **نتیجه‌ی اجرای کامل (۲۰۲۶-۱۰-۱۲):** `Tests: 2 failed, 447 passed (2640 assertions)` — دو شکست **غیر-AI**: `NoteEditorTest > create and edit pages ship the self hosted editor…` و `MorningCheckinPromptTest > modal hidden outside window` (تست وابسته به بازه‌ی زمانی روز). هیچ تست AI شکست نخورده است.
- گپ‌های تست (مشاهده، بدون قضاوت اولویت): تستی برای sanitizer مرورگری وجود ندارد (چون منطق در Blade/JS است)؛ تست همزمانی واقعی (دو request موازی) شبیه‌سازی‌شده با claim اتمیک است نه threads واقعی؛ تستی برای همبستگی `rid` در لاگ‌ها نیست؛ تستی برای ابهام `resolveRoutine` با دو عنوان شبیه نیست.

### ۶.۲ مشکلات امنیتی — وضعیت باز/بسته (تأییدشده روی کد)

| وضعیت | موضوع | شاهد |
|---|---|---|
| ✅ بسته | قفل سطر + claim اتمیک برای double-confirm و فاز پلن | `AiActionController.php:289-410` (`lockForUpdate`, `UPDATE…WHERE status='pending'`)؛ `AiPlanController.php:22-127` |
| ✅ بسته | throttle روی chat/stream | `routes/web.php:201-202` (`throttle:15,1`) |
| ✅ بسته | history سمت‌سرور؛ history کلاینت هرگز trust نمی‌شود | `AiContextEngine::loadHistory:558`؛ لاگ `request.client_history_ignored` |
| ✅ بسته | 403 برای conversation بیگانه (به‌جای fork بی‌صدا) | `AiChatController::stream` — رویداد `request.forbidden_conversation` |
| ✅ بسته | TLS verification اجباری (پیش‌فرض true، config-gated) | `AiProviderService.php:334-353`، `AiChatController.php:1394`؛ تست `test_outbound_tls_verification_is_mandatory` |
| ✅ بسته | SSRF روی base_url پروایدر سفارشی | `AiSecurity::blockReasonForProviderUrl:103` + `AiProviderController:86` (هر store و update)؛ تست `test_ssrf_guard_blocks_internal_urls` |
| ✅ بسته | نشت لاگ بین کاربران در `/ai/debug` | `AiLogger::tailForUser:163` با fail-closed (خطوط بدون `user_id` حذف)؛ تست `test_debug_returns_only_own_log_entries` |
| ✅ بسته | کلیدها: cast `encrypted`، هرگز در پاسخ/لاگ (`sanitize:63` با scrub متنی `Bearer/sk-`) | `AiSetting.php:31-36`، `AiProvider.php:26`، تست `test_api_keys_never_land_in_logs` |
| ✅ بسته | رد شدن کلیدهای هویتی/سطح‌مسلط (`user_id`, `policy`, `auto_confirm`, …) | `AiSecurity::FORBIDDEN_ARG_KEYS:23` + مرحله‌ی جدا در pipeline (`ToolPipeline::securityScreen:186`) |
| ⚠️ **باز** | **XSS: `marked.parse()` → `innerHTML` بدون sanitizer** در هر دو سطح چت | `index.blade.php:838,1107-1108,1434`؛ `_ai_chat.blade.php:~466,~528` — هیچ `DOMPurify` در پروژه نیست (grep صفر) |
| ⚠️ **باز** | **fallback کلید `.env`** — کاربر بدون کلید، کلید سراسری را مصرف می‌کند | `AiProviderService::getKey:94-119` |
| ⚠️ **باز** | **عضو پروژه با `LIKE %name%` اولین تطابق** | `AiToolService::resolveMemberUser:1200-1218` |
| ⚠️ **باز** | **بدون سقف روزانه/هزینه و بدون metering توکن**؛ ۱۵ درخواست در دقیقه تنها سقف است و هیچ‌جا `usage` خوانده نمی‌شود | `routes/web.php:201-202`؛ grep `prompt_tokens|usage` در app: صفر |
| 🟡 نسبی | mask کلید: حالا با مقایسه‌ی `maskedValue` ذخیره‌شده هم همراه است اما `str_starts_with('••••')` هنوز هست | `AiSettingsController.php:100-105` |
| 🟡 طراحی فعلی | prompt-injection: داده‌ی ورک‌اسپیس صریحاً `UNTRUSTED` علامت می‌خورد (سطح prompt)؛ مرحله‌ی واقعی ممانعت، ownership+تأیید سمت‌سرور است | `AiSecurity::markUntrusted:202`، `AiContextEngine:614-712`، بلوک امنیتی system prompt `AiChatController:1887-1935` |
| 🟡 ضعف خفیف | `emitToolMissedNotice` می‌تواند روی مثال کدِ پیست‌شده false-positive دهد (به کاربر می‌گوید «هیچی ساخته نشد») | `AiChatController::emitToolMissedNotice:1858` |

---

## ۷. مدل‌ها، جداول، روابط و وضعیت درخواست‌ها

| جدول | ساخت (migration) | کلیدها | وضعیت‌ها/قوانین |
|---|---|---|---|
| `ai_conversations` + `ai_messages` | `2026_05_04_000001` | FK user (cascade)، FK conversation (cascade)؛ `messages.role ∈ {user,assistant}` | — (ستون `model` فقط در پیام assistant) |
| `ai_settings` | `2026_08_12_000001` | `user_id unique`؛ ۵ کلید `encrypted` | ترتیب resolve |
| `ai_providers` | `2026_08_12_000002` | `user_id,sort_order`؛ `api_key encrypted` | `enabled`, `is_default` — **ولی `is_default` هیچ‌جا خوانده نمی‌شود** (فقط fillable/casts؛ رفتار default از `ai_settings` می‌آید) |
| `ai_pending_actions` | `2026_09_24_000001` + `2026_10_08_000001` | `idempotency_key UNIQUE`؛ ایندکس `(user_id,status)`, `(user_id,expires_at)`؛ ستون‌های `error, undo_data, undone_at` بعداً اضافه | `pending → executing → executed/failed`؛ `pending → rejected/expired`؛ terminal: executed/failed/rejected/expired (`AiPendingAction.php:24-49`)؛ عمر ۱۵ دقیقه (`:50`)؛ undo ۱۰ دقیقه (`:53`)؛ سقف ۳۰ باز (`:58`) |
| `ai_plans` | `2026_09_24_000002` | `idempotency_key UNIQUE`، ایندکس `(user_id,status)` | `proposed → confirmed → executing → done`؛ `cancelled`؛ `expired`؛ عمر ۱۵ دقیقه (`AiPlan.php:17`) |
| `workout_imports` (غیر-_AI اما خروجی مسیر AI) | — | `status=PREVIEW`، عمر ۳۰ دقیقه | سقف ۳ پیش‌نمایش باز |

- روابط: `AiConversation::messages()` (ordered by created_at)، `AiPendingAction/AiPlan::conversation()` و `::user()`؛ اکشن‌های معلق و پلن‌ها عمودی به کاربر و اختیاری به مکالمه وصل‌اند (حذف مکالمه cascade آن‌ها را هم می‌برَد).
- نگهداری: `ai:prune-pendings` روزانه (`routes/console.php:12`) — پلن‌های منقضی را expired و ردیف‌های terminal قدیمی‌تر از ۳۰ روز را حذف می‌کند. **هیچ prune برای `ai_messages`/`ai_plans` وجود ندارد** (مشاهده؛ آیا لازم است = **فرضیه** بسته به رشد).

---

## ۸. مقایسه‌ی لاگ‌های موجود با جریان واقعی کد (شکاف‌های مشاهده‌پذیری)

**وضعیت فایزیکی لاگ‌ها:** `storage/logs/ai-2026-10-08.log`، `ai-2026-10-10.log`، `ai-2026-10-11.log`، `ai.log` (legacy) — در مجموع **۵۷۷۴ خط**. کانال `ai` با driver `daily` و `days=14` (`config/logging.php:68-73`).

**رویدادهایی که واقعاً در لاگ ثبت شده‌اند (شمارش روی همه‌ی فایل‌ها):**

```
132 stream.proposal_emitted      128 tool.proposed          84 action.executed
 43 stream.openai_start          43  request.received        28 stream.tool_calls_received
 18 context.built                15  stream.no_tool_calls    14 action.confirm_all
 13 intent.routed                12  stream.text_only_no_tools
  7 plan.proposed                7   ERROR stream.fallback_provider
  5 tool.proposal_capped / plan.structure_confirmed / plan.phase_executed
  4 tool.proposal_invalid        2   task.fallback_created / plan.proposal_invalid / action.reject_all
  1 request.invalid / action.rejected / action.confirm_expired
```

**تطبیق با کد و شکاف‌های مشاهده‌پذیری:**

1. **رویداد پایانی برای مسیر stream وجود ندارد.** `request.completed` و `request.resolved` فقط در `chat()` ثبت می‌شوند (`AiChatController.php:155,183`) — یعنی در لاگ‌های واقعی فقط با پیشوند `testing.` (اجرای تست) دیده می‌شوند، چون UI عملاً همیشه از `/ai/stream` استفاده می‌کند. **نتیجه: برای هیچ درخواست production یک رویداد «پایان موفق با تعداد proposal/مدت» ثبت نمی‌شود.**
2. **زنجیره‌ی `rid` ناقص است.** `request.received/intent.routed/context.built/stream.*` دارای `rid` هستند، اما `tool.proposed` (۱۲۸ خط)، `action.executed` (۸۴)، `action.confirm_all` (۱۴)، `plan.*` — همگی **بدون `rid`**. ستونی به نام `request_id` هم در `ai_pending_actions` نیست؛ پس ارتباط «درخواست ← پیشنهاد ← اجرا» از طریق لاگ قابل بازسازی نیست (فقط با `action_id` بخش اجرا).
3. **رویدادهایی که `user_id` ندارند از دید `/ai/debug` کاربر خارج‌اند:** `intent.routed` (فقط rid)، `tools.routed` (نه user_id نه rid) — چون `tailForUser` خطوط بدون `user_id` را رد می‌کند (fail-closed, درست) این رویدادها هرگز در دیباگ داخلی دیده نمی‌شوند.
4. **تست‌ها در همان فایل لاگ production می‌نویسند:** خطوط `testing.INFO` در `ai-2026-10-08.log` و `ai.log` با `local.` قاطی‌اند (کانال `ai` مسیر ثابت دارد و تست‌ها `LOG_CHANNEL`/مسیر را override نمی‌کنند — `phpunit.xml` فقط `APP_ENV/DB_*` را ست می‌کند). این سیگنال عملیاتی را آلوده می‌کند.
5. **هیچ متریک کمّی وجود ندارد:** در هیچ رویدادی `latency_ms`، توکن ورودی/خروجی، هزینه، یا `http_status` مسیر موفق نیست. `context.built` حداقل `workspace_chars/history_count` دارد (نقطه قوت).
6. **پراکندگی بین دو فایل:** بخشی از رویدادها علاوه بر کانال `ai` با `Log::info('ai.tool.proposed'…)` در `laravel.log` هم ثبت می‌شوند (مثلاً `AiChatController:683`، `AiActionController:391`) — برای همبستگی باید دو فایل را هم زد.
7. **پیش‌نمایش پیام کاربر در لاگ PII است** (تا ۳۰۰ کاراکتر) — فیلتر per-user فقط هنگام نمایش اعمال می‌شود؛ خودِ فایل متن ساده است و ۱۴ روز نگه داشته می‌شود.
8. **نکات مثبت تأییدشده:** sanitize کلیدها (`AiLogger:63`)، لاگ‌های خطای ساخت‌یافته (`error_type=provider|system`)، و ثبت دقیق دلایل رد ابزار (`tool.not_routed`, `tool.capability_denied`, `tool.security_denied`, `tool.loop_dropped`).

---

## ۹. وضعیت «اکشن معلق» از دید کاربر (جریان عملی)

1. مدل tool_call می‌دهد → کارت در چت + نوار dock پایین (`linaDock`) با شمارنده.
2. رفرش صفحه: `/ai/debug` → `recent_pending_actions` (فقط statusهای زنده فیلتر می‌شوند — `index.blade.php:1177`) + پولینگ هر ۲۵ ثانیه.
3. تأیید تکی/همه → اجرای سمت‌سرور؛ خطاها با کدهای ماشین‌خوان (`expired_action`, `conflict`, `not_found`, … — `ToolError.php:14-65`) برمی‌گردند نه فقط متن آزاد.
4. لغو → `rejected` با flip شرطی؛ اجرای همزمان هرگز به عقب برنمی‌گردد.
5. انقضا: پس از ۱۵ دقیقه confirm با کد `expired_action` رد می‌شود؛ cron روزانه ردیف‌ها را `expired` و پاک می‌کند.

---

## ۱۰. نقاط ضعف معماری (فهرست مشاهده‌شده، بدون راه‌حل)

1. **`AiChatController.php` حدود ۲۰۲۷ خط و `AiToolService.php` ۳۴۰۵ خط** — منطق جریان stream، fallbackها، intent-به-متن و پیام system در یک کنترلر است؛ تغییر در هر بخش ریسک regression بالا دارد (تست‌ها پوشش خوبی دارند اما ساختار شکننده است).
2. **سه رشته‌ی موازی «تشخیص قصد» بدون مرجع واحد:** `AiIntentRouter` (intentها)، هوش متنی داخل system prompt (ده‌ها قاعده‌ی دستی)، و fallbackهای heuristic داخل کنترلر (`isWorkoutIntent:1033`, `isTaskCreationIntent:1045` با regex و کلمه‌ی کلیدی). این سه می‌توانند همزمان فعال شوند و پیام‌های متفاوتی بدهند.
3. **`AiIntentRouter::toolsFor` فقط در تست‌ها صدا زده می‌شود** (grep تأیید کرد) — مسیر production از `ToolCapabilityRouter::select` می‌رود؛ یعنی دو پیاده‌سازی موازی از سیاست زیرمجموعه‌ی tool وجود دارد که یکی‌شان dead code است (`AiIntentRouter.php:126`).
4. **پروفایل `planning` در `PROFILES` تعریف شده اما هیچ‌جا انتخاب نمی‌شود** (`AiContextEngine.php:86`؛ `profileFor:109` فقط `minimal|chat|agent` برمی‌گرداند) — پیکربندی مرده.
5. **سقف‌ها در سه‌جا تکرار شده‌اند:** مدل (`AiPlan::MAX_*`)، اعتبارسنجِ ابزار (`ToolSchema`/`validatePlanPropose`)، و متن system prompt (`AiChatController:~1925`) — امکان ناهماهنگی هنگام تغییر.
6. **fallbackهای heuristic مسیر اصلی را دور می‌زنند:** اگر پروایدر proxy tool_calls را برنگرداند، `buildTaskCreationFallbackProposals` با regex از متن کاربر تسک می‌سازد و `buildWorkoutFallbackProposal` در بدترین حالت یک LLM دوم (`parseWithAi`) اجرا می‌کند — هزینه/رفتار غیرمنتظره در لبه‌ها (`AiChatController:1116,1195`).
7. **بودجه‌ی openai برای parse ورک‌اوت در مسیر import هنوز ۲۰۴۸ است** (`WorkoutImportService:221` `openAiPayload` بدون tools → `AiProviderService:446` → 2048) در حالی که مسیر چت برای ورک‌اوت ۱۶۰۰۰ می‌گذارد — planهای ۷روزه‌ی بلند ممکن است قطع شوند.
8. **بدون queue:** همه‌ی فراخوانی‌های مدل همزمان داخل request PHP اجرا می‌شوند (timeout ۶۰-۹۰ ثانیه؛ `app/Jobs` وجود ندارد).
9. **`resolveRoutine` برخلاف `resolveTask` ابهام را چک نمی‌کند** (اولین LIKE).
10. **پیام‌های سیستمی هاردکد فارسی** در مکالمه‌ی کاربران انگلیسی.

---

## ۱۱. مستندات موجود در مقابل کد (شکاف مشاهده‌پذیری دانش)

`docs/AI_SYSTEM.md` و `docs/AI_TECH_SPEC.md` هر دو ادعا می‌کنند «وضع فعلی، تأییدشده در Oct 2026» هستند، اما کد از آن زمان در چندین محور جلوتر رفته است:

| ادعای مستند | وضعیت واقعی کد امروز |
|---|---|
| «`ai.chat`/`ai.stream` بدون throttle» (`AI_SYSTEM.md §14`، `AI_TECH_SPEC S1/P1`) | ❌ منسوخ — `routes/web.php:201-202` حالا `throttle:15,1` دارد |
| «history از کلاینت می‌آید و trust می‌شود» (`AI_SYSTEM §18`، `TECH_SPEC S7/P8`) | ❌ منسوخ — تاریخچه سمت‌سرور از `ai_messages` (`AiContextEngine:558`)؛ کلاینت نادیده گرفته می‌شود |
| «`verify => false`» (`AI_SYSTEM §18`، `TECH_SPEC S4/P5`) | ❌ منسوخ — `config('ai.tls_verify', true)` (`AiProviderService:347`، `AiChatController:1394`) + تست |
| «`/ai/debug` کل لاگ سراسری را می‌دهد» (`AI_SYSTEM §17`، `TECH_SPEC S5/P6`) | ❌ منسوخ — `tailForUser` با fail-closed (`AiLogger:163`) + تست |
| «context نامحدود، بدون کش» (`AI_SYSTEM §19`، `TECH_SPEC P2`) | ❌ منسوخ — Context Engine با بودجه و کش ۶۰ ثانیه‌ای |
| «confirmAll سقف ۲۰ < MAX_OPEN 30» (`AI_SYSTEM §20-10`، `TECH_SPEC P12`) | ❌ منسوخ — `limit(MAX_OPEN)` (`AiActionController:105`) |
| «conversation بیگانه fork بی‌صدا می‌شود» (`TECH_SPEC §11`) | ❌ منسوخ — 403 `forbidden_conversation` |
| «بدون قفل سطر (S10/S11)» (`TECH_SPEC`) | ❌ منسوخ — `lockForUpdate` در runAction و confirmPhase |
| «کانال `ai` با driver single» (`AI_SYSTEM §17`) | ❌ منسوخ — `daily` با ۱۴ روز (`config/logging.php:68-73`) |
| «system prompt ادعای created by {user}» (`AI_SYSTEM §20-7`) | ❌ منسوخ — «built by the Task Manager team» (`AiChatController:1932`) |
| «۷ فایل تست / ۶۴ تست» (`AI_SYSTEM §20`) | ❌ منسوخ — ۱۶ فایل / ~۱۹۷ تست |
| «۱۶ ابزار / سقف ۵» (`docs/PROJECT-GUIDE.md:94`) | ❌ منسوخ — ۲۴ ابزار / سقف ۳۰ |
| «XSS بدون sanitizer» | ✅ هنوز درست (باز) |
| «fallback `.env` کلید» | ✅ هنوز درست (باز) |
| «عضویت با LIKE name» | ✅ هنوز درست (باز) |
| «بدون metering توکن/هزینه» | ✅ هنوز درست (باز) |
| «`is_default` مرده» (`AI_SYSTEM §20-4`) | ✅ هنوز درست |
| «Planner aiOptimize هوشمند متنی نیست» (`TECH_SPEC §1.4`) | ✅ هنوز درست — هیچ فراخوانی LLM در `PlannerController` نیست (grep تأیید) |

`docs/TOOL_CAPABILITY_ROUTING.md` و `docs/AI_AGENT_IMPROVEMENT_PLAN.md` با کد هماهنگ‌تر به‌نظر می‌رسند (فازهای ۰-۵ عملاً پیاده‌سازی‌شده: project tree در context، `workspace_search`، گارد duplicate با candidates، `ask_user_choice`).

---

## ۱۲. فرضیه‌های تأییدنشده (برچسب‌خورده، نه واقعیت)

1. **فرضیه:** ابهام `resolveRoutine` (اولین LIKE) در عمل باعث اجرای روی روتین اشتباه شده باشد — کد چنین امکانی می‌دهد اما شواهد رخداد واقعی در لاگ‌ها پیدا نکردم.
2. **فرضیه:** برش ۸۰۰ کاراکتری تاریخچه باعث «فراموشی جزئیات» در اصلاحات بلند شده باشد — کد قطعی است، اما تأثیر رفتاری مدل اندازه‌گیری نشده.
3. **فرضیه:** false-positive های `emitToolMissedNotice` روی پیست کد رخ داده باشد — رویداد `stream.tool_missed_text_json` در لاگ‌های موجود دیده نشد (پس فعلاً نادر یا صفر است).
4. **فرضیه:** پلن جایگزین‌نشده (بدون auto-cancel) باعث سردرگمی یا خطای «too many open plans» برای کاربر شده باشد — کد تأیید، تجربه کاربری خیر.
5. **فرضیه:** `LIKE %needle%` در resolveTask/WorkspaceSearch برای حجم دیتای فعلی (مسیر شخصی) مشکل ایجاد کند — بدون اندازه‌گیری، فقط مشاهده‌ی نبود ایندکس‌پذیری.
6. **وضعیت دو تست شکست‌خورده‌ی غیر-AI:** `NoteEditorTest` و `MorningCheckinPromptTest` — فرضیه (بسیار محتمل) که به ترتیب به تغییرات اخیر UI نوت و بازه‌ی زمانی روز وابسته‌اند؛ بررسی نشد چون خارج از دامنه‌ی AI بودند.

---

## ۱۳. محدودیت‌های این ممیزی

- هیچ کدی تغییر نکرد؛ هیچ سناریوی زنده‌ای (مرورگر + پروایدر واقعی) اجرا نشد — رفتار end-to-end با کد و تست‌ها استنباط شده است.
- تست‌ها یک‌بار اجرا شدند (`447 passed / 2 failed`); شکست‌ها غیر-AI بودند.
- تحلیل لاگ روی ۴ فایل موجود `storage/logs/ai*.log` (اکتبر ۲۰۲۶) انجام شد؛ لاگ‌های قدیمی‌تر در دسترس نبودند.
- `resources/views/ai/index.blade.php` به‌طور کامل خوانده نشد (۱۹۴۷ خط) — رفتار UI با نگاشت targeted (grep) تأیید شد.
- ادعاهای «token/هزینه» در این گزارش تخمین chars/4 هستند، نه اندازه‌گیری billing.
