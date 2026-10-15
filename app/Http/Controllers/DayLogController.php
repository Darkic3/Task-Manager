<?php

namespace App\Http\Controllers;

use App\Models\DayLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DayLogController extends Controller
{
    /**
     * Day's entries plus the repeat catalog that powers suggestions.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'date' => ['sometimes', 'date'],
        ]);
        $date = $data['date'] ?? today()->toDateString();

        $entries = Auth::user()->dayLogs()
            ->where('date', $date)
            ->orderBy('id')
            ->get()
            ->map(fn (DayLog $log) => $log->toClientArray())
            ->values();

        return response()->json([
            'ok' => true,
            'date' => $date,
            'entries' => $entries,
            'catalog' => $this->catalog(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['mood_picked'] = (bool) ($data['mood_picked'] ?? false);
        $data['is_quit'] = (bool) ($data['is_quit'] ?? false);

        $log = Auth::user()->dayLogs()->create($data);

        return response()->json(['ok' => true, 'entry' => $log->fresh()->toClientArray()], 201);
    }

    public function update(Request $request, DayLog $dayLog)
    {
        $this->owned($dayLog);

        $rules = collect($this->rules())
            ->map(fn ($rule) => $this->sometimes($rule))
            ->all();

        $validator = validator($request->all(), $rules);
        $validator->after(function ($validator) use ($request, $dayLog) {
            $kind = $request->input('kind', $dayLog->kind);
            if ($kind !== DayLog::KIND_JOURNAL) {
                return;
            }
            foreach (['period', 'duration_seconds', 'amount', 'amount_unit', 'start_time', 'end_time'] as $field) {
                $value = $request->has($field) ? $request->input($field) : $dayLog->{$field};
                if ($value !== null && $value !== '') {
                    $validator->errors()->add($field, __('Day log sentences cannot carry time info.'));
                    break;
                }
            }
        });
        $data = $validator->validate();
        foreach (['mood_picked', 'is_quit'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $data[$flag] = (bool) ($data[$flag] ?? false);
            }
        }

        $dayLog->fill($data);
        $dayLog->save();

        return response()->json(['ok' => true, 'entry' => $dayLog->fresh()->toClientArray()]);
    }

    public function destroy(DayLog $dayLog)
    {
        $this->owned($dayLog);
        $dayLog->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Merge one repeat group (same activity, all time-based) into its earliest row.
     */
    public function merge(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:2', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
        ]);
        $ids = array_values(array_unique($data['ids']));

        $logs = Auth::user()->dayLogs()->whereIn('id', $ids)->get();
        abort_if($logs->count() !== count($ids), 403);

        $titles = $logs->map(fn (DayLog $log) => DayLog::normalizedTitle($log->title))->unique();
        $mergeable = $titles->count() === 1
            && $logs->every(fn (DayLog $log) => $log->kind === DayLog::KIND_ACTIVITY && (int) $log->duration_seconds > 0);
        abort_unless($mergeable, 422, __('Day log entries cannot be merged.'));

        $survivor = null;
        $mergedIds = [];
        DB::transaction(function () use ($logs, &$survivor, &$mergedIds) {
            $survivor = $logs->sortBy('id')->first();
            $seconds = 0;
            $notes = [];
            foreach ($logs as $log) {
                $seconds += (int) $log->duration_seconds;
                if ($log->note !== null && $log->note !== '' && ! in_array($log->note, $notes, true)) {
                    $notes[] = $log->note;
                }
                if ($log->id !== $survivor->id) {
                    $mergedIds[] = $log->id;
                    $log->delete();
                }
            }
            $survivor->forceFill([
                'duration_seconds' => $seconds,
                'note' => $notes ? implode(' · ', $notes) : null,
                'amount' => null,
                'amount_unit' => null,
                'start_time' => null,
                'end_time' => null,
            ])->save();
        });

        return response()->json([
            'ok' => true,
            'survivor' => $survivor->fresh()->toClientArray(),
            'merged_ids' => $mergedIds,
        ]);
    }

    /**
     * Repeat candidates for the Insights card: habitable activities and
     * quit-tracked ones. Titles already living as routines are excluded.
     */
    public function candidates(Request $request)
    {
        $request->validate(['date' => ['sometimes', 'date']]);

        $rows = Auth::user()->dayLogs()
            ->where('kind', DayLog::KIND_ACTIVITY)
            ->where('date', '>=', today()->subDays(21)->toDateString())
            ->orderBy('id')
            ->get(['title', 'date', 'is_quit']);

        $weekAgo = today()->subDays(7)->toDateString();
        $groups = [];
        foreach ($rows as $row) {
            $key = DayLog::normalizedTitle($row->title);
            if ($key === '') {
                continue;
            }
            if (! isset($groups[$key])) {
                $groups[$key] = ['name' => trim($row->title), 'count' => 0, 'week' => 0, 'quit' => false];
            }
            $groups[$key]['name'] = trim($row->title);
            $groups[$key]['count']++;
            if ($row->date >= $weekAgo) {
                $groups[$key]['week']++;
            }
            if ($row->is_quit) {
                $groups[$key]['quit'] = true;
            }
        }

        $routineNames = Auth::user()->routines()->pluck('title')
            ->map(fn ($title) => DayLog::normalizedTitle($title))
            ->filter()
            ->all();
        $routineNames = array_flip($routineNames);

        $habit = [];
        $quit = [];
        foreach ($groups as $key => $group) {
            if ($group['quit']) {
                if ($group['week'] >= 2) {
                    $quit[] = ['name' => $group['name'], 'count' => $group['week']];
                }
                continue;
            }
            if ($group['count'] >= 3 && ! isset($routineNames[$key])) {
                $habit[] = ['name' => $group['name'], 'count' => $group['count']];
            }
        }
        usort($habit, fn ($a, $b) => $b['count'] <=> $a['count']);
        usort($quit, fn ($a, $b) => $b['count'] <=> $a['count']);

        return response()->json([
            'ok' => true,
            'habit' => array_values(array_slice($habit, 0, 5)),
            'quit' => array_values(array_slice($quit, 0, 5)),
        ]);
    }

    protected function owned(DayLog $dayLog): void
    {
        abort_if($dayLog->user_id !== Auth::id(), 403);
    }

    protected function rules(): array
    {
        $periods = array_keys(config('routines.periods', []));

        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'kind' => ['required', Rule::in([DayLog::KIND_ACTIVITY, DayLog::KIND_JOURNAL])],
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'period' => ['nullable', Rule::in($periods), 'prohibited_if:kind,journal'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400', 'prohibited_if:kind,journal'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:100000', 'prohibited_if:kind,journal'],
            'amount_unit' => ['nullable', Rule::in(DayLog::UNITS), 'prohibited_if:kind,journal'],
            'start_time' => ['nullable', 'date_format:H:i', 'prohibited_if:kind,journal'],
            'end_time' => ['nullable', 'date_format:H:i', 'prohibited_if:kind,journal'],
            'logged_at' => ['nullable', 'date_format:H:i'],
            'mood' => ['nullable', 'integer', 'min:1', 'max:10'],
            'mood_picked' => ['nullable', 'boolean'],
            'is_quit' => ['nullable', 'boolean'],
            'time_entry_id' => ['nullable', 'integer', Rule::exists('time_entries', 'id')->where('user_id', Auth::id()), 'prohibited_if:kind,journal'],
        ];
    }

    protected function sometimes(array|string $rule): array|string
    {
        if (is_string($rule)) {
            return str_starts_with($rule, 'required') ? 'sometimes' : $rule;
        }

        return collect($rule)->map(fn ($r) => is_string($r) && str_starts_with($r, 'required') ? 'sometimes' : $r)->all();
    }

    /**
     * Repeat catalog: most-used activity titles of the last 90 days.
     */
    protected function catalog(): array
    {
        $rows = Auth::user()->dayLogs()
            ->where('kind', DayLog::KIND_ACTIVITY)
            ->where('date', '>=', today()->subDays(90)->toDateString())
            ->orderBy('id')
            ->get(['title', 'amount_unit', 'is_quit']);

        $groups = [];
        foreach ($rows as $row) {
            $key = DayLog::normalizedTitle($row->title);
            if ($key === '') {
                continue;
            }
            if (! isset($groups[$key])) {
                $groups[$key] = ['name' => trim($row->title), 'uses' => 0, 'unit' => 'minute', 'quit' => false];
            }
            $groups[$key]['name'] = trim($row->title);
            $groups[$key]['uses']++;
            if ($row->amount_unit) {
                $groups[$key]['unit'] = $row->amount_unit;
            }
            if ($row->is_quit) {
                $groups[$key]['quit'] = true;
            }
        }

        return collect(array_values($groups))
            ->sortByDesc('uses')
            ->take(20)
            ->values()
            ->all();
    }
}
