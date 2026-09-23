# Task Manager + Lina AI — راهنمای تفصیلی پروژه

> کاربرد: مرجع توسعه و ادامه‌دادن فیچرها با AI. استک: Laravel + Blade + SSE + MySQL/SQLite. همه روت‌ها پشت `auth` هستند مگر login.

## ۱. مدل‌های دیتا و روابط

| مدل | جدول | فیلدهای کلیدی | رابطه‌ها |
|---|---|---|---|
| `User` | `users` | name, email, profile fields | hasMany همه‌چیز (projects, tasks, routines, notes, reminders, files, timeEntries) |
| `Project` | `projects` | name, **slug** (کلید روت!), parent_id, sort_order, type, status, budget, start/end_date | children بازگشتی (`childrenRecursive`)، tasks، users (تیم via `project_teams`) |
| `Task` | `tasks` | project_id, parent_id, title, due_date, priority (low/med/high), status (to_do/in_progress/on_hold/in_review/completed), weight, auto_weight, sort_order, estimated_hours, completed_at | project, parent/children بازگشتی، checklistItems، timeEntries |
| `Routine` | `routines` | title, frequency (daily/weekly/monthly/every_n_days), days/weeks/months/month_days (json), every_n_days, time_period, start/end_time + SoftDeletes | completions، checklistItems |
| `RoutineCompletion` | `routine_completions` | routine_id, completed_date, completed_at + یونیک `(routine_id, completed_date)` | — |
| `RoutineChecklistItem` + `RoutineCheckitemCompletion` | — | استپ‌های روتین + تیک روزانه (یونیک item+date) | — |
| `ChecklistItem` | `checklist_items` | task_id, name, completed | task |
| `Reminder` | `reminders` | title, date, time, priority (+urgent), category, is_recurring + recurrence_type/interval, is_completed, snooze_until, tags | — |
| `Note` | `notes` | title, content, category, tags(array), is_favorite, date/time | — |
| `File` | `files` | name, path, type | — |
| `TimeEntry` | `time_entries` | project_id?, task_id?, started_at, ended_at, duration_seconds, paused_seconds, status (running/paused/stopped), description, category | project, task |
| `AiConversation` / `AiMessage` | `ai_conversations` / `ai_messages` | label / role(user/assistant), content, model | conversation→messages |
| `AiPendingAction` | `ai_pending_actions` | tool, args(json), preview(json), status, expires_at (+۱۵ دقیقه), idempotency_key(یونیک) | user, conversation |
| `AiSetting` / `AiProvider` | `ai_settings` / `ai_providers` | کلیدهای رمزنگاری‌شده + مدل هر پروایدر / پروایدر کاستوم (base_url, type, model, api_key) | — |

نکته‌ها: روت‌مدل `Project` با **slug** بایند می‌شود (`getRouteKeyName`) — پس هر `with('project...')` باید `slug` را سلکت کند. حذف روتین **سافت** است (تاریخچه می‌ماند، مخفی می‌شود).

## ۲. فیچرها با جزئیات

### داشبورد `/` (`DashboardController`)
ویجت روتین امروز (با شمار done/total)، تسک‌های اخیر + پروژه‌شان، نوت‌های اخیر، ریمایندرهای آینده، آمار تجمیعی (با group-by، نه N کوئری)، `GET /dashboard/productivity-data?period=week|month|year` برای چارت (تک‌کوئری group-by تاریخ).

### پلنر `/planner?view=day|week&date=` (`PlannerController`)
- تسک‌های روز + overdue (غیرتکمیل‌شده با due_date قبل‌تر) + تاگل سریع (`toggleTask/toggleRoutine/toggleCheckItem` با JSON).
- روتین‌ها به سه سبد تقسیم می‌شوند: امروز / این هفته / این ماه (`splitRoutines`، خالص PHP).
- **حلقه عادت** هر روتین: درصد پایبندی ۳۰روزه، streak فعلی (دنباله انتهایی occurrenceهای انجام‌شده)، ۷ مربع آخر — همه از یک مپ completion ازپیش‌لودشده حساب می‌شود (بدون N+1).
- تاگل کل روتین، استپ‌ها را همگام می‌کند و برعکس: با کامل‌شدن همه استپ‌ها خود روتین تیک می‌خورد.
- منطق recurrence در `Routine::occursOn()`: هفتگی = نام روز هفته، ماهانه = روز ماه، every_n_days = فاصله از روز ساخت.

### تسک‌ها `/tasks` (`TaskController`)
- درخت نامحدود (`childrenRecursive` ایگرلودشده)؛ ریشه‌ها `parent_id=null`.
- پیشرفت leaf از روی status؛ والد = میانگین وزنی فرزندان (`progressPercent/aggregateWeight` — روی کالکشن لودشده، بدون کوئری اضافه).
- وزن دستی × (۱ + زمان واقعی/تخمینی) وقتی `auto_weight` روشن است؛ زمان از `TimeEntry` جمع می‌شود.
- reorder با drag (parent_id + sort_order)، bulk update/delete (سقف ۲۰۰ آیدی، فقط تسک‌های خود کاربر).
- `resolveParent`: والد باید هم‌پروژه و هم‌کاربر باشد + ضدچرخه (سقف ۱۰۰ سطح).

### پروژه‌ها `/projects` (`ProjectController`)
درخت بازگشتی (`childrenRecursive.tasks`)، پیشرفت وزنی روی تسک‌های ریشه + زیرپروژه‌ها، `breadcrumb()` (زنجیره والدها)، اعضای تیم (`project_teams`)، reorder، `assertNoCycle`.

### روتین‌ها `/routines` (`RoutineController`)
- ساخت/ویرایش با `days` (هفتگی) / `month_days` (ماهانه) / `every_n_days` (۲ تا ۶۰)؛ یا `time_period` (صبح/ظهر/...) از `config/routines.php` یا بازه ساعت دقیق — هرگز هر دو با هم.
- صفحه stats هر روتین: هیت‌مپ ۱۶ هفته، streak جاری/بهترین، adherence سی‌روزه، `completionTracker` (توزیع ساعت انجام + انحراف از ساعت برنامه).
- `toggleOn` ایدم‌پوتنت است (تیک مجدد = آنتیک، بدون رکورد تکراری).

### ریمایندر `/reminders` (`ReminderController`)
اولویت ۴سطحی، دسته‌بندی، تگ، تکرار (daily/weekly/monthly/yearly با ساخت occurrence بعدی هنگام تکمیل)، snooze (دقیقه‌ای)، تقویم (fullCalendar events)، duplicate، اسکوپ‌های active/completed/overdue/dueToday.

### نوت/فایل/پروفایل/میل
نوت: جستجو، فیلتر دسته، favorite، duplicate، excerpt/wordCount. فایل: `name/path/type`. پروفایل: ویرایش + آواتار + رمز. میل (`MailController`): فعلاً فقط ویوی اینباکس (استاب).

### تایم‌ترکر `/time/*` + ویجت شناور (`time/_widget`, `TimeTrackingController`)
ماشین‌حالت `running → paused ⇄ running → stopped` در مدل `TimeEntry` (`pause/resume/stop` + `elapsedSeconds` با کم‌کردن مکث). شروع خودکار تایمر قبلی را stop می‌کند. گزارش‌ها (`reports`): جمع کل، تفکیک پروژه، نمودار روزانه — همه از یک کالکشن (بدون N+1). ویجت سراسری (`layouts/app.blade.php:790`) روی همه صفحات فیکس است؛ در صفحه `/ai` با CSS بالاتر برده شده تا روی دکمه ارسال نیفتد.

## ۳. معماری AI (لینا) — تفصیلی

### ۳.۱ مسیر درخواست
```
کاربر (/ai یا ویجت شناور) → POST /ai/stream (SSE) → AiChatController@stream
  → resolve/conversation → پیام کاربر در ai_messages ذخیره می‌شود
  → AiProviderService::resolve: اول default_provider (اگر کلید دارد)، وگرنه اولین پروایدر دارای کلید
  → buildContext ( کل ورک‌اسپیس کاربر به متن) + system prompt + ۲۰ پیام آخر تاریخچه
  → type=openai: استریم واقعی (Guzzle, read 256بایتی) | gemini/anthropic: sync بعد分 chunk
  → پیام دستیار در ai_messages ذخیره + touch کانورسیشن
  → بدون پروایدر: LinaFallbackBrain (آفلاین، regex روی پیام + کوئری مستقیم DB، متن‌ها از resources/ai/lina_dataset.json)
```
نسخه غیراستریم `POST /ai/chat` هم هست (کمتر استفاده می‌شود). سقف ورودی ۸۰۰۰ کاراکتر (بک‌اند + هر دو فرانت). خروجی openai: `max_tokens` معمولی ۲۰۴۸، با tools برابر ۴۰۹۶.

### ۳.۲ پروایدرها (`config/ai.php` + `AiProviderService`)
داخلی: openai / gemini / anthropic / deepseek / meta (هر کدام label، base_url، لیست مدل‌ها، مدل پیش‌فرض، ستون کلید/مدل). **کاستوم** (`ai_providers`، ساخته کاربر از `/ai/settings`): `base_url + type(openai|gemini|anthropic) + model + api_key` — مسیر OpenRouter دقیقاً همین است. منطق `openAiPayload` برای openrouter حالت `models[]` فالبک را هم دارد. تست اتصال: `POST /ai/providers/{id}/test`.

### ۳.۳ دو مود (پیش‌فرض: گفتگو)
| | 💬 Chat | 🛠 Agent |
|---|---|---|
| ابزار | اصلاً ارسال نمی‌شود | ۱۶ ابزار (فقط وقتی `type==openai`) |
| system prompt | `MODE: CHAT` — بحث read-only؛ اگر کاربر ساخت/تغییر خواست، راهنمایی + درخواست سوییچ به Agent | `MODE: AGENT` — آرگومان کوتاه (عنوان <۸۰، توضیح <۵۰۰ کاراکتر)، چند کال (هر روز یک کال، سقف ۵)، برای برنامه هفتگی `routine_create` ترجیح دارد، نام‌ها snake_case |
| اگر شرط برقرار نباشد | — | Agent + پروایدر غیرopenai = پیام شفاف «نیاز به پروایدر OpenAI-compatible» و هیچ تغییری؛ Agent + متنِ شبیه JSON بدون tool-call واقعی = هشدار «چیزی ساخته نشد» (بدون ساخت pending) |

تاگل در هدر چت (`ai/index.blade.php`)، ذخیره در `localStorage`، ارسال `mode` با هر درخواست؛ **سرور تنها مرجع تصمیم است** و فرانت در مود chat کارت proposal را رندر نمی‌کند.

### ۳.۴ ابزارها (۱۶ عدد، `AiToolService::TOOLS`)
نام‌ها فقط آندرلاین (API نقطه/خط‌فاصله را با ۴۰۰ رد می‌کند): `normalizeToolName` رکوردهای قدیمی نقطه‌دار را هم می‌پذیرد.

| ابزار | ورودی کلیدی | رفتار |
|---|---|---|
| task_create/update/complete/delete | title*, project_id/نام پروژه, due_date, priority, status | complete فقط تیک می‌زند؛ delete اثر ساب‌تسک‌ها را نشان می‌دهد |
| reminder_create/complete/delete | title*, date, time(HH:MM), priority | complete رکورد تکرار بعدی را می‌سازد |
| note_create/update/delete | title*, content* | — |
| project_create | name* | type=project |
| checklist_add/toggle | task_id+name / id | toggle برمی‌گرداند (done/reopened) |
| routine_create | title*, frequency*, days/month_days/every_n_days, time_period?, description | آینه قوانین `RoutineController@validated`؛ پیام موفقیت شامل `recurrenceLabel` |
| routine_complete | id, date? (پیش‌فرض امروز) | **هرگز آنتیک نمی‌کند**؛ تکراری = «already done» |
| routine_delete | id | سافت‌دیلیت؛ کارت می‌گوید تاریخچه می‌ماند |

چرخه: `definitions()` (JSON Schema با `additionalProperties:false`) → مدل tool_call می‌زند → `validateCall` (فقط خواندن + چک `user_id`، aliasهای camelCase مثل `projectId/dueDate/monthDays` هم پذیرفته می‌شود) → رکورد `ai_pending_actions` (pending، انقضا ۱۵ دقیقه، سقف ۵ باز به‌ازای کاربر، `idempotency_key`) → **کارت تأیید** (مشخصات + اثر خطرناک) → `POST /ai/actions/{id}/confirm|reject` (throttle:30,1، مالکیت ۴۰۳، re-validate، اجرا در transaction، تأیید تکراری dedupe، پیام ✅ در تاریخچه، لاگ `ai.tool.*`). بدون Undo.

### ۳.۵ فرانت چت (`ai/index.blade.php`)
سایدبار کانورسیشن‌ها (ساخت/تغییرنام/حذف/پاک‌کردن)، حباب‌ها با Markdown (`marked`) + هایلایت کد (`hljs`) + دکمه کپی، تایپینگ، استریم توکنی، کارت proposal (جدول rows، هشدار impact قرمز برای delete، دکمه‌های Confirm/Cancel + انقضا)، شمارنده کاراکتر، chips پیشنهادی، حالت موبایل (سایدبار کشویی). ویجت شناور سراسری (`layouts/_ai_chat.blade.php`) نسخه سبک همین چت است (سقف ۸۰۰۰ مشترک).

## ۴. امنیت (قرارداد سراسری)
- هر رکورد `user_id` دارد؛ هر خواندن/نوشتن با اسکوپ کاربر + `abort_if(403)` (الگو را در همه کنترلرهای جدید حفظ کن).
- `Project` با slug در URL می‌آید — اسکوپ `user_id` را فراموش نکن (آسیب‌پذیری IDOR).
- ولیدیشن ورودی در همه store/update؛ خروجی‌های JSON خطا اطلاعات чужой لو ندهند.
- AI: ابزار فقط در Agent + openai-compatible؛ re-validate لحظه اجرا؛ سقف pending؛ throttle روی confirm/reject.

## ۵. تست‌ها (`php artisan test` — سبز: ۴۸ تست)
`AiToolsTest` (validate/execute/confirm/reject/انقضا/403/aliasها/legacy)، `AiModesTest` (chat ابزار نمی‌فرستد، agent proposal می‌سازد، پرامپت‌ها، ابزارهای روتین)، `RoutinesHabitTest`، `TasksChaptersTest`، `DetailsPagesTest` و بقیه. برای HTTP پروایدر از `Http::fake` استفاده کن (الگو در `AiModesTest`).

## ۶. افزودن قابلیت (چک‌لیست توسعه)
- **ابزار AI جدید:** نام به `TOOLS` → تعریف در `definitions()` → `validate*` (فقط خواندن + user_id) → `exec*` → ردیف `preview` → تست در `AiToolsTest`.
- **پروایدر جدید:** ورودی در `config/ai.php` (label/base_url/type/models/default + ستون‌های `AiSetting`) — اگر OpenAI-compatible است type=openai تا ابزارها خودکار کار کنند.
- **فیچر CRUD جدید:** مدل + کنترلر با اسکوپ `user_id` و 403 + ویو Blade + روت + تست Feature.
- **نکته‌های مهم:** `with('project...')` همیشه `slug` را هم سلکت کند؛ متدهای محاسباتی مدل (progress/streak) باید روی رلیشنِ ایگرلودشده کار کنند نه کوئری تکی (قرارداد ضد N+1)؛ مایگریشن جدید برای هر تغییر اسکیما.
