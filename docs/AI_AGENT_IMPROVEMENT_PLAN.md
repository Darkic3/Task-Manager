# پلن ارتقای رفتار ایجنت لینا — Search-First، Structure-Aware، Clarification-Driven

> هدف: ایجنت دیگر هرگز بدون جستجو پروژه/تسک نسازد، درخواست کاربر را روی ** نزدیک‌ترین ساختار موجود ** (پروژه ← زیرپروژه ← تسک) مپ کند، و اگر بیش از یک تفسیر معتبر وجود داشت، به‌جای حدس زدن، **به کاربر گزینه بدهد و منتظر بماند.**

> **وضعیت اجرا: ✅ هر ۵ فاز پیاده‌سازی شد** (مهر ۱۴۰۵) — فاز ۰+۲: درخت پروژه‌ها + گارد دی‌دوپلیکیت (`AiSearchBeforeCreateTest`)؛ فاز ۱: `workspace_search`؛ فاز ۳: `ask_user_choice` + `POST /ai/actions/{id}/choice` + کارت انتخاب در چت (`AiSearchAndAskTest`)؛ فاز ۴: قواعد مپ در system prompt؛ فاز ۵: مستندات به‌روز. جزئیات در `docs/AI_SYSTEM.md` (۶.۳ + جدول ابزارها) و `docs/TOOL_CAPABILITY_ROUTING.md`.

## ریشه‌ی مشکل (بررسی‌شده روی کد)

وقتی کاربر گفت «این تسک‌ها را به پروژه Task Manager اضافه کن»، ایجنت پروژه‌ی جدید ساخت. سه علت ساختاری:

1. **مدل ابزار جستجو ندارد.** `AiToolService::TOOLS` هیچ ابزار READ برای جستجوی پروژه/تسک ندارد (فقط `report_generate`). تنها راه دیدن پروژه‌ها، Context Engine است که در آن فقط پروژه‌ی «مچ‌شده» به‌صورت خط نمایش داده می‌شود و بقیه فقط در شمارش خلاصه می‌مانند (`AiContextEngine::projectLines`) — یعنی مدل عملاً «Task Managerِ» موجود را نمی‌دید.
2. **`plan_propose` هیچ دی‌دوپلیکیتی ندارد.** `validatePlanPropose` / `executePlanPhase` نام پروژه‌ها را چک نمی‌کنند و هر گره را بی‌قید و شرط می‌سازند. مدل هم که ساختار را به‌صورت «بساز» می‌فرستد، پروژه‌ی تکراری ساخته می‌شود — **حتی اگر context کامل بود.**
3. **مکانیزم clarification تعاملی وجود ندارد.** `AiIntentRouter` فلگ `requires_clarification` دارد ولی فقط متن ساده‌ی ابهام است؛ `AiPendingAction` فقط confirm/cancel است، نه انتخاب بین گزینه‌ها. مدل مجبور است حدس بزند یا آزادانه «بسازد».

## فاز ۰ — دید ساختار در کانتکست (سریع‌ترین برد)

**فایل:** `app/Services/AiContextEngine.php`

- در پروفایل `agent` (و `planning`)، یک **درخت فشرده‌ی پروژه‌ها** همیشه ارسال شود: همه‌ی پروژه‌ها با `id + نام + عمق + breadcrumb` (مثل `Task Manager > چاپبر`)، نه فقط پروژه‌ی مچ‌شده. حجم واقعی کوچک است (۵۰ پروژه ≈ ۲K chars) و در همان workspace_budget جا می‌شود.
- **پالیسی در system prompt** (`AiChatController::buildContext` / بخش PROMPT):
  1. اگر کاربر گفت «اضافه کن به پروژه X» — هرگز پروژه نساز؛ اول مطمئن شو X وجود دارد.
  2. ساختار سلسله‌مراتبی (پروژه ← زیرپروژه ← تسک/ساب‌تسک، حداکثر ۵ سطح) را رعایت کن؛ نام‌هایی مثل «چاپبر» و «فصل» یعنی زیرپروژه، نه پروژه‌ی ریشه.
  3. اگر درخواست به ≥۲ ساختار ممکن مپ می‌شود، گزینه‌ها را شماره‌گذاری کن و منتظر جواب بمان.

## فاز ۱ — ابزار جستجوی سراسری: `workspace_search`

**فایل‌ها:** `app/Services/AiToolService.php` (تعریف + validator + executor)، `app/Services/AiTooling/ToolCapabilityRegistry.php`

- ابزار جدید READ با مشخصات:
  ```
  workspace_search { query: string, kinds?: ['project','task','note','routine','reminder'], limit?: ≤10 }
  → [{ kind, id, title, path (breadcrumb پروژه), due_date? }]
  ```
- تطبیق فارسی: نرمال‌سازی با همان منطق `AiIntentRouter::normalize` (ی/ک عربی، ZWNJ، تشدید) + `LIKE` روی title/name — یک Helper مشترک بساز تا روتر و جستجو از یک نرمال‌سازی استفاده کنند.
- ثبت در رجیستری: دسته‌ی جدید `search`، عملیات `OP_READ`، بدون confirmation، ورود به `SAFE_FALLBACK` و لیست ابزارهای هر مسیر mutation (الزام «اول جستجو، بعد نوشتن» سمت سرور).
- **دامنه‌ی امنیتی:** فقط entityهای متعلق به `$user->id` — مثل بقیه‌ی ابزارها.

## فاز ۲ — گارد دی‌دوپلیکیت سمت سرور (درمان قطعی باگ گزارش‌شده)

**فایل:** `app/Services/AiToolService.php` + `ToolError.php`

این فاز مستقل از پرامپت عمل می‌کند — حتی اگر مدل هم خطا کند، سرور جلوی ساخت تکراری را می‌گیرد.

- `validateProjectCreate`: نام را fuzzy با پروژه‌های موجود مقایسه کن. اگر match قوی بود → به‌جای ساخت، خطا با کد `project_exists` و **لیست کاندیداها** `{id, name, path}` برگردان. مدل یا به پروژه‌ی موجود وصل می‌شود یا از کاربر می‌پرسد.
- `validatePlanPropose` + `executePlanPhase`: همین دی‌دوپلیکیت به‌ازای هر گره‌ی `project`/`subprojects`/`projects[]`؛ preview پلن مشخص کند هر گره **«استفاده از پروژه‌ی موجود (attach)»** یا **«ساخت جدید»** است.
- آرگومان جدید اختیاری در schema پلن: هر گره‌ی پروژه `project_id?` برای وصل شدن صریح به پروژه‌ی موجود (مکانیزم resolve با نام مثل `task_update`).
- `ToolError` کدهای جدید: `project_exists`, `ambiguous_target`, `not_found_with_candidates` — تا `ToolPipeline` پیام + کاندیداها را به مدل برگرداند.

## فاز ۳ — جریان Clarification و انتخاب گزینه

**فایل‌ها:** مدل جدید یا extension روی `app/Models/AiPendingAction.php`، `app/Http/Controllers/AiActionController.php`، `resources/views/ai/index.blade.php`

- مفهوم جدید «کارت انتخاب»: وقتی validator به `ambiguous_target` برگرداند (مثلاً ۲ پروژه‌ی شبیه «تسک منیجر» و «Task Manager»)، یک ردیف pending از نوع **choice** ساخته می‌شود: سؤال + آپشن‌ها (هر آپشن = payload ادامه‌ی همان tool call با resolved شدن target).
- اندپوینت‌های جدید: `confirm-choice` (ادامه‌ی اکشن با آرگومان‌های انتخاب‌شده — اجرای اتمیک با همان claim/lease فعلی) و `cancel`. انقضا و `MAX_OPEN` مانند pending فعلی.
- فرانت (`ai/index.blade.php`): رندر کارت گزینه‌ها شبیه dock پندینگ فعلی — دکمه‌های فارسی + «گزینه‌ی دیگر».
- برای «چند روش انجام کار» (مثلاً: تسک‌ها به‌صورت ساب‌پروژه یا تسک داخل پروژه‌ی اصلی؟ یادداشت به پروژه یا به تسک؟): همان کارت choice با آپشن‌های ساختاری، تولیدشده از قواعد پرامپت + نتیجه‌ی جستجو.

## فاز ۴ — هوشمندی مپ ساختار

**فایل:** system prompt در `AiChatController` + مستند `docs/AI_SYSTEM.md`

- بخش جدید «Structure Mapping Rules» در پرامپت:
  - لیست دسته‌ای از کاربر (مثلاً ۱۴ آیتم) → یک تسک با ساب‌تسک/چک‌لیست یا چند تسک در زیرپروژه‌ی متناظر، **نه** پروژه‌های جدا.
  - نام‌های مفهومی کاربر («ورک‌اوت»، «یادداشت‌ها») → زیرپروژه زیر نزدیک‌ترین پروژه‌ی موجود.
  - اصل «کمترین ساختار جدید»: اول attach به موجود، بعد زیرپروژه، آخر ساخت ریشه.
- Preview هر اکشن (کارت confirm فعلی) نشان دهد entity به کدام گره‌ی موجود وصل می‌شود (breadcrumb).

## فاز ۵ — تست و داکیومنت

- تست‌های PHPUnit (مسیر `tests/`):
  - گارد دی‌دوپلیکیت پروژه (نام فارسی، ZWNJ، مشابه).
  - `workspace_search`: نرمال‌سازی فارسی، ایزولاسیون بین دو کاربر، limit.
  - پلن: گره‌ی موجود attach می‌شود نه ساخته؛ preview درست.
  - Clarification: ساخت → انتخاب → اجرای اتمیک یک‌باره؛ cancel؛ انقضا؛ دو کلیک همزمان.
- مستندات را به‌روز کن: `docs/AI_SYSTEM.md`، `docs/TOOL_CAPABILITY_ROUTING.md`، `docs/AI_TECH_SPEC.md` — و سپس «آپدیت AI» (همان بند داکیومنت پروژه‌ی خودت) با خلاصه‌ی رفتار جدید.

## ترتیب اجرا و اولویت

| اولویت | فاز | اثر |
|---|---|---|
| ۱ | فاز ۰ + ۲ | باگ پروژه‌ی تکراری را قطعاً حذف می‌کند (حتی بدون UI جدید) |
| ۲ | فاز ۱ | مدل توانایی کشف ساختار را پیدا می‌کند |
| ۳ | فاز ۳ | تعامل human-in-the-loop به‌جای حدس |
| ۴ | فاز ۴ + ۵ | کیفیت مپ و پایداری بلندمدت |

نکته‌ی نگهداری: هر ابزار جدید باید در سه جا ثبت شود — `AiToolService::TOOLS` + schema/validator/executor، `ToolCapabilityRegistry::CAPABILITIES/CATEGORY_TOOLS`، و allowlist مسیرهای router — وگرنه `validate()` رجیستری خطا می‌دهد.
