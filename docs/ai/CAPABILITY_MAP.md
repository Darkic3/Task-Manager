# نقشه قابلیت‌های AI ‏(Lina) — استخراج‌شده از کد

> **ماهیت سند:** توصیف وضعیت فعلی کد، نه رفتار مطلوب. هر ادعا سندِ `file:line` دارد.
> مبنا: `app/Services/AiToolService.php:30` (`TOOLS` ‏— **۲۴ ابزار**)، ‏`TOOL_RISK (:84)`.
> رفتار مطلوب و شکاف‌ها در `AI_BEHAVIOR_CONTRACT.md` است.
> آخرین تطبیق با کد: فاز ۲ (شاخه `feature/ai-edition`).

## ۱. قواعد سراسری (همه ابزارها)

| موضوع | قاعده | شاهد |
|---|---|---|
| رجیستری | `AiToolService::TOOLS` ‏— ۲۴ نام | `AiToolService.php:30` |
| نوع عملیات | `TOOL_RISK`: ‏`READ` فقط برای `report_generate` و `workspace_search`؛ بقیه `WRITE` (برخی `+DESTRUCTIVE`/`+SENSITIVE`) | `AiToolService.php:84` |
| نیاز به تأیید | `requiresConfirmation() = !isReadOnly()` — هر ۲۲ ابزار غیرREAD نیازمند تأیید، بدون استثنا | `AiToolService.php:118، 134` |
| پایپ‌لاین پیشنهاد | allowlist قابلیت → envelope (شکل/حجم) → security → normalize → شکل schema → کسب‌وکار → مالکیت → وضعیت → سیاست | `ToolPipeline.php:48` (`validateForProposal`) |
| اعتبارسنجی مجدد اجرا | همان مراحل روی خوانش تازه از دیتابیس؛ `resolved` زمان پیشنهاد دور ریخته می‌شود، `preview` هرگز به اجرا نمی‌رسد | `ToolPipeline.php:128`؛ `AiActionController.php:23` |
| اجرای اتمی | `lockForUpdate` + تراکنش + claim شرطی `pending→executing`؛ اجرای تکراری/هم‌زمان دوباره side-effect نمی‌سازد | `AiActionController.php:337` (`runAction`) |
| کلید idempotency | `sha256("v1\|user\|conv\|tool\|canonicalJson(resolved)")`؛ پلن از `title+structure`؛ چرخش کلید پس از برخورد با ردیف terminal | `ToolPipeline.php:364` |
| سقف‌ها | هر نوبت ۲۰ فراخوانی، حداکثر ۳ تکرار یکسان (`RATE_LIMITED`/`LOOP_DETECTED`)؛ ۳۰ اکشن باز، انقضای ۱۵ دقیقه؛ پلن: ۵ پروژه/۳ زیرپروژه/۳۰ تسک/۱۰۰ ساب‌تسک/۱۰ یادآور/۱۰ یادداشت/۷ روتین، سقف کل ۲۰۰؛ بدنه ۴۸KB و عمق ۱۲ | `ToolPipeline.php:30`؛ `AiPendingAction.php:50`؛ `AiPlan.php:17`؛ `ToolSchema.php:20` |
| امنیت | کلیدهای هویتی (`user_id`، `auto_confirm`، `skip_confirmation` و…) در propose و execute هر دو پس زده می‌شوند؛ مدل به‌تنهایی مجوز اجرا صادر نمی‌کند | `AiSecurity.php:23`؛ `ToolPipeline.php:190` |
| خطاها | `ToolError`: ‏`validation_error`، `authorization_error`، `not_found`، `conflict`، `duplicate_target`، `ambiguous_target`، `expired_action`، `payload_too_large`، `unknown_tool`، `tool_not_routed`، `loop_detected`، `rate_limited`، `not_allowed_for_intent`، `execution_error` | `ToolError.php:14` |

## ۲. تسک (۴ ابزار)

### `task_create` — ‏`WRITE`
- **هدف:** ساخت تسک (`parent_id` → ساب‌تسک، پروژه به ارث می‌رسد). تعریف: `AiToolService.php:150`؛ اجرا: `:2831`.
- **آرگومان‌ها:** الزامی `title` (≤۲۵۵)؛ اختیاری `project_id`، `project`، `parent_id`، `due_date`، `priority`، `status`، `description` (≤۲۰۰۰).
- **موجودیت‌ها:** `Task` (+ `Project`/تسک والد). **پیش‌نیاز:** مالکیت والد/پروژه در صورت ارجاع.
- **کنترل‌ها:** normalize نام/شناسه، بررسی مالکیت پروژه+والد (`ToolPipeline.php:218`).
- **تأیید:** بله (ساخت وضعیت کاربرمحور). **اثر جانبی:** یک سطر `tasks`. **Undo:** ندارد.
- **خطاها:** `validation_error`، `not_found` (والد/پروژه)، خطاهای استاندارد پایپ‌لاین.
- **تست‌ها:** `AiConfirmationTest`، `AiToolsTest`، `AiTraceRunTest` و ۹ فایل دیگر.

### `task_update` — ‏`WRITE` — **قابل undo (۱۰ دقیقه)**
- **هدف:** ویرایش تسک با `id` یا عنوان+محدوده پروژه (`resolveTask`: ‏`AiToolService.php:1481`).
- **آرگومان‌ها:** یکی از `id|task` الزامی؛ بقیه اختیاری (`title`، `due_date`، `priority`، `status`، `description`).
- **کنترل‌ها:** مالکیت تسک؛ **عنوان مبهم → خطای `conflict`، نه حدس** (`:1531`).
- **اثر جانبی:** به‌روزرسانی ستون‌ها (+ `completed_at` عند تغییر وضعیت). **Undo:** بله، تصویرِ قبل ذخیره می‌شود (`AiUndoService.php:71`).
- **تست‌ها:** `AiConfirmationTest`، `AiSecurityBoundaryTest`، `AiTaskResolveTest`.

### `task_complete` — ‏`WRITE` — **قابل undo (۱۰ دقیقه)**
- **هدف:** تکمیل تسک؛ هرگز با این ابزار «ناتکمیل» نمی‌شود (`AiToolService.php:278`).
- **آرگومان‌ها:** `id|task` (+ محدوده پروژه). **پیش‌نیاز:** مالکیت + وضعیت غیرتکمیل (وگرنه `conflict`؛ در propose و execute هر دو بررسی می‌شود).
- **اثر جانبی:** `status=completed, completed_at=now`. **Undo:** بله.
- **تست‌ها:** `AiConfirmationTest`، `AiTaskResolveTest`، `AiTraceContractTest` و دیگران.

### `task_delete` — ‏`WRITE+DESTRUCTIVE`
- **هدف:** حذف تسک با پیش‌نمایش اثر بر ساب‌تسک‌ها (`previewTaskDelete`: ‏`AiToolService.php:2741`).
- **آرگومان‌ها:** `id|task`. **تأیید:** بله + پیش‌نمایش خطر. **اثر جانبی:** حذف سطر (آبشاری)؛ **برگشت‌ناپذیر، undo ندارد** (تعمداً؛ `AiUndoService.php:11`).

## ۳. یادآور (۳ ابزار)

### `reminder_create` — ‏`WRITE`
- الزامی `title`؛ اختیاری `date`، `time`، `priority`، `description`، `location`. تاریخ فارسی از عنوان استخراج می‌شود (`validateReminderCreate`: ‏`AiToolService.php:852`). اثر: یک سطر `reminders`. Undo ندارد.

### `reminder_complete` — ‏`WRITE` — **قابل undo (۱۰ دقیقه)**
- الزامی `id`. پیش‌نیاز: مالکیت + ناتکمیل (وگرنه `conflict`). اثر: `markAsCompleted()`.

### `reminder_delete` — ‏`WRITE+DESTRUCTIVE`
- الزامی `id`. حذف سطر؛ undo ندارد.

## ۴. یادداشت (۳ ابزار)

### `note_create` — ‏`WRITE`
- الزامی `title` (≤۲۵۵) و `content` (≤۲۰۰۰۰)؛ اختیاری `category`. اثر: یک سطر `notes`. Undo ندارد.

### `note_update` — ‏`WRITE`
- الزامی `id`؛ اختیاری `title/content/category`. پیش‌نیاز: مالکیت (وگرنه `not_found`). Undo ندارد.

### `note_delete` — ‏`WRITE+DESTRUCTIVE`
- الزامی `id`. حذف سطر؛ undo ندارد.

## ۵. پروژه (۲ ابزار)

### `project_create` — ‏`WRITE`
- الزامی `name`؛ اختیاری `description`، `status`، `parent/parent_id` (عمق < ۵).
- **نگهبان تکراری:** نام مشابه → `duplicate_target` + `candidates[{id,name,path}]` (حداکثر ۳) (`AiToolService.php:1034`). اثر: یک سطر `projects`. Undo ندارد.

### `project_add_member` — ‏`WRITE+SENSITIVE`
- **هدف:** افزودن همکار به پروژه‌ی تحت مالکیت؛ تنها ابزاری که کلید `user` در آن مجاز است (`AiToolService.php:553`).
- **آرگومان‌ها:** مکان‌یاب `project|project_id` + `user` (ایمیل/نام/شناسه) + `role` (پیش‌فرض `member`).
- **کنترل‌ها:** تفکیک دقیق/جزئی کاربر (سقف ۵ کاندیدا)؛ افزودنِ خود رد می‌شود؛ ابهام → `ambiguous_target` + کاندیدا (فقط `id+name`)؛ اعتبارسنجی مجدد عضو در لحظه اجرا.
- **تأیید:** بله + پیش‌نمایش صریح (اشتراک‌گذاری داده). **اثر جانبی:** upsert جدول میانی `project_user`. Undo ندارد.

## ۶. لینک و گزارش (۲ ابزار)

### `note_link` — ‏`WRITE`
- اتصال یادداشت به پروژه/تسک/یادداشت (`target_type` الزامی). **هر دو سمت باید تحت مالکیت باشند** (`ToolPipeline.php:255`)؛ خودارجاعی رد می‌شود. اثر: یک سطر لینک. Undo ندارد.

### `report_generate` — ‏`READ` — **بدون تأیید**
- خلاصه فقط‌خواندنی فضای کاری (بازه `today/week/month`، پیش‌فرض `week`)؛ هیچ سطری نمی‌سازد/تغییر نمی‌دهد (`execReportGenerate`: ‏`AiToolService.php:2983`). بدون اثر جانبی؛ undo موضوعیت ندارد.

## ۷. چک‌لیست (۲ ابزار)

### `checklist_add` — ‏`WRITE`
- الزامی `name` + مکان‌یاب تسک (`task_id|task`). اثر: یک سطر `checklist_items`. Undo ندارد.

### `checklist_toggle` — ‏`WRITE`
- الزامی `id`؛ مالکیت از طریق تسک مالک بررسی می‌شود (`ToolPipeline.php:265`). اثر: چرخش پرچم تکمیل. Undo ندارد.

## ۸. روتین (۴ ابزار)

### `routine_create` — ‏`WRITE`
- الزامی `title` (+ `frequency` با استانداردسازی مترادف‌ها)؛ هفتگی → `days` الزامی، ماهانه → `month_days`، هر-N-روز → بازه ۲–۶۰؛ حالت ردیابی مقداری → `value_kind` الزامی؛ حداکثر ۲۰ گام. اثر: روتین + سطرهای گام. Undo ندارد.

### `routine_complete` — ‏`WRITE` — **قابل undo (۱۰ دقیقه)**
- الزامی `id` (+ `date` اختیاری، پیش‌فرض امروز). پیش‌نیاز: مالکیت + عدم ثبت برای آن تاریخ (وگرنه `conflict`)؛ عادت اجتنابی در اجرا رد می‌شود. اثر: یک سطر `routine_completions`؛ undo آن را حذف می‌کند.

### `routine_delete` — ‏`WRITE+DESTRUCTIVE`
- الزامی `id`. پیش‌نمایش اثر بر تاریخچه ثبت‌شده؛ سوابق به‌عنوان تاریخچه می‌مانند. undo ندارد.

### `routine_log` — ‏`WRITE`
- الزامی `value` (عدد یا `HH:MM` برای نوع زمانی) + مکان‌یاب روتین؛ اختیاری `date/item/set_no`.
- پیش‌نیاز: روتینِ ردیابی‌شونده. **اثر مضاعفِ افشاشده:** ثبت مقدار ممکن است روتین را خودکار تکمیل کند. Undo ندارد.

## ۹. پلن و برنامه تمرینی (۲ ابزار)

### `plan_propose` — ‏`WRITE+SENSITIVE`
- **هدف:** پیشنهاد یکجای ساخت چندسطحی (۵ پروژه یا ۱ پروژه+۳ زیرپروژه، تسک/یادآور/یادداشت/روتین، حداکثر ۲۰۰ قلم)؛ هرگز به‌عنوان یک اکشن اجرا نمی‌شود — پس از تأیید ساختار، فازبه‌فاز اجرا (`executePlanPhase`: ‏`AiToolService.php:2426`).
- **آرگومان‌ها:** الزامی `title`؛ آرایه‌های اختیاری با سقف؛ اتصال به رکورد موجود فقط با `project_id` صریح یا تطبیق قوی نام.
- **تأیید:** دو مرحله‌ای (تأیید ساختار + اجرای فازها). Undo ندارد.

### `workout_plan_propose` — ‏`WRITE`
- **هدف:** برنامه تمرینی ساخت‌یافته ۷روزه؛ برای هر متن تمرینیِ الصاق‌شده **اجباری** است، نه `plan_propose`/روتین/تسک.
- **آرگومان‌ها:** الزامی `title` + `days[1..7]` (هر روز: عنوان، نوع، تمرین‌ها با ست/تکرار/RIR/استراحت/تمپو/سیرکویت)؛ اختیاری `week_number/start_date/goal/rules[≤30]`.
- **اثر:** پس از تأیید فقط یک `WorkoutImport(PREVIEW)`؛ اجرای مستقیم همیشه `ok:false` برمی‌گرداند. Undo ندارد.

## ۱۰. جست‌وجو و تعامل (۲ ابزار)

### `workspace_search` — ‏`READ` — **بدون تأیید**
- **هدف:** جست‌وجوی فارسی‌پسندِ پیش‌ازساخت در پروژه/تسک/یادداشت/روتین/یادآورِ تحت مالکیت؛ شناسه+نام برای اتصال برمی‌گرداند.
- الزامی `query`؛ اختیاری `kinds` و `limit` (۱–۲۰، پیش‌فرض ۸). بدون اثر جانبی.

### `ask_user_choice` — ‏`WRITE` (طبقه تعامل)
- **هدف:** پرسش ۲–۵ گزینه‌ای مشخص به‌جای حدس؛ هر گزینه `{tool,args}` دقیق دارد و پس از انتخاب، از مسیر عادی پیشنهاد می‌گذرد.
- الزامی `question` (≤۵۰۰) + `options[2..5]`؛ ابزار گزینه باید ابزار واقعی و غیر از خودش باشد. **خودش هیچ تغییری اجرا نمی‌کند** (`execute` فقط «در انتظار کاربر» برمی‌گرداند؛ `AiToolService.php:664`). Undo ندارد.

## ۱۱. شکاف‌های تست ابزارها (وضعیت موجود)
- پوشش مستقیم برای هر ۲۴ ابزار حداقل در یک فایل تست وجود دارد (فهرست بالا).
- **کم‌پوشش‌ها:** `reminder_complete`/`note_update`/`checklist_add` هر کدام عمدتاً تک‌فایل؛ `routine_log` فقط `RoutineTrackingTest`؛ مسیر `confirmAll` روی `ask_user_choice` (اجرا به‌جای `choice`) تست ندارد؛ تطبیق نامِ روتین (first-LIKE-match خاموش) تست منفی ندارد.
- **ناهماهنگی مستندات قدیمی:** `docs/ai/PHASE_0_CURRENT_STATE_AUDIT.md:164` نام `workout_propose` را ذکر می‌کند که در رجیستری نیست؛ نام canonical ‏`workout_plan_propose` است.

## ۱۲. پیشنهادهای آینده (نیازمند تغییر کد — در این فاز انجام نشد)
1. یکسان‌سازی پاسخ ابهام تسک (`conflict` متنی) با الگوی `candidates` در عضو/پروژه.
2. تصمیم‌گیری درباره طبقه `WRITE` برای `ask_user_choice` (کارت تأیید برای سؤالِ بدون اثر؟).
3. تست منفی برای تطبیق خاموش روتین و پروژه (first-match).
