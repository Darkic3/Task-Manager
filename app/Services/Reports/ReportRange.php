<?php

namespace App\Services\Reports;

use Carbon\Carbon;

/**
 * Shared date-range value object for all reports: today / week / month /
 * custom, plus the previous equal-length window for delta comparisons.
 */
final class ReportRange
{
    public readonly Carbon $from;
    public readonly Carbon $to;
    public readonly Carbon $prevFrom;
    public readonly Carbon $prevTo;
    public readonly string $preset;
    public readonly string $label;

    private function __construct(Carbon $from, Carbon $to, string $preset, string $label)
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->startOfDay();
        $length = $this->from->diffInDays($this->to) + 1;
        $this->prevTo = $this->from->copy()->subDay();
        $this->prevFrom = $this->prevTo->copy()->subDays($length - 1);
        $this->preset = $preset;
        $this->label = $label;
    }

    public static function fromRequest(?string $preset, ?string $from, ?string $to): self
    {
        $today = now()->startOfDay();

        if ($preset === 'today') {
            return new self($today, $today, 'today', 'Today');
        }
        if ($preset === 'month') {
            return new self($today->copy()->startOfMonth(), $today, 'month', 'This month');
        }
        if ($preset === 'custom' && $from && $to) {
            try {
                $f = Carbon::parse($from)->startOfDay();
                $t = Carbon::parse($to)->startOfDay();
                if ($t->lt($f)) {
                    [$f, $t] = [$t, $f];
                }
                // Cap custom windows at one year to keep aggregates cheap.
                if ($f->diffInDays($t) > 365) {
                    $f = $t->copy()->subDays(365);
                }

                return new self($f, $t, 'custom', $f->format('M d').' – '.$t->format('M d, Y'));
            } catch (\Throwable) {
                // fall through to week
            }
        }

        $start = $today->copy()->startOfWeek(Carbon::SATURDAY);

        return new self($start, $today, 'week', 'This week');
    }

    /** Y-m-d keys for every day in [from..to]. */
    public function days(): array
    {
        $days = [];
        $cursor = $this->from->copy();
        while ($cursor->lte($this->to)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $days;
    }

    public function toQuery(): array
    {
        return [
            'range' => $this->preset,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }
}
