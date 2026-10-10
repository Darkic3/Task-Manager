# Tool/Capability Routing — Lina (ایجنت)

هدف: به‌جای ارسال هر ۲۲ تعریف Tool در هر درخواست، فقط قابلیت‌های مرتبط با
Intent و درخواست فعلی در اختیار مدل قرار می‌گیرد. محدودسازی واقعاً در سمت
سرور اعمال می‌شود (payload + allowlist)، نه صرفاً در Prompt.

## ۱. قابلیت‌های فعلی (۲۲ ابزار)

| Tool | دسته | عملیات | توضیح | دسترسی |
|---|---|---|---|---|
| `task_create` | task | WRITE | ساخت تسک (subtask با `parent_id`) | owner-only |
| `task_update` | task | WRITE | ویرایش تسک (id/title + scope پروژه) | owner-only |
| `task_complete` | task | WRITE | تکمیل تسک | owner-only |
| `task_delete` | task | WRITE+DESTRUCTIVE | حذف تسک (پیش‌نمایش اثر) | owner-only |
| `reminder_create` | reminder | WRITE | ساخت یادآوری (date/time) | owner-only |
| `reminder_complete` | reminder | WRITE | تکمیل یادآوری | owner-only |
| `reminder_delete` | reminder | WRITE+DESTRUCTIVE | حذف یادآوری | owner-only |
| `note_create` | note | WRITE | ساخت یادداشت | owner-only |
| `note_update` | note | WRITE | ویرایش یادداشت (ID) | owner-only |
| `note_delete` | note | WRITE+DESTRUCTIVE | حذف یادداشت (ID) | owner-only |
| `note_link` | link | WRITE | اتصال یادداشت به project/task/note | owner-only، هر دو سمت |
| `project_create` | project | WRITE | ساخت پروژه (زیرپروژه با parent) | owner-only |
| `project_add_member` | project | WRITE+SENSITIVE | افزودن همکار (email/name/id) | پروژه‌ی خودی |
| `checklist_add` | checklist | WRITE | افزودن آیتم چک‌لیست به تسک | owner-only (از طریق تسک) |
| `checklist_toggle` | checklist | WRITE | تغییر وضعیت آیتم چک‌لیست | owner-only (از طریق تسک) |
| `routine_create` | routine | WRITE | ساخت روتین تکرارشونده | owner-only |
| `routine_complete` | routine | WRITE | انجام روتین در یک تاریخ | owner-only |
| `routine_delete` | routine | WRITE+DESTRUCTIVE | حذف روتین (پیش‌نمایش سوابق) | owner-only |
| `routine_log` | routine | WRITE | ثبت عدد tracking | owner-only |
| `report_generate` | report | READ | خلاصه‌ی read-only (today/week/month) | owner-only، read-only |
| `plan_propose` | plan | WRITE+SENSITIVE | پیشنهاد بیلد چندپروژه‌ای (یکجا) | فازبه‌فاز پس از تأیید |
| `workout_plan_propose` | workout | WRITE | پیشنهاد پلن ۷روزه‌ی تمرینی | via import preview پس از تأیید |

- تنها ابزار READ: `report_generate`. بقیه نیازمند تأیید صریح کاربر.
- منبع حقیقت دسته‌بندی: `App\Services\AiTooling\ToolCapabilityRegistry`
  (`CATEGORY_TOOLS` + `CAPABILITIES`). `TOOL_GROUPS`/`TOOL_RISK` در
  `AiToolService` برای سازگاری عقبرو حفظ شده.

## ۲. انتخاب Tool (Intent + Context + Provider)

- `AiIntentRouter::route()` همان طبقه‌بند قبلی است (تغییر رفتار نداده؛ فقط
  `tool_filter` برای mutation حالا entity-aware است).
- `AiTooling\ToolCapabilityRouter::select($route, $context)` تصمیم نهایی:
  - `mode` و `provider_type` از کانتکست: حالت `chat` یا پرووایدر غیر
    OpenAI-compatible (gemini/anthropic) → هیچ ابزاری (`[]`).
  - `general_chat` با اطمینان بالا → `[]` (بدون workspace/tools).
  - `workspace_query`/`report` → فقط `report_generate`.
  - `workout_plan` → فقط `workout_plan_propose`.
  - `task_mutation` → اجتماع دسته‌های شناسایی‌شده از
    `entities.objects` (+ همیشه `report`)؛ بدون object → مجموعه‌ی broad
    قبلی (single+report). درخواست ترکیبی «تسک+یادآوری+یادداشت» هر سه دسته
    را می‌گیرد ولی `plan/workout` سنگین بیرون می‌ماند.
  - `plan_build` → fail-open (`null` = ست کامل؛ پلن ممکن است همه‌ی ابعاد را
    یکجا ببندد).
  - `ambiguous` یا اطمینان پایین → مجموعه‌ی امن READ-only
    (`report_generate`) + سؤال شفاف‌سازی (`hintFor` در system prompt).
  - اطمینان متوسط (غیرمبهم) → fail-open (`null`) برای حفظ recall.
- سیگنال‌های صریح کانتکست (مثل `has_note_attachments`) فقط دسته را عریض
  می‌کنند؛ مالکیت downstream بررسی می‌شود.

## ۳. اعمال سمت سرور (نه Prompt)

1. **Outbound:** `AiChatController::toolsForResolved()` فقط definitions
   منتخب را به payload می‌دهد (`tools.routed` لاگ می‌شود). برای
   non-openai هیچ `tools` ارسال نمی‌شود (رفتار قبلی Agent Mode حفظ شده).
2. **Inbound:** همان allowlist در `callProviderSyncWithTools` (sync) و
   `emitToolProposals` (streaming) روی `tool_calls` مدل چک می‌شود؛ ابزار
   خارج از لیست با کد `tool_not_routed` رد و surface می‌شود (نه silent).
3. **Proposal:** `ToolPipeline::validateForProposal(..., ['allowed_tools'])`
   ابزار نامرئی را با `not_allowed_for_intent` رد می‌کند. فراخوانی مستقیم
   بدون allowlist (legacy) رفتار قبلی را دارد.
4. Routing هرگز جایگزین Authorization/validation/confirmation نیست:
   مالکیت، وضعیت، policy و تأیید کاربر مثل قبل روی همه‌ی mutationها اجرا
   می‌شود؛ ابزار روت‌شده ولی foreign/conflict همچنان رد می‌شود.

حفظ‌شده‌ها: سقف `MAX_TOOL_CALLS_PER_TURN=20`، `MAX_IDENTICAL_PER_TURN=3`
(loop cut)، سقف pending (`MAX_OPEN`)، سقف plan، idempotency، و fallback
پرووایدرهای بدون Agent Mode.

## ۴. مصرف توکن (قبل/بعد، تخمین chars/4 روی JSON definitions)

Full (۲۲ ابزار): ‎~4578 توکن (~18KB).

| درخواست | Intent | ابزار ارسالی | بعد | صرفه‌جویی |
|---|---|---|---|---|
| سلام | general | ۰ | ~۱ | ۱۰۰٪ |
| گزارش بده | report | ۱ | ~۱۰۱ | ۹۷.۸٪ |
| «یه تسک بساز» | mutation | ۷ | ~۱۰۴۸ | ۷۷.۱٪ |
| یادآوری | mutation | ۴ | ~۳۶۵ | ۹۲٪ |
| ترکیبی تسک+یادآوری+یادداشت | mutation | ۱۴ | ~۱۶۷۲ | ۶۳.۵٪ |
| برنامه تمرینی | workout | ۱ | ~۵۷۱ | ۸۷.۵٪ |
| سه پروژه | plan (fail-open) | FULL | ~۴۵۷۸ | ۰٪ |
| مبهم «این» | ambiguous (safe) | ۱ | ~۱۰۱ | ۹۷.۸٪ |

تکرار اندازه‌گیری:
`ToolCapabilityRouter::tokenComparison($route, $svc, $ctx)` —
تست: `AiToolCapabilityRoutingTest::test_routing_saves_tokens_on_narrow_intents`.

## ۵. تست‌ها

- `tests/Feature/AiToolCapabilityRoutingTest.php` (۱۹ تست):
  پوشش رجیستری، پنهان‌ماندن ابزار نامرتبط، union چنددسته‌ای، safe-set
  مبهم، گیت provider/mode، رد off-route (sync+stream، بدون ساخت pending)،
  بقای turn-cap/loop، و صرفه‌جویی توکن.
- `tests/Feature/AiIntentRouterTest.php`: ambiguous حالا safe-set امن
  (READ-only) می‌گیرد؛ medium غیرمبهم همچنان fail-open.
