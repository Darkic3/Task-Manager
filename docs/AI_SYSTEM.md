# AI System — Full Documentation (وضع فعلی پروژه)

> هدف این سند: توصیف دقیق و کامل **وضع موجود** زیرسیستم هوش مصنوعی (نام فعلی: Lina/لینا) —
> کارکرد، منطق، فیلدها، قابلیت‌ها، UI/UX — تا بتوان آن را به یک AI دیگر داد برای گرفتن پیشنهاد.
> هیچ‌چیز در این سند پیشنهاد آینده نیست، مگر در بخش ۲۰ که صراحتاً «گپ‌های مشاهده‌شده» جدا شده است.
> استک: Laravel 12 + Blade + Vite. تاریخ بررسی: Oct 2026.

---

## ۱. شناسنامه و دامنه

| مورد | مقدار |
|---|---|
| نام فعلی دستیار | Lina / لینا (هاردکد در ~۹۰ نقطه: بلید، `lang/*.json`، `lina_dataset.json`، پرامپت سیستمی) |
| نقطه ورود وب | سایدبار → Intelligence → Lina AI (`GET /ai`، نام روت `ai.index`) + ویجت شناور سراسری (`resources/views/layouts/_ai_chat.blade.php`) |
| صفحه تنظیمات | `GET /ai/settings` (نام روت `ai.settings`) |
| مدل هزینه | BYOK: هر کاربر کلید خودش را می‌گذارد (ستون رمزنگاری‌شده)؛ fallback سراسری `.env` هم موجود است (بخش ۴) |
| دو مود | `chat` (پیش‌فرض، فقط‌خواندنی) و `agent` (ابزار + کارت تأیید) — انتخاب در هدر چت، ذخیره در `localStorage`، ارسال `mode` با هر درخواست؛ **سرور تنها مرجع** است |
| آفلاین | بدون هیچ پروایدر: `LinaFallbackBrain` (PHP خالص، بدون API خارجی) |

فایل‌های مرجع (مسیر دقیق):

```
app/Http/Controllers/AiChatController.php      (~1320 خط: index/status/CRUD کانورسیشن/chat/stream/dispatch/buildMessages/buildContext/debug)
app/Http/Controllers/AiSettingsController.php  (index/update/quickSwitch)
app/Http/Controllers/AiProviderController.php  (store/update/destroy/test برای کاستوم)
app/Http/Controllers/AiActionController.php    (confirm/reject/confirmAll/rejectAll + runAction)
app/Http/Controllers/AiPlanController.php      (confirmStructure/confirmPhase/cancel + serialize)
app/Http/Controllers/NoteAiController.php      (sendCollection/extractPreview/extractApply/summarizeSubject)
app/Services/AiProviderService.php             (resolve/key/endpoint/retry/payload/converters)
app/Services/AiToolService.php                 (۲۲ ابزار: definitions/validateCall/preview/execute + executePlanPhase)
app/Services/AiLogger.php                     (log/error/sanitize/tail)
app/Services/LinaFallbackBrain.php             (~۵۰۱ خط) + resources/ai/lina_dataset.json
app/Services/Notes/NoteAiService.php           (MODES + complete + contextFor + extract + summarize + analyzeCollection)
app/Models/{AiSetting,AiProvider,AiConversation,AiMessage,AiPendingAction,AiPlan}.php
config/ai.php, config/services.php, config/logging.php (کانال ai)
routes/web.php:183-219
resources/views/ai/index.blade.php (~۱۸۰۹ خط)، resources/views/ai/settings.blade.php (~۳۴۰ خط)
migrations: 2026_08_12_000001 (ai_settings), 2026_08_12_000002 (ai_providers),
             2026_05_04_000001 (ai_conversations + ai_messages),
             2026_09_24_000001 (ai_pending_actions), 2026_09_24_000002 (ai_plans)
tests/Feature/Ai*.php (۷ فایل)
```

---

## ۲. معماری و مسیر درخواست (دقیق)

```
کاربر (/ai یا ویجت شناور)
  → POST /ai/stream (SSE، بدنه: message≤8000، conversation_id?، history≤40×8000، mode)
  → AiChatController@stream
      ۱. ولیدیشن JSON (خطا → 422 JSON، هرگز ریدایرکت، چون fetch است)
      ۲. فیلتر نوبت‌های خالی/فقط-فاصله
      ۳. resolve() پروایدر/مدل/کلید برای همین کاربر
      ۴. یافتن یا ساخت AiConversation (اسکوپ user_id) + ذخیره پیام user + لیبل خودکار اولین پیام (۶۰ کاراکتر اول)
      ۵. اگر پروایدر نیست → LinaFallbackBrain → استریم قطعه‌قطعه + ذخیره پاسخ آفلاین
      ۶. buildContext(user) + buildMessages(user,context,history,msg,mode)
      ۷. اگر agent و type != openai → پیام بلاک SSE (هیچ تغییری)
      ۸. اگر type == openai → streamOpenAi (استریم واقعی Guzzle، خوانش ۲۵۶ بایتی)
         وگرنه → sync کامل بعد شبیه‌سازی استریم (chunkهای ۵ کاراکتری + usleep 12ms)
      ۹. در [DONE]: ذخیره پیام assistant + touch کانورسیشن + (در agent) emitToolProposals و...
```

نسخه غیراستریم `POST /ai/chat` هم هست (کمتر استفاده می‌شود؛ همان resolve/context ولی بدون ذخیره کانورسیشن، خروجی `{model,provider,reply?,proposal/proposals?}`).

نمودار حالت ابزار:

```
definitions() → مدل tool_call → createPendingFromToolCall (cap + validateCall)
  → ai_pending_actions(pending,+15min) → کارت تأیید در چت
  → POST /ai/actions/{id}/confirm|reject (throttle:30,1، مالکیت ۴۰۳، re-validate، transaction)
  → پیام ✅ در تاریخچه + AiLogger
```

نمودار پلن:

```
plan_propose (تک‌کال) → AiPlan(proposed, structure, phases[], current_phase=0, +15min)
  → کارت ساختار → confirm-structure (dedupe + advancePastDone + touchExpiry)
  → confirm-phase {phase == pointer سرور، وگرنه dedupe} (تکی یا run_all)
  → executePlanPhase تزریق ID واقعی فازبه‌فاز → done/cancelled/expired
```

---

## ۳. مدل‌های دیتابیس و فیلدها

### ۳.۱ `ai_settings` (یک ردیف به‌ازای هر کاربر، `user_id unique`)

| ستون | نوع | توضیح |
|---|---|---|
| `user_id` | FK users cascade, unique | مالک |
| `default_provider` | string nullable | مثل `openai` یا `custom:7`؛ اگر خالی → auto (اولین فعال) |
| `default_model` | string nullable | اورراید سراسری مدل پیش‌فرض (فقط اگر به همان پروایدر بخورد) |
| `openai_key / gemini_key / anthropic_key / deepseek_key / meta_key` | text nullable, cast `encrypted` | کلید per-user؛ خالی یعنی «تنظیم نشده» |
| `openai_model / gemini_model / ...` | string nullable | مدل منتخب per-provider (فقط اگر در کاتالوگ `config/ai.php` باشد) |

ساخت: `AiSettingsController@index` با `firstOrCreate(['user_id'])`.

### ۳.۲ `ai_providers` (کاستوم، چند ردیف به‌ازای هر کاربر)

| ستون | نوع | توضیح |
|---|---|---|
| `user_id` | FK cascade | مالک |
| `label` | string(80) | نام نمایشی (مثل «OpenRouter») |
| `type` | openai\|gemini\|anthropic (default openai) | نوع پروتکل؛ فقط `openai` ابزار/agent دارد |
| `base_url` | string(500), url | نرمالایز با `endpointFor()` (بخش ۴.۴) |
| `api_key` | text nullable, cast `encrypted` | خالی در update یعنی «نگه‌دار قبلی» |
| `model` | string(120) | free-text (اعتبارسنجی فقط غیرخالی بودن) |
| `enabled` | bool default true | از چک‌باکس فرم می‌آید |
| `is_default` | bool | در DB هست ولی منطق default از `ai_settings.default_provider` خوانده می‌شود |
| `sort_order` | uint | `max+1` هنگام ساخت؛ ترتیب نمایش و auto-detect |

کلید یکتا برای ارجاع: `providerKey() = 'custom:{id}'`.

### ۳.۳ `ai_conversations` + `ai_messages`

| جدول | فیلدها |
|---|---|
| `ai_conversations` | `id, user_id FK cascade, label default 'New Chat', timestamps` |
| `ai_messages` | `id, conversation_id FK ai_conversations cascade, role enum(user,assistant), content longText, model string nullable, timestamps` — بدون ستون feedback/cost/tokens |

رابطه‌ها: `AiConversation::messages()` مرتب با `created_at`؛ `AiMessage::conversation()`.

رفتار: ساخت خودکار در `stream` اگر `conversation_id` نامعتبر/خالی باشد؛ لیبل خودکار = ۶۰ کاراکتر اول اولین پیام؛ `clear` = حذف پیام‌ها + ریست لیبل؛ حذف کانورسیشن = cascade پیام‌ها.

### ۳.۴ `ai_pending_actions`

| ستون | مقدار |
|---|---|
| `user_id` | FK cascade |
| `conversation_id` | nullable FK ai_conversations cascade |
| `tool` | string(60)، یکی از ۲۲ نام `TOOLS` (نرمالایز: نقطه/خط‌فاصله → آندرلاین) |
| `args` | json (آرگومان **validateشده و resolveشده**، نه خام مدل) |
| `preview` | json nullable (rows کارت تأیید) |
| `status` | pending\|confirmed\|rejected\|executed\|expired |
| `expires_at` | timestamp (ساخت: `now()+15min`) |
| `executed_at` | nullable |
| `idempotency_key` | string(64) unique، `random_bytes(32)` |
| ایندکس‌ها | `(user_id,status)`، `(user_id,expires_at)` |

ثابت‌ها: `EXPIRY_MINUTES=15`، `MAX_OPEN=30` (سقف pending باز به‌ازای کاربر؛ `confirmAll` فقط ۲۰ تای اول را برمی‌دارد).

### ۳.۵ `ai_plans`

| ستون | مقدار |
|---|---|
| `user_id`, `conversation_id?` | FK cascade |
| `title` | string(255) |
| `structure` | json (درخت validateشده: `project/subprojects[]/projects[]/reminders[]/notes[]/routines[]`) |
| `phases` | json (`[{key,label,total,done,status,result_ids[]}]` — پوینتر `current_phase` روی سرور) |
| `status` | proposed\|confirmed\|executing\|done\|cancelled\|expired |
| `current_phase` | tinyUint default 0 |
| `expires_at` | `now()+15min`، با `touchExpiry()` لغزشی تمدید می‌شود |
| `executed_at`, `idempotency_key unique` | — |
| ایندکس | `(user_id,status)` |

سقف‌های داخل مدل: `MAX_PROJECTS=5, MAX_SUBPROJECTS=3, MAX_TASKS=30, MAX_SUBTASKS=100, MAX_REMINDERS=10, MAX_NOTES=10`.

---

## ۴. پروایدرها، resolve و BYOK

### ۴.۱ کاتالوگ `config/ai.php`

۵ پروایدر داخلی، هرکدام: `label, key_env, key_column, model_column, base_url, type, models{ id => label }, default_model`:

| id | type | base_url | default_model | مدل‌ها (خلاصه) |
|---|---|---|---|---|
| `openai` | openai | `https://api.openai.com/v1/chat/completions` | `gpt-5.6-terra` | gpt-5.6-sol/terra/luna، gpt-5/mini/nano، gpt-4.1/mini، o3، o4-mini، gpt-4o/mini |
| `gemini` | gemini | `.../v1beta/models` | `gemini-3.6-flash` | 3.6-flash، 3.5-flash/lite، 3.1-flash-lite، 2.5-pro/flash/lite |
| `anthropic` | anthropic | `https://api.anthropic.com/v1/messages` | `claude-sonnet-5` | fable-5، opus-5، sonnet-5، haiku-4-5، opus-4-8/4-7، sonnet-4-6 |
| `deepseek` | openai | `https://api.deepseek.com/v1/chat/completions` | `deepseek-v4-flash` | v4-pro، v4-flash، chat/reasoner (legacy) |
| `meta` | openai | `https://api.llama.com/v1/chat/completions` | `muse-spark-1.2` | muse-spark-1.2، Llama-4-Maverick/Scout، Llama-3.3-70B، Llama-3.1-405B |

`default_provider = env(AI_DEFAULT_PROVIDER, AI_PROVIDER, 'openai')`، `default_model = env(AI_DEFAULT_MODEL)`.

### ۴.۲ ترتیب resolve (`AiProviderService::resolve`)

1. `setting.default_provider` (یا کانفیگ) اگر در لیست `providersForUser` باشد و `getKey()` غیرخالی → همان.
2. وگرنه اولین پروایدر (ترتیب: داخلی‌ها بعد کاستوم‌های مرتب با sort_order) که کلید دارد.
3. وگرنه `null` → حالت آفلاین.

`providersForUser = allProviders() + customProviders(user)` — کلید آرایه برای کاستوم `custom:{id}` است.

### ۴.۳ `getKey` / `getModel` (BYOK + fallback)

- کاستوم: فقط اگر رکورد متعلق به همین کاربر + `enabled` + `api_key` غیرخالی.
- داخلی: اول `AiSetting.{key_column}` (رمزگشایی خودکار لاراول)؛ اگر خالی → `config(services.{p}.key) ?: env(KEY_ENV)` یعنی **کلید سراسری `.env`**.
- `getModel`: کاستوم → `model` آزاد؛ داخلی → ستون per-provider اگر در کاتالوگ باشد وگرنه default؛ سپس اگر `setting.default_model` به همین پروایدر بخورد و در کاتالوگ باشد، همان.
- `enabledMap`: به‌ازای هر پروایدر `hasKey()`؛ `isConfigured = !empty(enabled)`.
- نمایش: `AiSettingsController@index` فقط `•••• + 4 کاراکتر آخر` می‌فرستد (`$masked`)؛ کلید کامل هرگز به مرورگر نمی‌رود. `status/debug` هم فقط `{provider,model,type}` برمی‌گردانند.

### ۴.۴ نرمالایز endpoint و تست اتصال

`endpointFor(baseUrl, type)`: اگر type != openai همان URL؛ اگر openai و انتها `/chat/completions` بود همان؛ `/chat` → `+ /completions`؛ `/completions` → جایگزینی با `/chat/completions`؛ وگرنه `+ /chat/completions`. یعنی کاربر می‌تواند بیس (`.../v1`) یا کامل را بچسباند.

`testConnection(AiProvider)`: فقط برای کاستوم (دکمه Test در تنظیمات) — پیام `ping` با `max_tokens/maxOutputTokens=5`؛ خروجی `{ok, message}`. برای داخلی دکمه Test وجود ندارد.

### ۴.۵ ارسال HTTP و خطا

`postJson(url, payload, headers, timeout)`: هدرهای `Content-Type + HTTP-Referer(app.url) + X-Title(app.name)`، `verify => false`، `timeout` (۶۰ چت/استریم، ۹۰ نوت، ۳۰ تست)، **retry تا ۳ بار** روی `429/5xx` با sleep ‏۱.۵/۳ ثانیه. `formatErrorResponse`: `HTTP {status}: {message — metadata.raw — remedy_hint}`.

`openAiPayload(messages, model, stream, baseUrl?, tools?)`: `max_tokens` ‏۲۰۴۸ عادی / ۴۰۹۶ با tools / ۱۶۰۰۰ اگر ابزار workout داخل باشد؛ `temperature 0.7`؛ اگر tools → `tools + tool_choice:auto`؛ اگر baseUrl شامل `openrouter.ai` و model شامل `,` → `models[]` فالبک + حذف `model` تکی.

کانورترها: `toGeminiContents` (system جدا → systemInstruction، assistant→model) و `toAnthropicMessages` (system جدا، بقیه `{role,content}`).

---

## ۵. مودهای chat و agent

| | 💬 chat | 🛠 agent |
|---|---|---|
| tools به مدل | ارسال نمی‌شود | `definitions()` کامل (فقط اگر `type==openai`) |
| system block | `MODE: CHAT` — بحث read-only؛ ساخت/تغییر خواست → توضیح + درخواست سوییچ | `MODE: AGENT` — قوانین کامل (بخش ۶.۲) |
| Gemini/Claude | پاسخ متنی عادی | بلاک با پیام «نیاز به پروایدر OpenAI-compatible» — هیچ تغییری |
| کارت proposal در UI | رندر نمی‌شود (فقط لاگ warn) | رندر می‌شود |

تاگل در هدر چت، `localStorage`، ارسال `mode` با هر درخواست. اندپوینت جدا ندارد.

---

## ۶. کانتکست ورک‌اسپیس و system prompt

### ۶.۱ `buildContext(user)` — دقیقاً چه می‌فرستد

بدون هیچ `limit`/`paginate`، هر بار کل دیتای کاربر:

```
PROJECTS (n):
- {name} (status: {status}[, due: Y-m-d])            ← select(name,status,end_date,budget)
TASKS (n):
- [{status}] {title} (priority: {p}[, due: {d}][, project: {name}])  ← همه تسک‌ها + project:id,name
NOTES (n):
- {title}[ [tags: a, b]]: {strip_tags(substr(content,0,120))}        ← همه نوت‌ها
REMINDERS (n):
- [{done|pending}] {title}[ at Y-m-d H:i][ [tags]]                  ← همه ریمایندرها
ROUTINES (n):
- {title} ({frequency})                                             ← همه روتین‌ها
FILES (n):
- {name} (type: {type})                                             ← فقط نام/تایپ، نه محتوا
```

بخش‌های خالی حذف می‌شوند. خطا → رشته `(Could not load user data)`.

### ۶.۲ `buildMessages(user,context,history,newMessage,mode)`

```
system:
You are Lina, a smart personal AI assistant built into this Task Manager app by {user->name}.
If asked your name... say Lina. If asked who created you... say created by {user->name}.   ← (همین متن فعلی)
Today is {l, F j, Y}.
You can help with: workspace data + coding/tech + general knowledge
Guidelines: lang directive (fa→ فارسی روان / en→ English) + markdown + fenced code + only-context + concise
{MODE block}
--- USER WORKSPACE DATA ---
{context}
--- END WORKSPACE DATA ---
+ history (فیلتر خالی‌ها) + newMessage — همه cleanUtf8 شده
```

بلاک AGENT (خلاصه سرفصل‌ها، متن کامل در کد `AiChatController:1165-1189`): تک‌آیتم‌ها؛ resolve با title (هرگز ID نخواه)؛ bulk = چند کال در یک ترن؛ project_add_member با email؛ note_link؛ report_generate؛ plan_propose تکی با همه‌چیز (projects[] تا ۵ مستقل یا project+subprojects تا ۳، reminders[] تا ۱۰ با date/time/location، notes[] تا ۱۰، caps)؛ تاریخ فارسی (امروز/فردا/پس‌فردا، ساعت ۶ عصر=۱۸:۰۰)؛ workout_plan_propose برای هر پلن تمرینی (۷ روز، نگاشت DAY1=start_date، parsing «4×6–8/RIR/tempo/side/amrap/circuit/❌→rules»)؛ routine weekly حتماً days؛ tracking (نامشخص بود → اول بپرس)؛ سقف عنوان ۱۲۰/توضیح ۵۰۰؛ snake_case.

بلاک CHAT: read-only؛ workout را توضیح بده ولی نساز (ارجاع به Agent یا Workouts→Import).

مقادیر عددی فعلی: ورودی `message≤8000`، `history≤40` نوبت هرکدام `≤8000`؛ `max_tokens` خروجی ۲۰۴۸/۴۰۹۶/۱۶۰۰۰؛ Gemini `maxOutputTokens 2048/temp 0.7`؛ Anthropic `max_tokens 2048/temp 0.7`؛ NoteAi جدا `temp 0.3` و سقف‌های ۲۰۰۰/۲۵۰۰/۳۰۰۰.

---

## ۷. ابزارها (۲۲ عدد) — ورودی و رفتار دقیق

> قرارداد نام: فقط snake_case (`normalizeToolName` نسخه‌های نقطه/خط‌فاصله قدیمی را هم می‌پذیرد ولی ۴۰۰ نمی‌دهد چون اسکیما `additionalProperties:false` دارد).

| # | ابزار | ورودی کلیدی (required با *) | رفتار validate → preview → execute |
|---|---|---|---|
| ۱ | `task_create` | `title*`, `project_id?/project?` (نام یا ID)، `parent_id?` (ساب‌تسک؛ پروژه از والد ارث می‌رسد)، `due_date?`, `priority?`, `status?`, `description?` | والد باید مال کاربر + هم‌پروژه باشد؛ پروژه باید مال کاربر باشد (وگرنه «not yours»)؛ بدون پروژه هم مجاز (project null)؛ پیش‌فرض priority=medium/status=to_do |
| ۲ | `task_update` | `id?/task?` (ID یا title + اسکوپ `project?/project_id?`) + هرکدام از `title/due_date/priority/status/description` | `resolveTask`: با ID (اسکوپ user) یا title (+اسکوپ پروژه)؛ مبهم بود → خطای «کدام؟» |
| ۳ | `task_complete` | `id?/task?` (+اسکوپ) | تیک + `completed_at` |
| ۴ | `task_delete` | `id?/task?` (+اسکوپ) | preview اثر ساب‌تسک‌ها؛ اجرا حذف |
| ۵ | `reminder_create` | `title*`, `date?`, `time? (HH:MM)`, `priority?`, `description?`, `location?` | اگر date/time خالی: `FaDateParser::parse(title+desc)` فارسی (امروز/فردا/ساعت ۶ عصر→۱۸:۰۰) |
| ۶/۷ | `reminder_complete/delete` | `id*` | مالکیت user_id |
| ۸ | `note_create` | `title*, content*`, `category?` | مستقیم |
| ۹/۱۰ | `note_update/delete` | `id*` (+title/content/category برای update) | مالکیت user_id |
| ۱۱ | `project_create` | `name*`, `description?`, `status?`, `parent?/parent_id?` (نام یا ID) | والد باید مال کاربر؛ عمق ≤۵ (`projectDepth`)؛ type=project |
| ۱۲ | `project_add_member` | `project?/project_id?` + `user*` (email/نام/ID) + `role? member/viewer/editor` | پروژه باید مال تو؛ ممبر با `resolveMemberUser` (عددی→find، شامل @→email، وگرنه LIKE نام، اولین)؛ خودت خطا؛ اجرا `syncWithoutDetaching` |
| ۱۳ | `note_link` | `note?/note_id?` + `target_type* (project/task/note)` + `target?/target_id?` | هر دو سمت `where user_id`؛ سلف‌لینک نوت ممنوع؛ اجرا `NoteLinkService::attach` |
| ۱۴ | `report_generate` | `range? today/week/month (default week)` | فقط‌خواندنی (`UnifiedReportService`)؛ هیچ رکوردی نمی‌سازد؛ خروجی مارک‌داون KPI+دلتا+اینسایت |
| ۱۵ | `checklist_add` | `task_id?/task?` (+اسکوپ) + `name*` | تسک باید مال کاربر |
| ۱۶ | `checklist_toggle` | `id*` | toggle + برگرداندن done/reopened |
| ۱۷ | `routine_create` | `title*, frequency* (daily/weekly/monthly/every_n_days)`, `days?` (weekly اجباری)، `month_days?`, `every_n_days?`, `time_period?`, `description?` (≤۵۰۰)، `tracking_mode? none/value/sets`, `value_kind?/unit?/label?`, `steps[]?` (≤۲۰، هرکدام target_sets) | آینه `RoutineController@validated`؛ preview شامل days_label/value_label |
| ۱۸ | `routine_complete` | `id*`, `date?` (default امروز) | هرگز un-complete نمی‌کند؛ تکراری → «already done» |
| ۱۹ | `routine_delete` | `id*` | سافت‌دیلیت؛ کارت می‌گوید تاریخچه می‌ماند |
| ۲۰ | `routine_log` | `routine?/routine_id?`, `date?`, `value*` (عدد؛ time-kind: دقیقه از نیمه‌شب یا رشته HH:MM)، `item?/item_id?/set_no?` (sets-mode) | چک رهگیری‌بودن؛ sets کامل → auto-complete |
| ۲۱ | `plan_propose` | `title*` + یکی از: `projects[]` (تا ۵ مستقل، هرکدام tasks[] + members[] تا ۵) یا `project{}+subprojects[]` (تا ۳) یا `routines[]` (انحصاری، تا ۷) + `reminders[]` (تا ۱۰) + `notes[]` (تا ۱۰)؛ تسک‌ها `{title≤120,due_date,priority,description≤500,subtasks[]}` | فقط validate + ساخت `AiPlan`؛ اجرا مرحله‌ای (بخش ۸)؛ مدل هیچ ID نمی‌بیند |
| ۲۲ | `workout_plan_propose` | `title*, days[7]` (به ترتیب اجرا، هر روز `{title,type:training/rest/recovery,notes?,exercises[]}`؛ هر تمرین `{name*,section?,target_sets?,rep_min/max?,duration_seconds?,target_weight?,target_rir?,rest_seconds?,tempo?,side_mode?,is_amrap?,is_circuit?,circuit_rounds/rest?,notes?}`) + `week_number?/start_date?/goal?/description?/rules[]?` | فقط validate + ساخت `WorkoutImport(PREVIEW,+30min)`؛ سقف ۷ روز، ۴۰ تمرین/روز |

`validateCall(tool,args,user)` ابتدا normalize (شامل aliasهای camelCase مثل `projectId/dueDate/monthDays`) بعد match به ولیدیتور. هیچ ولیدیتوری write نمی‌کند. `preview(tool,resolved,user)` ردیف‌های کارت تأیید را می‌دهد (برای deleteها هشدار impact قرمز در فرانت). `execute(tool,resolved,user)` داخل `DB::transaction` می‌نویسد و `{ok,message,id?}` برمی‌گرداند؛ `plan/workout` در execute تکی اجرا نمی‌شوند (پیام «مرحله‌به‌مرحله»).

---

## ۸. چرخه تأیید تکی (`AiActionController`)

- `confirm(action)`: ۴۰۳ مالکیت → `runAction` → `{ok,deduped?,message?}` یا 422 `{ok:false,error}`.
- `runAction`: اگر `executed` → deduped؛ اگر `!isActionable` (expired یا غیرpending) → markExpired + `code:expired`؛ وگرنه **re-validate** با args ذخیره‌شده (مالکیت ممکن است عوض شده باشد) → `confirmed` → `execute()` → fail: `execute_failed`؛ success: `executed + executed_at` + لاگ + اگر `conversation_id` → پیام `✅ ...` در تاریخچه.
- `reject`: اگر pending نیست → deduped؛ وگرنه `rejected`.
- `confirmAll`: ۲۰ تای اول pending (قدیمی اول)، هرکدام مستقل (یکی fail بقیه را بلاک نمی‌کند) → `{ok: empty(failed), message: 'N executed. M failed... K expired', details[]}`.
- `rejectAll`: ۲۰ تای اول → expiredها mark، بقیه rejected → شمارش.
- همه چهار اندپوینت `throttle:30,1` دارند. بدون Undo (بعد از execute برگشت نیست).

---

## ۹. پلن‌های ساختاری (`AiPlan` + `AiPlanController`)

- ساخت فقط از `plan_propose` (بخش ۷/۲۱) با `createPlanFromToolCall`: سقف ۳ پلن باز (proposed/confirmed/executing،未expire) وگرنه خطای «finish or cancel one» → `AiPlan::create(status=proposed, phases=buildPlanPhases(structure), current_phase=0, expires_at=+15min, idempotency)`.
- فازها (ترتیب ثابت): ۱ پروژه‌ها → ۲ ساب‌پروژه‌ها → ۳ تسک‌ها → ۴ زیرتسک‌ها → ۵ ریمایندرها → ۶ نوت‌ها (هر فاز `{key,label,total,done,status,result_ids[]}`).
- `confirm-structure`: ۴۰۳ → اگر confirmed/executing/done → deduped؛ اگر `!isActionable` → expire + 422؛ وگرنه `confirmed + advancePastDone (رد فازهای صفرتایی) + touchExpiry` + یادداشت `📋 Structure approved (nothing created yet...)` در تاریخچه.
- `confirm-phase {phase?, run_all?}`: ۴۰۳ → اگر done → deduped؛ اگر actionable نیست یا status مجاز نیست → expire + 422؛ **اگر `phase != current_phase` سرور → deduped** (ضد دابل‌کلیک که فاز بعد را اشتباهی اجرا کند)؛ حلقه `executePlanPhase(fresh)` تا `run_all` تمام شود یا یک فاز fail شود (422 با plan تازه)؛ بعد هر فاز `advancePastDone`؛ در پایان یادداشت `✅ ...` در تاریخچه.
- `cancel`: → `cancelled` (آیتم‌های ساخته‌شده می‌مانند) + پیام «already-created items stay».
- `serialize(plan)`: شکل کارت stepper برای فرانت. پکت‌های SSE مرتبط: `plan_proposal` / `plan_phase_update` (repaint ضمنی).
- اعتبارسنجی ساختار: عنوان تسک ≤۱۲۰، سقف‌های بخش ۳.۵؛ نسخه قدیمی تک‌ریشه (بدون `projects`) هم با همان منطق اجرا می‌شود.

---

## ۱۰. ایمپورت برنامه تمرینی (Workout)

- ساخت فقط از `workout_plan_propose` (بخش ۷/۲۲) با `createWorkoutImportFromToolCall`: سقف ۳ ایمپورت PREVIEW باز未expire وگرنه خطا → `normalizeStructure(structure, start_date?)` via `WorkoutImportService` → `WorkoutImport::create(status=PREVIEW, expires_at=+30min)` → پکت `{import:{id,title,week_number,start_date,preview,preview_url: workouts.imports.show}}`.
- Fallback وقتی مدل tool_call نزد (پراکسی‌های کاستوم مثل Nara/OpenRouter): `isWorkoutIntent(userMsg)` (≥۲ کلیدواژه از لیست دوزبانه + طول>۱۰۰) → `buildWorkoutFallbackProposal`: idempotency با هش `md5(userMsg)` در `source_text like %[msg:hash]%` (retry استریم duplicate نمی‌سازد) → اول `extractWorkoutJson(assistantText)` (بلوک ```json یا `{...}` با `days`) → `normalizeStructure` → اگر نشد `parseWithAi(userMsg≤30000, anchor)`؛ `startsTodayCue` (شروع از امروز) → `start_date=today`؛ fail → پیام فارسی «از Workouts → Import with AI بده».
- تأیید/ساخت نهایی پلن در فلو `WorkoutImportController@confirm` است (خارج از AI چت)، نه در `execute()` ابزار.

---

## ۱۱. استریم SSE (جزئیات فنی)

- هدرها: `Content-Type: text/event-stream, Cache-Control: no-cache, X-Accel-Buffering: no, Connection: keep-alive`.
- اولین ایونت همیشه `{model,provider,conversation_id}`؛ بعد دلتاها `choices[0].delta.content`؛ پایان `[DONE]`؛ خطا وسط استریم `{error}` + `[DONE]`.
- OpenAI: `Guzzle verify=>false, timeout 60`، `POST endpoint` با `stream:true`، خوانش حلقه‌ای ۲۵۶ بایت، parse خط‌های `data:`، انباشت `accumulatedText` + `toolAccum[index]={id,name,arguments+=chunk}`؛ در `[DONE]`: persist (اگر متن غیرخالی) + `touch` + (agent) سه emit: `emitToolProposals` (هر tool_call → pending + پکت SSE کارت) ، `emitWorkoutFallbackIfNeeded`، `emitToolMissedNotice` (اگر متن شبیه JSON/دستور بود ولی tool_call نیامد: «چیزی ساخته نشد»).
- اگر `POST` اول غیر-۲۰۰ شد یا وسط استریم `{"error":...}` آمد و هنوز چیزی استریم نشده → **fallback به sync** (`callOpenAiSyncRaw` + chunk ۵تایی + همان سه emit)؛ وگرنه خطا به فرانت.
- اگر استریم بدون `[DONE]` قطع شد: در انتها اگر متن ناتمام ذخیره نشده بود، با چک `exists(همان content)` ذخیره می‌شود (ضد duplicate).
- غیر-OpenAI: `callProviderSync` کامل → ذخیره → شبیه‌سازی استریم (chunk ۵تایی، usleep 12ms)؛ بدون tool_call.
- آفلاین: chunkهای ۴تایی + usleep 14ms با مدل `lina-offline`.
- `sseFlushClosure`: `ob_flush + flush` اگر سطح بافر >۰.

---

## ۱۲. مغز آفلاین (`LinaFallbackBrain` + دیتاست)

- سازنده: `($user, $message)`؛ `low = strtolower(trim)`؛ لود `resources/ai/lina_dataset.json` (intro/help/labels بدون دست‌کاری PHP).
- ترتیب ifها در `respond()`: هویت (who are you) → قابلیت‌ها → تاریخ/ساعت → سلام → تشکر → summary/overview → overdue → due-today → due-tomorrow → high-priority → task-count → ... (ادامه در فایل، ~۵۰۰ خط)؛ هر شاخه کوئری مستقیم DB با `user_id` همین کاربر + رشته مارک‌داون (متن‌ها از دیتاست با جایگذاری `{name}` = نام کاربر).
- فقط‌خواندنی است؛ هرگز چیزی نمی‌سازد. regexها عمدتاً انگلیسی‌اند (فارسی محدود).

---

## ۱۳. هوش نوت‌ها و پلنر (`NoteAiService` + `NoteAiController` + `PlannerController::aiOptimize`)

- `NoteAiService::MODES = [summary, decisions, tasks, timeline, report]`.
- `complete(user, messages, maxTokens)`: resolve مشترک → سه مسیر openai/gemini/anthropic (temp ‏۰.۳، timeout ‏۹۰)؛ بدون پروایدر → Exception «Add an API key in AI Settings».
- `contextFor(notes, maxChars=60000)`: `### {title} [{kind} · {date}]` + `> summary` + content تا سقف؛ اضافه‌حجم break می‌شود.
- `extractFromNote`: پرامپت «فقط JSON با summary/tasks/decisions/questions» (maxTokens ۲۰۰۰) → `parseJson` (strip fences، برش `{...}`) → `cleanTasks` (≤۲۰، title≤۲۵۵، due اعتبارسنجی، priority نرمال) / `cleanDecisions` (≤۲۰)؛ اگر AI نیست یا خطا → `ruleBasedExtract` (چک‌باکس‌های `- [ ]` + summary/excerpt موجود).
- `summarizeSubject(subject, notes)`: سقف ۵۰۰۰۰ کاراکتر، سکشن‌های فارسی ثابت (وضعیت فعلی/نکات مهم/تصمیم‌ها/مسائل باز/اقدام بعدی)، maxTokens ۲۰۰۰.
- `analyzeCollection(user, notes, mode)`: سقف ۶۰۰۰۰، دستور per-mode (انگلیسی + «same language as notes»)، maxTokens ۳۰۰۰.
- `NoteAiController`: `sendCollection(collection_id?/mode?)` — کالکشن از `NoteShare::ofUser` یا فیلتر جاری (`NoteQueryService`)، `limit 200 + notebook/labels`، خالی → خطا، بی‌کلید → redirect به settings، سپس analyze + ساخت `AiConversation(label: {name} · {mode})` با پیام user (کانتکست) و assistant (پاسخ)؛ `extractPreview/Apply` و `summarizeSubject` با `authorize(view/update)` روی نوت (Policy).
- `PlannerController::aiOptimize` (مودال `_ai-schedule-modal`): مرتب‌سازی تسک‌های امروز با بار شناختی/انرژی؛ پیام موفقیت `Lina successfully organized...`.

---

## ۱۴. روت‌ها (جدول کامل AI)

| متد | URI | کنترلر@متد | throttle | ورودی | خروجی |
|---|---|---|---|---|---|
| GET | `/ai` | AiChat@index | auth | — | ویو + `resolved/enabledMap/providers` |
| GET | `/ai/status` | AiChat@status | auth | — | `{resolved{provider,model,type}?, enabled{}, agent_ready, agent_block_reason?, providers[{label,models,default_model}]}` |
| GET | `/ai/settings` | AiSettings@index | auth | — | ویو + `setting/providers/allProviders/customProviders/enabledMap/masked` |
| PUT | `/ai/settings` | AiSettings@update | auth | `default_provider? (in همه)، default_model?، {p}_key? (≤500، masked→skip، خالی→skip، clear_*→null)، {p}_model?` | redirect + success |
| POST | `/ai/settings/switch` | AiSettings@quickSwitch | auth | `provider*, model?` | `{ok:true}` |
| POST | `/ai/providers` | AiProvider@store | auth | `label*, type* (openai/gemini/anthropic), base_url* url, model*, api_key?, enabled?` | redirect |
| PUT | `/ai/providers/{provider}` | AiProvider@update | auth+owner403 | همان + کلید خالی یعنی نگه‌دار | redirect |
| DELETE | `/ai/providers/{provider}` | AiProvider@destroy | auth+owner403 | — (اگر default بود → null) | redirect |
| POST | `/ai/providers/{provider}/test` | AiProvider@test | auth+owner403 | — | `{ok,message}` (ping) |
| POST | `/ai/chat` | AiChat@chat | auth (بدون throttle) | `message*≤8000, history?≤40×8000, mode?` | `{model,provider,reply?,proposal?/proposals?}` یا `{reply: 'No provider…'}` یا `{reply:'AI error…'}` (همیشه ۲۰۰ به‌جز ولیدیشن) |
| POST | `/ai/stream` | AiChat@stream | auth (بدون throttle) | `message*≤8000, conversation_id?, history?, mode?` | SSE (بخش ۱۱) یا 422 JSON |
| GET | `/ai/debug` | AiChat@debug | auth | — | `{now,resolved?,enabled,note?,agent_ready,agent_block_reason?,recent_pending_actions(≤30),recent_plans(≤3),ai_log_tail(80)}` — بدون کلید |
| POST | `/ai/actions/confirm-all` | AiAction@confirmAll | 30,1 | — (۲۰ تای اول) | `{ok,message,details[]}` |
| POST | `/ai/actions/reject-all` | AiAction@rejectAll | 30,1 | — | `{ok,message}` |
| POST | `/ai/actions/{action}/confirm` | AiAction@confirm | 30,1 + owner403 | — | `{ok,deduped?,message?}` / 422 |
| POST | `/ai/actions/{action}/reject` | AiAction@reject | 30,1 + owner403 | — | `{ok,deduped?,message?}` |
| POST | `/ai/plans/{plan}/confirm-structure` | AiPlan@confirmStructure | 30,1 + owner403 | — | `{ok,deduped?,plan?}` / 422 |
| POST | `/ai/plans/{plan}/confirm-phase` | AiPlan@confirmPhase | 30,1 + owner403 | `phase?, run_all?` | `{ok,deduped?,plan?}` / 422 |
| POST | `/ai/plans/{plan}/cancel` | AiPlan@cancel | 30,1 + owner403 | — | `{ok,message}` |
| GET/POST/... | `/ai/conversations...` | AiChat@{conversations,createConversation,getConversation,renameConversation,deleteConversation,clearConversation} | auth + owner403 (به‌جز لیست/ساخت که اسکوپ‌اند) | `label?/label*≤120` | JSON ردیف/ok |
| …نوت‌AI… | `notes/send-to-ai`, `notes/{note}/extract (GET/POST)`, `note-subjects/{subject}/summarize` | NoteAiController | auth + Policy view/update | `collection_id?/mode?` | redirect-back / JSON extract / view |

---

## ۱۵. صفحه چت (`index.blade.php`) — UI/UX فعلی

- هدر ۵۶px: آواتار + `Lina` + وضعیت (`agent_ready`؟) + تاگل Chat/Agent + سوییچ پروایدر (روی `quickSwitch`)؛ سایدبار ۲۴۴px: دکمه New + لیست کانورسیشن (rename inline؟/delete/clear)؛ ستون اصلی: حباب‌ها (user راست / assistant چپ)، مارک‌داون با `marked` (CDN) + هایلایت `hljs` (CDN) + دکمه کپی کد؛ تایپینگ «Lina is thinking…»؛ شمارنده کاراکتر (سقف ۸۰۰۰)؛ chips پیشنهادی؛ کارت proposal (جدول rows + هشدار قرمز impact برای delete + Confirm/Cancel + نمایش انقضا + Confirm-all)؛ کارت plan (stepper فازها با دکمه هر فاز + run-all + cancel)؛ کارت workout-import (preview + لینک `workouts.imports.show`)؛ حالت بن‌بست بی‌کلید (متن «حالت آفلاین خواندنی»)؛ ریسپانسیو: سایدبار کشویی موبایل؛ `main` فول‌ارتفاع (topnav/footer مخفی)؛ ویجت تایم‌ترکر با CSS بالاتر برده شده تا روی دکمه ارسال نیفتد.
- JS: `fetch` به `/ai/stream` با parse خط `data:`، رندر تدریجی، مدیریت proposal/plan packets فقط در agent، `console.warn` برای ignore در chat، `AbortController`؟ ( enter-to-send، retry؟ — رفتار دقیق در فایل)؛ `GET /ai/debug` از فرانت صدا زده می‌شود (خط ۱۱۲۸).

## ۱۶. صفحه تنظیمات (`settings.blade.php`) — فیلدها و رفتار

- کارت «Default AI»: سلکت `default_provider` (گزینه `-- Auto (first enabled) --` + همه داخلی/کاستوم با ✓ فعال) + سلکت `default_model` (با JS بر اساس پروایدر پر می‌شود) + توضیح fallback.
- کارت «Providers & API Keys»: به‌ازای هر ۵ داخلی: بج `Enabled/Disabled` + بج `Default` + `base_url` + اینپوت `password` با مقدار masked (`••••last4`) + چک‌باکس `Clear` (فقط اگر کلید دارد) + سلکت per-provider model (همه مدل‌های کاتالوگ با `label — id`)؛ submit منطق: masked→skip، خالی→skip، clear→null، مقدار جدید→ذخیره (رمزنگاری خودکار)؛ مدل فقط اگر `validateModel` پاس شود.
- کارت «Custom Providers»: جدول ردیف‌ها (label/type/base_url/model/enabled + دکمه‌های Test/Use-as-default/Edit/Delete) + فرم افزودن (label/type/base_url/model/api_key/enabled)؛ `base_url` با `endpointFor` نرمالایز می‌شود.
- نکته: دکمه Test فقط برای کاستوم است؛ برای داخلی Test نیست.

---

## ۱۷. لاگینگ و دیباگ فعلی

- `AiLogger::log/error(event, context)`: کانال `ai` → `storage/logs/ai.log` (driver `single`، سطح از `LOG_LEVEL`)؛ هرگز throw نمی‌کند (fallback به کانال default).
- `sanitize`: redact کلیدهای `key/api_key/Authorization` + برش `message*_preview/reply*/text/content` به ۳۰۰ کاراکتر + برش `args/resolved/preview/structure` به ۸۰۰ کاراکتر (`…[truncated]`).
- `newRequestId() = 'ai_'+8char`؛ ایونت‌ها: `request.received/resolved/invalid/offline_fallback/no_provider/context.failed/completed/failed`، `stream.openai_start/sync_fallback_provider`، `agent.blocked_non_openai`، `provider.tool_calls/empty_response`، `tool.proposed/proposal_capped/proposal_invalid`، `plan.*`، `action.*` — همه با `user_id/rid/provider/model/mode`.
- `tail(80)`: خوانش کل فایل با `file()` و ۸۰ خط آخر (newest-first نیست، همان ترتیب فایل برش‌خورده)؛ هرگز throw نمی‌کند.
- `GET /ai/debug`: resolved/enabled/agent_ready + `recent_pending_actions(≤MAX_OPEN: id,tool,status,expires_at,created_at)` + `recent_plans(≤3)` + `ai_log_tail(80)` — **فیلتر per-user ندارد** (خطوط همه کاربران در فایل مشترک است).
- لاگ‌های پراکنده `Log::info/warning/error` هم در `laravel.log` هست (`ai.tool.proposed/executed/rejected`, `ai.plan.*`, `AI chat response`, `stream read error`...).

---

## ۱۸. امنیت و ایزولاسیون (فقط بخش AI)

- ✅ همه خواندن/نوشتن AI اسکوپ `user_id` است: کانورسیشن‌ها (`where user_id` + `abort_if 403`)، pending/plans/providers (owner check)، ولیدیتورهای ابزار (`Model::where(id).where(user_id)`)، اجرای ابزار (`user_id` اجباری)، `NoteAiController` با Policy.
- ✅ کلیدها هرگز به فرانت/لاگ نمی‌روند (masked + sanitize + debug-safe).
- ⚠️ `/ai/debug` لاگ سراسری می‌دهد (بخش ۱۷) — برای مولتی‌یوزر باید اسکوپ شود.
- ⚠️ `history` از کلاینت می‌آید (اعتماد) — سرور باید از DB بخواند (وضع فعلی این‌طور نیست).
- ⚠️ `chat/stream` بدون throttle/سقف روزانه‌اند (فقط confirmها throttle دارند).
- ⚠️ `verify => false` در `postJson` و `streamOpenAi` (MITM).
- ⚠️ `project_add_member` با LIKE نام (شخص اشتباه + enumeration).
- ⚠️ fallback `.env`: کاربر بی‌کلید از کلید سراسری ادمین مصرف می‌کند (هزینه).
- ℹ️ جدول مجاور غیر-AI (Task/Project/File/Checklist وب) چک مالکیت ندارند — خارج از اسکوپ این سند ولی روی آیتم‌های ساخته‌شده توسط AI اثر می‌گذارند.

---

## ۱۹. رفتار توکن/هزینه (اعداد فعلی کد)

| پارامتر | مقدار |
|---|---|
| ورودی کاربر | `message ≤ 8000` کاراکتر |
| history کلاینت | `≤ 40` نوبت، هرکدام `≤ 8000` |
| کانتکست | **نامحدود** (همه رکوردها، بدون limit؛ نوت ۱۲۰ کاراکتر/هرکدام) |
| system AGENT | ~۲هزار توکن ثابت در هر پیام agent (حتی «سلام») |
| خروجی | ۲۰۴۸ (عادی) / ۴۰۹۶ (tools) / ۱۶۰۰۰ (workout)؛ Gemini/Anthropic ‏۲۰۴۸؛ NoteAi ‏۲۰۰۰–۳۰۰۰، temp چت ۰.۷ / نوت ۰.۳ |
| NoteAi context | ۶۰۰۰۰ (collection) / ۵۰۰۰۰ (subject)؛ کالکشن `limit 200` نوت |
| بدون متریک | جدول `ai_usages` وجود ندارد؛ توکن واقعی از پروایدر خوانده/ذخیره نمی‌شود |

---

## ۲۰. تست‌ها و گپ‌های مشاهده‌شده (برای AI بازبین — بدون پیشنهاد راه‌حل)

تست‌های موجود (۷ فایل): `AiToolsTest` (validate/execute تکی)، `AiTaskResolveTest` (resolve با title)، `AiPlansTest` (سقف‌ها، فلو سلسله‌مراتب و شاخه routines، mismatch فاز، run_all، ۴۰۳/انقضا)، `AiModesTest` (الگوی `Http::fake`)، `AiMultiProjectTest`، `AiCollabReportTest`، `AiPageRenderTest` (رندر بلید + باز/بسته بودن style). راهنما می‌گوید ۶۴ تست سبز با MySQL `127.0.0.1:3306/task_test`.

گپ‌های عینی (فقط مشاهده، بدون تجویز):
1. اعداد سقف در سه‌جا تکرار شده‌اند: مدل (`AiPlan`/`AiPendingAction`)، ولیدیتور، system prompt — امکان ناهمگامی.
2. `docs/PROJECT-GUIDE.md:94` می‌گوید «۱۶ ابزار» و «سقف ۵ باز» ولی کد فعلی ۲۲ ابزار و `MAX_OPEN=30` است (داک قدیمی).
3. `AiMessage` ستون `model` دارد ولی `provider/request_id/tokens/feedback` ندارد.
4. `is_default` در `ai_providers` نوشته می‌شود ولی خوانده نمی‌شود (default از `ai_settings` است).
5. `clearConversation` پیام‌ها را hard-delete می‌کند (audit از بین می‌رود).
6. `LinaFallbackBrain` فارسی محدود؛ دیتاست `{name}` = نام کاربر در intro.
7. system prompt ادعای `created by {user}` می‌کند.
8. CDN خارجی در چت (بدون اینترنت می‌شکند)؛ `marked` بدون sanitize قابل‌مشاهده در کد.
9. کاتالوگ مدل هاردکد با نام‌های آینده‌دار (مثل `gpt-5.6-*`) — با deprecate پروایدر می‌شکند.
10. `confirmAll/rejectAll` سقف ۲۰ دارند ولی `MAX_OPEN=30` است (۱۰ تای آخر جا می‌مانند).

---

## ۲۱. سؤالات برای AI بازبین (پاسخ بده)

1. با حفظ BYOK و Blade، چه ۱۰ ریسک/اتلاف مهم‌تر است (severity + file:line + فیکس یک‌خطی)؟
2. چه سقف‌های کانتکست/هیستوری/خروجی پیشنهاد می‌دهی (اعداد مشخص) و صرفه‌جویی تقریبی؟
3. مسیریابی ابزار (فقط زیرمجموعه مرتبط به مدل) را چطور طراحی می‌کنی بدون شکستن `plan/workout`؟
4. برای Gemini/Claude: native function-calling یا مخفی‌کردن Agent؟ چه معیار تصمیم؟
5. کدام ۶–۸ ابزار جاافتاده (postpone/schedule/snooze/file_attach/time_*) را اول اضافه می‌کنی (اسکچ validate/preview/execute)؟
6. طراحی «اسم کاستوم دستیار»: چه ستون/کانفیگ/هلپر و چه تغییر در prompt/dataset/i18n بدون شکستن چت‌های قدیمی؟
7. لاگ/مانیتورینگ beta: چه اسکیمای `ai_request_logs` و چه ۵ نمودار برای تسترها؟
8. چه ۵ بهبود UI چت (استریم/attach/Quick Actions FA/ویزارد BYOK) بیشترین اثر را دارد؟

---

*پایان سند. مبنای همه اعداد: خوانش مستقیم فایل‌های بخش ۱.*
