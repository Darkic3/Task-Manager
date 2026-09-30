<?php

namespace App\Services\Notes;

use App\Models\Note;

class NoteTemplateService
{
    /**
     * Markdown skeleton per note kind. Short on purpose: a starting
     * point, not a form. Persian headings so the export reads well.
     *
     * @return array<string, array{hint: string, body: string}>
     */
    public static function all(): array
    {
        return [
            Note::KIND_DAILY => [
                'hint' => 'مرور روز: ۳ اتفاق مهم، حال، دستاورد',
                'body' => "## امروز چه گذشت؟\n- \n\n## حال و انرژی\n- مود: /5 — انرژی: /5\n\n## ۳ اتفاق مهم\n1. \n2. \n3. \n\n## دستاورد\n- \n\n## نگرانی / فردا\n- ",
            ],
            Note::KIND_EVENT => [
                'hint' => 'رویداد: کی، کجا، چه شد، فالوآپ',
                'body' => "## رویداد\n- تاریخ: \n- مکان: \n- افراد حاضر: @\n\n## چه گذشت؟\n- \n\n## نتیجه\n- \n\n## فالوآپ\n- [ ] ",
            ],
            Note::KIND_PERSON => [
                'hint' => 'شخص: شناخت، حرف مهم، تعهد',
                'body' => "## شناخت\n- نقش: \n- زمینه: \n\n## آخرین حرف مهم\n- \n\n## تعهد / قول\n- [ ] \n\n## برداشت شخصی\n- ",
            ],
            Note::KIND_MEETING => [
                'hint' => 'جلسه: دستور، تصمیم، اقدام بعدی',
                'body' => "## دستور جلسه\n- \n\n## حاضرین\n- @\n\n## تصمیم‌ها\n- [ ] \n\n## اقدام بعدی\n- [ ] مسئول:  | ددلاین: \n",
            ],
            Note::KIND_DECISION => [
                'hint' => 'تصمیم: مسئله، گزینه‌ها، دلیل',
                'body' => "## مسئله\n- \n\n## گزینه‌ها\n- الف: \n- ب: \n\n## تصمیم نهایی\n- \n\n## دلیل\n- \n\n## بازبینی در تاریخ\n- ",
            ],
            Note::KIND_TOPIC => [
                'hint' => 'موضوع: مسئله، نکات، منابع',
                'body' => "## موضوع\n- \n\n## نکات کلیدی\n- \n\n## منابع\n- \n\n## سوال باز\n- ",
            ],
            Note::KIND_IDEA => [
                'hint' => 'ایده: مسئله، راه‌حل، قدم بعدی',
                'body' => "## مسئله\n- \n\n## راه‌حل\n- \n\n## چرا خوب است؟\n- \n\n## ریسک\n- \n\n## قدم بعدی\n- [ ] ",
            ],
            Note::KIND_REFERENCE => [
                'hint' => 'منبع: خلاصه، نقل‌قول، برداشت',
                'body' => "## منبع\n- \n\n## خلاصه\n- \n\n## نقل‌قول\n> \n\n## برداشت شخصی\n- ",
            ],
            Note::KIND_LOG => [
                'hint' => 'لاگ: زمان، رخداد، نتیجه',
                'body' => "## زمان\n- \n\n## رخداد\n- \n\n## نتیجه\n- ",
            ],
            Note::KIND_GENERAL => [
                'hint' => 'یادداشت آزاد',
                'body' => "## خلاصه\n- \n\n## جزئیات\n- ",
            ],
        ];
    }

    public static function bodyFor(string $kind): string
    {
        return self::all()[$kind]['body'] ?? self::all()[Note::KIND_GENERAL]['body'];
    }

    public static function hintFor(string $kind): string
    {
        return self::all()[$kind]['hint'] ?? '';
    }
}
