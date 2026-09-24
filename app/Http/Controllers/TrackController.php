<?php

namespace App\Http\Controllers;

use App\Models\Routine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackController extends Controller
{
    /**
     * Hub of measurable routines: latest value, delta vs previous,
     * 14-day sparkline. Single batched logs query (no N+1).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $kind = $request->input('kind');

        $routines = $user->routines()
            ->where('tracking_mode', '!=', Routine::TRACKING_NONE)
            ->when($kind, fn ($q) => $q->where('value_kind', $kind))
            ->with('checklistItems')
            ->orderBy('title')
            ->get();

        $from = now()->subDays(29)->startOfDay();
        $logsByRoutine = $routines->isNotEmpty()
            ? \App\Models\RoutineLog::where('user_id', $user->id)
                ->whereIn('routine_id', $routines->pluck('id'))
                ->where('completed_date', '>=', $from->toDateString())
                ->orderBy('completed_date')
                ->get()
                ->groupBy('routine_id')
            : collect();

        $cards = $routines->map(fn ($r) => $this->card($r, $logsByRoutine->get($r->id, collect())))->values();
        $kinds = $user->routines()
            ->where('tracking_mode', '!=', Routine::TRACKING_NONE)
            ->whereNotNull('value_kind')
            ->distinct()
            ->pluck('value_kind')
            ->sort()
            ->values();

        return view('track.index', compact('cards', 'kinds', 'kind'));
    }

    private function card(Routine $routine, $logs): array
    {
        $unit = $routine->tracking_mode === Routine::TRACKING_SETS
            ? null
            : ($routine->value_unit ?: null);

        if ($routine->tracking_mode === Routine::TRACKING_VALUE) {
            $byDate = $logs->whereNull('checklist_item_id')->groupBy(fn ($l) => $this->key($l));
            $dates = $byDate->keys()->sort()->values();
            $points = $dates->map(fn ($d) => ['date' => $d, 'value' => (float) $byDate[$d]->first()->value])->values();
            $latest = $points->last();
            $prev = $points->count() > 1 ? $points[$points->count() - 2] : null;

            return [
                'routine' => $routine,
                'latest' => $latest['value'] ?? null,
                'latest_date' => $latest['date'] ?? null,
                'delta' => ($latest && $prev) ? round($latest['value'] - $prev['value'], 2) : null,
                'unit' => $unit,
                'spark' => $points->take(-14)->pluck('value')->all(),
                'spark_labels' => $points->take(-14)->pluck('date')->all(),
            ];
        }

        // Sets mode: activity spark = sets logged per day; latest session detail.
        $stepLogs = $logs->whereNotNull('checklist_item_id');
        $byDate = $stepLogs->groupBy(fn ($l) => $this->key($l));
        $dates = $byDate->keys()->sort()->values();
        $latestDate = $dates->last();
        $latestSets = $latestDate ? $byDate[$latestDate] : collect();
        $stepNames = $routine->checklistItems->keyBy('id');
        $perStep = $latestSets->groupBy('checklist_item_id')->map(fn ($g, $itemId) => [
            'name' => $stepNames->get($itemId)?->name ?? ('Step #' . $itemId),
            'sets' => $g->sortBy('set_no')->map(fn ($l) => (float) $l->value)->values()->all(),
        ])->values();

        return [
            'routine' => $routine,
            'latest' => $latestSets->count() ?: null,
            'latest_date' => $latestDate,
            'latest_suffix' => 'sets',
            'delta' => null,
            'unit' => null,
            'spark' => $dates->take(-14)->map(fn ($d) => $byDate[$d]->count())->all(),
            'spark_labels' => $dates->take(-14)->all(),
            'per_step' => $perStep,
        ];
    }

    private function key($log): string
    {
        return $log->completed_date instanceof Carbon
            ? $log->completed_date->toDateString()
            : substr((string) $log->completed_date, 0, 10);
    }
}
