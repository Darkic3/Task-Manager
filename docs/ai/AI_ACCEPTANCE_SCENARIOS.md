# سناریوهای پذیرش AI ‏(Lina) — ‏Given / When / Then

> هر سناریو: ورودی، وضعیت اولیه دیتابیس، رفتار مورد انتظار، سؤال/تأیید لازم،
> ابزارهای مجاز/ممنوع، اثر جانبی، روش اثبات، و تست موجود یا موردنیاز.
> «موجود» یعنی رفتار در کد امروز پیاده است؛ در غیر این صورت «موردنیاز» ثبت شده.

---

## ۱. ساخت یک تسک ساده و جدید ✅ موجود
- **Given:** کاربر بدون پروژه، تسکی با این عنوان ندارد.
- **When:** «یه تسک بساز به نام خرید شیر، فردا»
- **Then:** یک `tool_proposal` برای `task_create` با `awaiting_confirmation`؛ پس از تأیید دقیقاً یک سطر `tasks`.
- **ابزار مجاز:** `task_create` (+ `workspace_search` برای بررسی). **ممنوع:** بقیه ابزارهای WRITE.
- **اثبات:** `assertDatabaseHas('tasks')` + رویدادهای `tool.proposed → confirmation.requested → action.executed` با یک `run_id`.
- **تست موجود:** `AiTraceRunTest::test_stream_run_traces_proposal_and_confirmation`، `AiConfirmationTest`.

## ۲. درخواست ساخت تسکی که وجود دارد ⚠️ موجود (جزئی)
- **Given:** تسک باز با عنوان یکسان موجود است.
- **When:** همان درخواست سناریو ۱ تکرار می‌شود.
- **Then (مطلوب):** هشدار تکراری + پیشنهاد استفاده از موجود، بدون سطر جدید.
- **رفتار فعلی:** هیچ نگهبان مشابهتی برای تسک نیست؛ سطر دوم ساخته می‌شود (فقط fallback عنوانِ دقیق را رد می‌کند). **شکاف B2.**
- **اثبات:** `assertDatabaseCount` پس از دو درخواست یکسان.
- **تست موردنیاز:** جدید — `duplicate_task_title_warns_and_creates_nothing`.

## ۳. اصلاح عنوان یا تاریخ تسک قبلی ⚠️ موجود (جزئی)
- **Given:** تسک «خرید شیر» موجود؛ نوبت بعد کاربر: «تاریخش کن پس‌فردا».
- **Then (مطلوب):** همان تسک (نه سطر تازه) با `task_update` پیشنهاد شود.
- **رفتار فعلی:** روتر آن را `task_mutation` عمومی می‌زند؛ مدل باید از تاریخچه متنی حدس بزند — بدون «درخواست جاری» و بدون پیوند صریح به نوبت قبل. **شکاف A1/A2.**
- **اثبات:** `preview.action_id` به همان تسک اشاره کند؛ `tasks` همان یک سطر بماند.
- **تست موجود (جزئی):** `AiTaskResolveTest` (تفکیک عنوان)؛ **موردنیاز:** سناریوی چندنوبتی اصلاح.

## ۴. درخواست مبهم با چند موجودیت هم‌نام ⚠️ موجود (جزئی)
- **Given:** دو تسک «گزارش» در پروژه‌های مختلف.
- **When:** «گزارش رو کامل کن»
- **Then (مطلوب):** `ask_user_choice` با ۲ گزینه دقیق، بدون حدس.
- **رفتار فعلی:** تسک چندتایی فقط `conflict` متنی بدون `candidates` می‌دهد؛ پرسش به رفتار مدل واگذار شده و تضمینی نیست. **شکاف B3.**
- **اثبات:** پاکت `tool_proposal` با ابزار `ask_user_choice` و ۲ گزینه `{tool,args}` معتبر.
- **تست موردنیاز:** جدید — ابهام تسک → choice ساخت‌یافته.

## ۵. افزودن عضو با چند تطبیق احتمالی ✅ موجود
- **Given:** دو کاربر با نام مشابه؛ پروژه تحت مالکیت کاربر.
- **When:** «علی رو به پروژه X اضافه کن»
- **Then:** `ambiguous_target` + حداکثر ۵ کاندیدا (`id+name`) و کارت `ask_user_choice`؛ پس از انتخاب، همان run با `project_add_member` اجرا شود.
- **اثر جانبی:** دقیقاً یک upsert در `project_user`.
- **اثبات:** `assertDatabaseHas('project_user')` + زنجیره `run_id` از پیشنهاد تا اجرا.
- **تست موجود:** `AiProjectMemberSelectionTest`، `AiSearchAndAskTest::test_choice_gated_ownership_state_bounds`.

## ۶. ساخت یا اصلاح روتین ✅ موجود (با احتیاط ثبت‌شده)
- **Given:** کاربر بدون روتین هم‌نام.
- **When:** «روتین ورزش هفتگی بساز شنبه و دوشنبه»
- **Then:** `routine_create` با `days` الزامی؛ پس از تأیید یک سطر `routines` (+ گام‌ها).
- **احتیاط مستند:** تطبیق نام روتین first-LIKE-match خاموش است — اصلاح روتین هم‌نام ممکن است به هدف اشتباه بخورد. **شکاف B3.**
- **تست موجود:** `AiModesTest`، `RoutineTrackingTest`.

## ۷. ساخت پلن چندمرحله‌ای ✅ موجود
- **Given:** فضای کاری خالی.
- **When:** «یه پلن ماهانه برای راه‌اندازی فروشگاه با ۳ تسک بچین»
- **Then:** `plan_proposed` + تأیید ساختار (بدون ساخت‌وساز، پیام `📋`)، سپس اجرای فازبه‌فاز با گزارش هر فاز.
- **ابزار ممنوع:** اجرای مستقیم موجودیت‌ها پیش از تأیید ساختار.
- **اثبات:** وضعیت‌های `proposed→confirmed→executing→done` در `plan.updated` + سطرهای ساخته‌شده.
- **تست موجود:** `AiPlansTest`، `AiConfirmationTest::test_plan_phase_failure_reports_partial_progress`.

## ۸. تغییر درخواست در میانه برنامه‌ریزی ❌ شکاف (موردنیاز)
- **Given:** پلن `proposed` برای فروشگاه.
- **When:** «بهش یه یادآور هم اضافه کن»
- **Then (مطلوب):** ویرایش همان پلن با اثر مشخص بر فازها.
- **رفتار فعلی:** `plan_propose` تازه = سطر `ai_plans` جدید (کلید از title+structure)؛ پلن قبلی باید دستی لغو شود. **شکاف C4.**
- **تست موردنیاز:** جدید — ویرایش پلن، سطر تازه نسازد.

## ۹. لغو یا رد پیشنهاد ✅ موجود
- **When:** کاربر کارت تأیید را رد می‌کند (تکی یا گروهی).
- **Then:** چرخش اتمی به `rejected`؛ تأیید هم‌زمانِ برنده، بازنویسی نشود؛ هیچ سطری ساخته نشود.
- **اثبات:** وضعیت `rejected` + `assertDatabaseMissing` + پاسخ `{deduped:true}` در مسابقه.
- **تست موجود:** `AiConfirmationTest::test_reject_after_execute_deduped`، `AiToolsTest::test_confirm_executes_and_reject_cancels`.

## ۱۰. تأیید تکراری یا retry ✅ موجود
- **When:** دابل‌کلیک / ارسال مجدد / reconnect پس از اجرای موفق.
- **Then:** دقیقاً یک side-effect؛ پاسخ‌های بعدی `deduped`/`already_executed` بدون اجرای دوباره.
- **اثبات:** شمارش سطرها + دقیقاً یک رویداد `action.executed` برای `run_id`.
- **تست موجود:** `AiIdempotencyTest` (۹ سناریو)، `AiTraceRunTest::test_retry_of_the_same_operation_keeps_its_trace_and_one_side_effect`.

## ۱۱. شکست بخشی از عملیات چندمرحله‌ای ✅ موجود
- **When:** در `confirm-all` یا فاز پلن، یک قلم شکست می‌خورد.
- **Then:** بقیه مستقل اجرا شوند؛ گزارش تفکیکی done/failed/expired؛ برای پلن: `completed[]` + `failed_phase` بدون rollback پنهان.
- **اثبات:** JSON شامل هر سه شمارش + یادداشت `⚠ Partial progress`.
- **تست موجود:** `AiConfirmationTest::test_confirm_all_reports_done_expired_failed`، `test_plan_phase_failure_reports_partial_progress`.

## ۱۲. درخواست فاقد مجوز ✅ موجود
- **Given:** تسک/پروژه متعلق به کاربر دیگر (حدس شناسه).
- **When:** پیشنهاد و تأیید آن.
- **Then:** `not_found` (بدون افشای وجود) در پیشنهاد و اجرای مجدد؛ 403 در عبور از مالکیت سطر/مکالمه.
- **اثبات:** `assertForbidden`/`assertStatus(422)` + عدم تغییر دیتابیس + `user_id` بیگانه در `tailForUser` دیده نشود.
- **تست موجود:** `AiSecurityBoundaryTest` (۴۵/۵۸/۱۴۵/۱۸۳/۱۹۸)، `AiTaskResolveTest::test_rejects_unknown_title_and_other_users`.

## ۱۳. نبود Provider یا خطای Provider ✅ موجود
- **Given:** بدون کلید provider / کوتا تمام‌شده / قطع mid-stream.
- **When:** هر درخواست agent.
- **Then:** به‌ترتیب `offline_fallback` (فقط‌خواندنی، «هرگز چیزی نمی‌سازد»)، `provider_error` با پیام فارسی + جزئیات فنی تاشو، و برای استریم ناتمام `stream_truncated` (نه موفقیت کاذب).
- **اثبات:** `outcome` پایانی + `done_marker` + عدم وجود pending.
- **تست موجود:** `AiTraceRunTest` (provider-error، mid-stream، truncated، offline-fallback).

## ۱۴. بازخوانی مکالمه پس از reload ✅ موجود
- **Given:** پیشنهادهای تأییدنشده + اجرای انجام‌شده؛ صفحه reload می‌شود.
- **When:** تأیید پس از reload.
- **Then:** همان `run_id` (از `preview._trace`) بازیابی شود؛ منقضی → `expired_action`؛ اجراشده → `deduped`.
- **اثبات:** `executed.run_id == proposed.run_id` با `rid` متفاوت.
- **تست موجود:** `AiTraceRunTest::test_confirmation_after_reload_resolves_the_original_run`، `AiTraceContractTest` (reload + سطر بدون trace).

## ۱۵. گزارش نتیجه‌ای که بخشی اجرا نشده ❌ شکاف (موردنیاز)
- **When:** مدل متنی می‌نویسد «انجام شد» ولی tool_call واقعی نداشته.
- **Then (مطلوب):** پرچم ساخت‌یافته «تأییدنشده/اجرانشده» کنار پیام باشد تا UI آن را از موفقیت متمایز کند.
- **رفتار فعلی:** فقط پیام‌های متنی صادقانه (`nothing was saved`) بدون پرچم ماشینی. **شکاف E4.**
- **تست موردنیاز:** جدید — پاسخ بدون tool_call باید پرچم unverified داشته باشد.
