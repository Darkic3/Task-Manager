<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Models\WorkoutSetLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkoutReportController extends Controller
{
    public function index(Request $request)
    {
        $date = $this->parseDate($request->input('date'));
        $view = (string) $request->input('view', 'week');
        $mode = in_array($view, ['day', 'week', 'month', 'overall'], true) ? $view : 'week';

        [$start, $end] = match ($mode) {
            'day' => [$date->copy(), $date->copy()],
            'week' => [$date->copy()->startOfWeek(Carbon::SATURDAY), $date->copy()->startOfWeek(Carbon::SATURDAY)->copy()->addDays(6)],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [
                $this->overallStartForUser(auth()->user()),
                now()->copy()->endOfDay(),
            ],
        };

        $sessions = $this->sessionsBetween($start, $end);
        $plannedByDate = $mode === 'overall' ? [] : $this->plannedDaysBetween($start, $end);

        $plannedSessionsAndSets = $mode === 'overall'
            ? ['scheduled_sessions' => null, 'scheduled_sets' => null]
            : $this->plannedSessionsAndSetsBetween($start, $end);

        if ($mode === 'overall') {
            $days = collect();
        } else {
            $dayCount = $mode === 'day' ? 0 : ($mode === 'week' ? 6 : $start->copy()->daysInMonth - 1);
            $days = collect(range(0, $dayCount))->map(function (int $offset) use ($start, $sessions, $plannedByDate): array {
                $day = $start->copy()->addDays($offset);
                $daySessions = $sessions->filter(fn (WorkoutSession $session) => $session->workout_date->isSameDay($day))->values();

                return [
                    'date' => $day,
                    'sessions' => $daySessions,
                    'summary' => $this->summarizeSessions($daySessions),
                    'scheduled' => $plannedByDate[$day->toDateString()] ?? 0,
                ];
            });
        }

        $summary = $this->summarizeSessions($sessions);
        $summary['scheduled'] = $mode === 'overall' ? null : ($days->sum('scheduled'));

        // Adherence based on planned days (sessions) and completed sessions.
        $scheduledSessions = $plannedSessionsAndSets['scheduled_sessions'];
        $summary['scheduled_sessions'] = $scheduledSessions;
        $summary['adherence_percent'] = ($scheduledSessions && $scheduledSessions > 0)
            ? round(($summary['completed_sessions'] / $scheduledSessions) * 100)
            : null;

        $summary['scheduled_sets'] = $plannedSessionsAndSets['scheduled_sets'];
        $summary['planned_vs_done_sets'] = ($plannedSessionsAndSets['scheduled_sets'] && $plannedSessionsAndSets['scheduled_sets'] > 0)
            ? ($plannedSessionsAndSets['scheduled_sets'].' / '.$summary['sets'])
            : null;

        // Skip reasons (exercise-level and set-level).
        [$exerciseSkipReasons, $setSkipReasons] = $this->skipReasonsFromSessions($sessions);
        $summary['exercise_skip_reasons'] = $exerciseSkipReasons;
        $summary['set_skip_reasons'] = $setSkipReasons;

        // Weekly bodyweight stats from workout sessions (fallback to routine weight comes later).
        $bodyweight = $this->bodyweightStatsBetween($start, $end);
        $summary['bodyweight_week_avg'] = $bodyweight['avg_curr'];
        $summary['bodyweight_week_delta'] = $bodyweight['delta'];

        $strengthCharts = $this->strengthTrendChartsBetween($start, $end);
        $bodyweightChart = $this->bodyweightTrendChartBetween($start, $end);
        $complianceChart = $this->complianceAndTimeChartBetween($sessions);

        // Calculate sets per muscle group for Weekly Muscle Radar
        $muscleVolume = [
            'Chest' => 0,
            'Back' => 0,
            'Legs' => 0,
            'Shoulders' => 0,
            'Arms' => 0,
            'Core' => 0,
        ];

        foreach ($sessions as $session) {
            foreach ($session->exerciseLogs as $elog) {
                $exercise = $elog->workoutExercise?->exercise;
                if (! $exercise) {
                    continue;
                }
                $setCount = $elog->setLogs->where('completed', true)->count();
                $groups = (array) ($exercise->muscle_groups ?? []);

                if (empty($groups)) {
                    $name = strtolower($exercise->name ?? '');
                    if (str_contains($name, 'bench') || str_contains($name, 'chest') || str_contains($name, 'push up')) {
                        $groups[] = 'Chest';
                    } elseif (str_contains($name, 'row') || str_contains($name, 'pull') || str_contains($name, 'lat') || str_contains($name, 'deadlift')) {
                        $groups[] = 'Back';
                    } elseif (str_contains($name, 'squat') || str_contains($name, 'leg') || str_contains($name, 'calf') || str_contains($name, 'lunge')) {
                        $groups[] = 'Legs';
                    } elseif (str_contains($name, 'press') || str_contains($name, 'shoulder') || str_contains($name, 'lateral')) {
                        $groups[] = 'Shoulders';
                    } elseif (str_contains($name, 'curl') || str_contains($name, 'tricep') || str_contains($name, 'bicep') || str_contains($name, 'arm')) {
                        $groups[] = 'Arms';
                    } else {
                        $groups[] = 'Core';
                    }
                }

                foreach ($groups as $g) {
                    $cap = ucfirst(strtolower($g));
                    if (array_key_exists($cap, $muscleVolume)) {
                        $muscleVolume[$cap] += $setCount;
                    }
                }
            }
        }

        return view('workouts.reports.index', [
            'mode' => $mode,
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'sessions' => $sessions,
            'summary' => $summary,
            'muscleVolume' => $muscleVolume,
            'strengthCharts' => $strengthCharts,
            'bodyweightChart' => $bodyweightChart,
            'complianceChart' => $complianceChart,
        ]);
    }

    private function strengthTrendChartsBetween(Carbon $start, Carbon $end): array
    {
        $userId = auth()->id();
        if (! $userId) {
            return [];
        }

        $ranges = [
            'pull-up' => [
                'label' => 'Pull-up',
                'query' => fn ($q) => $q
                    ->whereRaw('lower(normalized_name) like ?', ['%pull%'])
                    ->whereRaw('lower(normalized_name) like ?', ['%up%']),
                'colors' => ['both' => '#4f46e5'],
            ],
            'chin-up' => [
                'label' => 'Chin-up',
                'query' => fn ($q) => $q
                    ->whereRaw('lower(normalized_name) like ?', ['%chin%'])
                    ->whereRaw('lower(normalized_name) like ?', ['%up%']),
                'colors' => ['both' => '#0ea5e9'],
            ],
            'pike-push-up' => [
                'label' => 'Pike Push-up',
                'query' => fn ($q) => $q
                    ->whereRaw('lower(normalized_name) like ?', ['%pike%'])
                    ->whereRaw('lower(normalized_name) like ?', ['%push%'])
                    ->whereRaw('lower(normalized_name) like ?', ['%up%']),
                'colors' => ['both' => '#16a34a'],
            ],
            'bulgarian-split-squat' => [
                'label' => 'Bulgarian Split Squat',
                'query' => fn ($q) => $q
                    ->whereRaw('lower(normalized_name) like ?', ['%bulgarian%'])
                    ->whereRaw('lower(normalized_name) like ?', ['%split%'])
                    ->whereRaw('lower(normalized_name) like ?', ['%squat%']),
                'colors' => ['left' => '#f97316', 'right' => '#fbbf24'],
            ],
        ];

        $fromKey = $start->toDateString();
        $endKey = $end->toDateString();
        $out = [];

        foreach ($ranges as $key => $meta) {
            $exerciseIds = Exercise::query()
                ->forUser($userId)
                ->tap(fn ($q) => ($meta['query'])($q))
                ->pluck('id')
                ->all();

            if (empty($exerciseIds)) {
                continue;
            }

            $sets = WorkoutSetLog::query()
                ->whereHas('exerciseLog.workoutExercise.exercise', fn ($q) => $q->whereIn('id', $exerciseIds))
                ->whereHas('exerciseLog.session', fn ($q) => $q
                    ->where('user_id', $userId)
                    ->where('status', WorkoutSession::COMPLETED)
                    ->whereBetween('workout_date', [$fromKey, $endKey]))
                ->with(['exerciseLog.session', 'exerciseLog.workoutExercise.exercise'])
                ->get();

            if ($sets->isEmpty()) {
                continue;
            }

            $entries = $sets
                ->sortBy(fn ($s) => $s->exerciseLog->session->workout_date->toDateString().'|'.($s->created_at?->timestamp ?? 0))
                ->values();

            $labels = [];
            $repsBySide = [
                'left' => [],
                'right' => [],
                'both' => [],
            ];

            $hasLeft = false;
            $hasRight = false;

            foreach ($entries as $set) {
                $dateKey = $set->exerciseLog->session->workout_date->toDateString();
                $side = $set->side ?? 'both';
                $sideShort = $side === 'left' ? 'L' : ($side === 'right' ? 'R' : '');
                $label = $dateKey.' S'.$set->set_number.($sideShort ? ' '.$sideShort : '');
                $labels[] = $label;

                $reps = $set->reps !== null ? (float) $set->reps : null;
                foreach (['left', 'right', 'both'] as $s) {
                    if ($side === 'both' && $s === 'both') {
                        $repsBySide[$s][] = $reps;
                    } elseif ($side === $s) {
                        $repsBySide[$s][] = $reps;
                    } else {
                        $repsBySide[$s][] = null;
                    }
                }

                if ($side === 'left') {
                    $hasLeft = true;
                } elseif ($side === 'right') {
                    $hasRight = true;
                }
            }

            $datasets = [];
            if ($hasLeft) {
                $datasets[] = [
                    'label' => $key.' Left',
                    'data' => $repsBySide['left'],
                    'color' => $meta['colors']['left'] ?? '#f97316',
                ];
            }
            if ($hasRight) {
                $datasets[] = [
                    'label' => $key.' Right',
                    'data' => $repsBySide['right'],
                    'color' => $meta['colors']['right'] ?? '#fbbf24',
                ];
            }
            if (! $hasLeft && ! $hasRight) {
                $datasets[] = [
                    'label' => $key.' (Both)',
                    'data' => $repsBySide['both'],
                    'color' => $meta['colors']['both'] ?? '#4f46e5',
                ];
            }

            $out[$key] = [
                'label' => $meta['label'],
                'labels' => $labels,
                'datasets' => $datasets,
            ];
        }

        return $out;
    }

    private function bodyweightTrendChartBetween(Carbon $start, Carbon $end): array
    {
        $userId = auth()->id();
        if (! $userId) {
            return [];
        }

        $sessions = WorkoutSession::query()
            ->where('user_id', $userId)
            ->whereBetween('workout_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('bodyweight_kg')
            ->orderBy('workout_date')
            ->orderBy('bodyweight_recorded_at')
            ->get(['workout_date', 'bodyweight_kg', 'bodyweight_recorded_at']);

        // Take the latest recorded bodyweight per day.
        $byDay = [];
        foreach ($sessions as $s) {
            $k = $s->workout_date->toDateString();
            if (! isset($byDay[$k]) || ($byDay[$k]['recorded_at'] ? $s->bodyweight_recorded_at?->timestamp : null) >= ($byDay[$k]['recorded_at'] ? $byDay[$k]['recorded_at']->timestamp : null)) {
                $byDay[$k] = [
                    'kg' => (float) $s->bodyweight_kg,
                    'recorded_at' => $s->bodyweight_recorded_at,
                ];
            }
        }

        $labels = [];
        $daily = [];

        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end->copy()->startOfDay())) {
            $k = $cursor->toDateString();
            $labels[] = $cursor->format('M j');
            $daily[] = isset($byDay[$k]) ? (float) $byDay[$k]['kg'] : null;
            $cursor->addDay();
        }

        // Weekly average aligned to each day.
        $weekAvgByKey = [];
        foreach ($byDay as $dayKey => $row) {
            $date = Carbon::parse($dayKey)->startOfWeek(Carbon::SATURDAY);
            $wk = $date->toDateString();
            $weekAvgByKey[$wk]['sum'] = ($weekAvgByKey[$wk]['sum'] ?? 0) + (float) $row['kg'];
            $weekAvgByKey[$wk]['count'] = ($weekAvgByKey[$wk]['count'] ?? 0) + 1;
        }
        foreach ($weekAvgByKey as $wk => $r) {
            $weekAvgByKey[$wk] = $r['count'] ? $r['sum'] / $r['count'] : null;
        }

        $weekly = [];
        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end->copy()->startOfDay())) {
            $k = $cursor->toDateString();
            $wk = $cursor->copy()->startOfWeek(Carbon::SATURDAY)->toDateString();
            $weekly[] = $weekAvgByKey[$wk] ?? null;
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'daily' => $daily,
            'weekly' => $weekly,
        ];
    }

    private function complianceAndTimeChartBetween($sessions): array
    {
        // Only completed sessions for adherence.
        $completed = $sessions->where('status', WorkoutSession::COMPLETED)->values();
        if ($completed->isEmpty()) {
            return [];
        }

        $labels = [];
        $minutes = [];
        $setCompletion = [];

        foreach ($completed as $session) {
            $labels[] = $session->workout_date->format('M j');
            $minutes[] = (int) ($session->durationMinutes() ?? 0);

            $plannedSets = (int) ($session->day?->exercises?->sum(fn ($we) => $we->requiredSetCount()) ?? 0);
            $completedSets = 0;
            $exerciseLogs = $session->exerciseLogs;

            foreach ($exerciseLogs as $elog) {
                $sideMode = $elog->workoutExercise?->side_mode ?: 'bilateral';
                $completedSetRows = $elog->setLogs->where('completed', true);
                if ($sideMode === 'per_side') {
                    $bySet = $completedSetRows->groupBy('set_number');
                    foreach ($bySet as $rows) {
                        if ($rows->pluck('side')->unique()->count() >= 2) {
                            $completedSets++;
                        }
                    }
                } else {
                    $completedSets += $completedSetRows->pluck('set_number')->unique()->count();
                }
            }

            $setCompletion[] = $plannedSets > 0 ? round(($completedSets / $plannedSets) * 100, 1) : null;
        }

        return [
            'labels' => $labels,
            'minutes' => $minutes,
            'setCompletion' => $setCompletion,
        ];
    }

    public function export(Request $request)
    {
        $view = (string) $request->input('view', 'week');
        $mode = in_array($view, ['day', 'week', 'month', 'overall'], true) ? $view : 'week';
        $date = $this->parseDate($request->input('date'));
        $download = $request->boolean('download', true);

        [$start, $end] = match ($mode) {
            'day' => [$date->copy(), $date->copy()],
            'week' => [$date->copy()->startOfWeek(Carbon::SATURDAY), $date->copy()->startOfWeek(Carbon::SATURDAY)->copy()->addDays(6)],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [
                $this->overallStartForUser(auth()->user()),
                now()->copy()->endOfDay(),
            ],
        };

        $sessions = $this->sessionsBetween($start, $end);
        $sessionsByDate = $sessions->groupBy(fn (WorkoutSession $s) => $s->workout_date->toDateString());
        $plannedByDate = $mode === 'overall' ? [] : $this->plannedDaysBetween($start, $end);
        $summary = $this->summarizeSessions($sessions);
        $summary['scheduled'] = $mode === 'overall' ? null : array_sum(array_values($plannedByDate));

        $label = match ($mode) {
            'day' => $start->format('Y-m-d'),
            'week' => $start->format('Y-m-d') . ' → ' . $end->format('Y-m-d'),
            'month' => $start->format('Y-m') ,
            default => 'Overall',
        };
        $filename = 'workout-report-'.$mode.'-'.$label.'-'.now()->format('Ymd-His').'.txt';

        if ($download) {
            return $this->streamExport(function () use ($mode, $start, $end, $label, $sessionsByDate, $plannedByDate, $summary) {
                $cursor = $start->copy()->startOfDay();
                yield $this->exportHeaderBlock($mode, $label, $start, $end, $summary);

                // Overall can be large; we still render day-by-day to satisfy the “fill rest days” requirement.
                while ($cursor->lte($end->copy()->startOfDay())) {
                    $key = $cursor->toDateString();
                    $daySessions = $sessionsByDate->get($key, collect());
                    yield $this->exportDayBlock($cursor, $daySessions, $plannedByDate[$key] ?? null);
                    $cursor->addDay();
                }

                yield "\n";
            }, $filename);
        }

        // Inline text (used for copy / view).
        $chunks = [];
        foreach ($this->exportChunks($mode, $start, $end, $label, $sessionsByDate, $plannedByDate, $summary) as $c) {
            $chunks[] = $c;
        }
        $text = implode('', $chunks);

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    private function exportChunks(string $mode, Carbon $start, Carbon $end, string $label, $sessionsByDate, array $plannedByDate, array $summary): \Generator
    {
        $cursor = $start->copy()->startOfDay();
        yield $this->exportHeaderBlock($mode, $label, $start, $end, $summary);

        while ($cursor->lte($end->copy()->startOfDay())) {
            $key = $cursor->toDateString();
            $daySessions = $sessionsByDate->get($key, collect());
            yield $this->exportDayBlock($cursor, $daySessions, $plannedByDate[$key] ?? null);
            $cursor->addDay();
        }

        yield "\n";
    }

    private function streamExport(\Closure $factory, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($factory) {
            foreach ($factory() as $chunk) {
                echo $chunk;
            }
        }, $filename, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    private function exportHeaderBlock(string $mode, string $label, Carbon $start, Carbon $end, array $summary): string
    {
        $lines = [];
        $lines[] = 'WORKOUT PERFORMANCE REPORT';
        $lines[] = 'Generated at: '.now()->toDateTimeString();
        $lines[] = 'Range: '.$label;
        $lines[] = '';
        $lines[] = 'Overview';
        $lines[] = '- Sessions: '.$summary['sessions'].' (completed: '.$summary['completed_sessions'].')';
        $lines[] = '- Exercises: '.$summary['exercises'].' (completed: '.$summary['completed_exercises'].')';
        $lines[] = '- Sets: '.$summary['sets'].' | Reps: '.$summary['reps'];
        $lines[] = '- Volume (kg): '.$summary['volume'];
        $lines[] = '- Pain events: '.$summary['pain_events'];
        $lines[] = '- Duration: '.$summary['minutes'].' minutes';
        if ($mode !== 'overall' && $summary['scheduled'] !== null) {
            $lines[] = '- Scheduled movements (plan): '.$summary['scheduled'];
        }
        $lines[] = '';

        $lines[] = 'Daily log (including rest days)';
        $lines[] = '';

        return implode("\n", $lines)."\n";
    }

    private function exportDayBlock(Carbon $day, $daySessions, ?int $scheduled): string
    {
        $hasSessions = $daySessions->isNotEmpty();
        $plannedLine = $scheduled !== null ? 'Planned movements: '.$scheduled : null;

        $lines = [];
        $lines[] = '--- '.$day->format('Y-m-d').' ('.strtolower($day->format('l')).') ---';
        if (! $hasSessions) {
            $lines[] = 'Status: Rest Day';
            if ($plannedLine) {
                $lines[] = $plannedLine;
            }
            $lines[] = '';
            return implode("\n", $lines);
        }

        $first = $daySessions->first();
        if ($first->day?->title) {
            $lines[] = 'Planned day: '.$first->day->title.' ('.$first->day?->plan?->title.')';
        }
        if (! empty($first->day?->notes)) {
            $lines[] = 'Day notes: '.$this->singleLine((string) $first->day->notes);
        }
        if ($plannedLine) {
            $lines[] = $plannedLine;
        }

        foreach ($daySessions as $i => $session) {
            $lines[] = '';
            $lines[] = 'Session '.($i + 1).': '.$session->status;
            $lines[] = 'Duration: '.($session->durationMinutes() ?? 0).' minutes';
            $lines[] = 'Workout date: '.$session->workout_date->format('Y-m-d');
            if (! empty($session->session_note)) {
                $lines[] = 'Session note: '.$this->singleLine((string) $session->session_note);
            }
            if (! empty($session->pain_note)) {
                $lines[] = 'Pain note: '.$this->singleLine((string) $session->pain_note);
            }

            // Exercise order follows the workout day template.
            $exerciseLogsByWE = $session->exerciseLogs?->keyBy('workout_exercise_id') ?? collect();
            $orderedExercises = $session->day?->exercises ?? collect();

            foreach ($orderedExercises as $we) {
                $elog = $exerciseLogsByWE->get($we->id);
                if (! $elog) {
                    continue;
                }

                $weModel = $elog->workoutExercise;
                $targetSets = $weModel?->requiredSetCount();
                $completedSets = $elog->setLogs->where('completed', true)->count();
                $skipReason = $elog->skip_reason;
                $lines[] = '';
                $lines[] = '- Exercise: '.($weModel?->exercise?->name ?? $weModel?->id ?? 'Unknown');
                if ($weModel) {
                    $lines[] = '  Target: '.$weModel->targetLabel();
                    if (! empty($weModel->notes)) {
                        $lines[] = '  Planned exercise note: '.$this->singleLine((string) $weModel->notes);
                    }
                }
                if ($targetSets !== null) {
                    $lines[] = '  Target sets: '.$targetSets.' | Completed sets: '.$completedSets;
                }
                if (! empty($elog->note)) {
                    $lines[] = '  Exercise note: '.$this->singleLine((string) $elog->note);
                }
                if (! empty($skipReason)) {
                    $lines[] = '  Skip reason: '.$this->singleLine((string) $skipReason);
                }

                foreach ($elog->setLogs as $set) {
                    $setStatus = $set->completed ? 'DONE' : 'SKIPPED';
                    $pr = $set->is_pr ? ' | PR' : '';
                    $lines[] = '  Set '.$set->set_number.': ['.$setStatus.'] reps='.
                        ($set->reps ?? 0).' weight='.
                        ($set->weight ?? 0).'kg duration='.
                        ($set->duration_seconds ?? 0).'s';
                    if ($set->rir !== null) {
                        $lines[count($lines) - 1] .= ' RIR='.($set->rir ?? '');
                    }
                    if ($set->form_rating !== null) {
                        $lines[count($lines) - 1] .= ' form='.(string) $set->form_rating;
                    }
                    if ((int) ($set->pain_level ?? 0) > 0) {
                        $lines[count($lines) - 1] .= ' pain='.$set->pain_level;
                    }
                    if ($pr) {
                        $lines[count($lines) - 1] .= $pr;
                    }
                    if (! empty($set->note)) {
                        $lines[] = '    note: '.$this->singleLine((string) $set->note);
                    }
                }
            }
        }

        $lines[] = '';
        return implode("\n", $lines);
    }

    private function singleLine(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s) ?: $s;
        return $s;
    }

    private function overallStartForUser($user): Carbon
    {
        $min = $user->workoutSessions()->min('workout_date');
        if (! $min) {
            return now()->startOfDay();
        }

        try {
            return Carbon::parse($min)->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    public function exercise(Exercise $exercise)
    {
        abort_if($exercise->user_id !== null && $exercise->user_id !== auth()->id(), 403);

        $sets = WorkoutSetLog::query()
            ->with(['exerciseLog.session.day.plan', 'exerciseLog.workoutExercise'])
            ->whereHas('exerciseLog.workoutExercise', fn ($query) => $query->where('exercise_id', $exercise->id))
            ->whereHas('exerciseLog.session', fn ($query) => $query->where('user_id', auth()->id())->where('status', WorkoutSession::COMPLETED))
            ->orderBy('created_at')
            ->get();

        $setDetailsByDate = $this->buildSetDetailsForExercise($sets);

        $byDate = $sets->groupBy(fn (WorkoutSetLog $set) => $set->exerciseLog->session->workout_date->toDateString())
            ->map(fn ($rows, $date) => [
                'date' => $date,
                'reps' => round($rows->sum(fn ($row) => (float) $row->reps), 2),
                'volume' => round($rows->sum(fn ($row) => (float) $row->reps * (float) ($row->weight ?? 0)), 2),
                'max_weight' => $rows->max(fn ($row) => (float) ($row->weight ?? 0)),
                'best_1rm' => round((float) $rows->map(fn ($r) => (float) ($r->weight ?? 0) * (1 + ((float) ($r->reps ?? 0) / 30)))->max(), 1),
                'avg_rir' => $rows->whereNotNull('rir')->count() ? round($rows->whereNotNull('rir')->avg('rir'), 1) : null,
                'sets' => $rows->count(),
            ])->sortBy('date')->values();

        $allTime1RM = (float) $sets->map(fn ($r) => (float) ($r->weight ?? 0) * (1 + ((float) ($r->reps ?? 0) / 30)))->max();

        return view('workouts.reports.exercise', [
            'exercise' => $exercise,
            'sessions' => $byDate->sortByDesc('date')->values(),
            'chartSessions' => $byDate,
            'setDetailsByDate' => $setDetailsByDate,
            'stats' => [
                'sessions' => $byDate->count(),
                'sets' => $sets->count(),
                'reps' => round($sets->sum(fn ($row) => (float) $row->reps), 2),
                'volume' => round($sets->sum(fn ($row) => (float) $row->reps * (float) ($row->weight ?? 0)), 2),
                'best_weight' => $sets->max(fn ($row) => (float) ($row->weight ?? 0)) ?: null,
                'best_reps' => $sets->max(fn ($row) => (float) ($row->reps ?? 0)) ?: null,
                'all_time_1rm' => round($allTime1RM, 1) ?: null,
            ],
        ]);
    }

    private function buildSetDetailsForExercise($sets): array
    {
        $setsByDate = $sets->groupBy(fn ($set) => $set->exerciseLog?->session?->workout_date?->toDateString() ?? 'unknown');
        $dates = $setsByDate->keys()->sort()->values();

        $prevByKey = []; // set_number|side => last completed snapshot
        $out = [];

        foreach ($dates as $dateKey) {
            $rows = $setsByDate->get($dateKey)?->sortBy('created_at')->values() ?? collect();
            $records = [];

            $prevCreatedAt = null;
            foreach ($rows as $row) {
                $side = $row->side ?: 'both';

                $restEstimate = null;
                if ($prevCreatedAt && $row->created_at) {
                    $restEstimate = max(0, $row->created_at->diffInSeconds($prevCreatedAt));
                } else {
                    $restEstimate = $row->exerciseLog?->workoutExercise?->rest_seconds;
                }

                $completed = (bool) ($row->completed ?? true);
                $note = (string) ($row->note ?? '');
                $skipReason = null;
                if (! $completed) {
                    if (str_starts_with($note, 'Skipped:')) {
                        $skipReason = trim(mb_substr($note, strlen('Skipped:')));
                    } else {
                        $skipReason = $note !== '' ? $note : 'Skipped';
                    }
                }

                $rpe = null;
                if ($row->rir !== null) {
                    $rpe = max(0, min(10, 10 - (float) $row->rir));
                }

                $difficulty = null;
                if ($row->rir !== null) {
                    $rirVal = (float) $row->rir;
                    $difficulty = $rirVal <= 1.0 ? 'Hard' : ($rirVal <= 3.0 ? 'Moderate' : 'Easy');
                }

                $key = ((int) ($row->set_number ?? 0)).'|'.$side;
                $prev = $prevByKey[$key] ?? null;
                $flags = [];

                if ($prev && $prev['completed'] && $completed) {
                    $currWeight = (float) ($row->weight ?? 0);
                    $prevWeight = (float) ($prev['weight'] ?? 0);
                    $currReps = (float) ($row->reps ?? 0);
                    $prevReps = (float) ($prev['reps'] ?? 0);

                    if ($currWeight > $prevWeight && $currReps + 0.0001 < $prevReps && $currWeight > 0) {
                        $flags[] = 'Possible fake progress (weight up, reps down)';
                    }
                    if ($row->rir !== null && $prev['rir'] !== null && $currWeight > $prevWeight && (float) $row->rir > (float) $prev['rir'] + 0.75) {
                        $flags[] = 'Effort looks easier despite higher load (RIR up)';
                    }
                    if ($row->form_rating !== null && $prev['form_rating'] !== null && $currWeight > $prevWeight && (int) $row->form_rating < (int) $prev['form_rating']) {
                        $flags[] = 'Form rating dropped with higher load';
                    }
                    if ((int) ($row->pain_level ?? 0) > (int) ($prev['pain_level'] ?? 0)) {
                        $flags[] = 'Pain level increased';
                    }
                }

                $records[] = [
                    'set_number' => (int) $row->set_number,
                    'side' => $side,
                    'completed' => $completed,
                    'reps' => $row->reps,
                    'weight' => $row->weight,
                    'duration_seconds' => $row->duration_seconds,
                    'rir' => $row->rir,
                    'rpe' => $rpe,
                    'difficulty' => $difficulty,
                    'form_rating' => $row->form_rating,
                    'pain_level' => $row->pain_level,
                    'rest_estimate_seconds' => $restEstimate,
                    'note' => $row->note,
                    'skip_reason' => $skipReason,
                    'is_pr' => (bool) ($row->is_pr ?? false),
                    'flags' => $flags,
                ];

                if ($completed) {
                    $prevByKey[$key] = [
                        'completed' => true,
                        'weight' => $row->weight,
                        'reps' => $row->reps,
                        'rir' => $row->rir,
                        'form_rating' => $row->form_rating,
                        'pain_level' => $row->pain_level,
                    ];
                }

                $prevCreatedAt = $row->created_at;
            }

            $out[$dateKey] = $records;
        }

        return $out;
    }

    private function sessionsBetween(Carbon $start, Carbon $end)
    {
        return auth()->user()->workoutSessions()
            ->whereBetween('workout_date', [$start->toDateString(), $end->toDateString()])
            ->with(['day.plan', 'day.exercises', 'exerciseLogs.workoutExercise.exercise', 'exerciseLogs.setLogs'])
            ->orderBy('workout_date')
            ->orderBy('started_at')
            ->get();
    }

    private function plannedDaysBetween(Carbon $start, Carbon $end): array
    {
        $plans = auth()->user()->workoutPlans()
            ->with('days')
            ->where(function ($query) use ($end): void {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $end->toDateString());
            })
            ->latest('start_date')
            ->latest()
            ->get();
        $planned = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $plan = $plans->first(fn ($candidate) => ! $candidate->start_date || $candidate->start_date->lte($date));
            $weekday = strtolower($date->format('l'));
            $planned[$date->toDateString()] = $plan?->days
                ->where('weekday', $weekday)
                ->whereIn('type', ['training', 'recovery'])
                ->count() ?? 0;
        }

        return $planned;
    }

    private function summarizeSessions($sessions): array
    {
        $exerciseLogs = $sessions->flatMap->exerciseLogs;

        $completedSetRows = 0;
        $sets = $exerciseLogs->flatMap->setLogs;
        $completedSets = $sets->where('completed', true);
        $painSets = $sets->filter(fn ($set) => (int) ($set->pain_level ?? 0) > 0);
        $painSessions = $sessions->filter(fn (WorkoutSession $s) => ! empty($s->pain_note))->count();
        $rirSets = $completedSets->whereNotNull('rir');

        foreach ($exerciseLogs as $elog) {
            $sideMode = $elog->workoutExercise?->side_mode ?: 'bilateral';
            $completed = $elog->setLogs->where('completed', true);

            if ($sideMode === 'per_side') {
                $bySet = $completed->whereIn('side', ['left', 'right'])->groupBy('set_number');
                foreach ($bySet as $rows) {
                    if ($rows->pluck('side')->unique()->count() >= 2) {
                        $completedSetRows++;
                    }
                }
            } else {
                $completedSetRows += $completed->pluck('set_number')->unique()->count();
            }
        }

        return [
            'sessions' => $sessions->count(),
            'completed_sessions' => $sessions->where('status', WorkoutSession::COMPLETED)->count(),
            'exercises' => $exerciseLogs->count(),
            'completed_exercises' => $exerciseLogs->where('completed', true)->count(),
            'sets' => $completedSetRows,
            'reps' => round($completedSets->sum(fn ($set) => (float) ($set->reps ?? 0)), 2),
            'volume' => round($completedSets->sum(fn ($set) => (float) ($set->reps ?? 0) * (float) ($set->weight ?? 0)), 2),
            'avg_rir' => $rirSets->count() ? round($rirSets->avg('rir'), 1) : null,
            'pain_events' => $painSets->count() + $painSessions,
            'minutes' => $sessions->sum(fn (WorkoutSession $session) => $session->durationMinutes() ?? 0),
        ];
    }

    private function plannedSessionsAndSetsBetween(Carbon $start, Carbon $end): array
    {
        $plans = auth()->user()->workoutPlans()->with(['days.exercises'])->
            where(function ($query) use ($end): void {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $end->toDateString());
            })
            ->latest('start_date')
            ->latest()
            ->get();

        $scheduledSessions = 0;
        $scheduledSets = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $plan = $plans->first(fn ($candidate) => ! $candidate->start_date || $candidate->start_date->lte($date));
            $weekday = strtolower($date->format('l'));

            $day = $plan?->days
                ->where('weekday', $weekday)
                ->whereIn('type', ['training', 'recovery'])
                ->first();

            if (! $day) {
                continue;
            }

            $dayExercises = $day->exercises ?? collect();
            if ($dayExercises->isEmpty()) {
                continue;
            }

            $scheduledSessions++;
            $scheduledSets += (int) $dayExercises->sum(fn ($we) => $we->requiredSetCount());
        }

        return [
            'scheduled_sessions' => $scheduledSessions,
            'scheduled_sets' => $scheduledSets,
        ];
    }

    private function skipReasonsFromSessions($sessions): array
    {
        $exerciseLogs = $sessions->flatMap->exerciseLogs;
        $setLogs = $exerciseLogs->flatMap->setLogs;

        $exerciseSkipReasons = [];
        foreach ($exerciseLogs as $elog) {
            if (! empty($elog->skip_reason)) {
                $reason = trim((string) $elog->skip_reason);
                $exerciseSkipReasons[$reason] = ($exerciseSkipReasons[$reason] ?? 0) + 1;
            }
        }

        $setSkipReasons = [];
        foreach ($setLogs->where('completed', false) as $set) {
            $note = (string) ($set->note ?? '');
            $reason = null;
            if (str_starts_with($note, 'Skipped:')) {
                $reason = trim(mb_substr($note, strlen('Skipped:')));
            }
            $reason = $reason ?: 'Skipped';
            $setSkipReasons[$reason] = ($setSkipReasons[$reason] ?? 0) + 1;
        }

        arsort($exerciseSkipReasons);
        arsort($setSkipReasons);

        return [
            array_slice($exerciseSkipReasons, 0, 5, true),
            array_slice($setSkipReasons, 0, 5, true),
        ];
    }

    private function bodyweightStatsBetween(Carbon $start, Carbon $end): array
    {
        // Current window weekly average = [start..start+6] for week mode.
        // For other modes, we still take the last 7 days within the window.
        $currStart = clone $start;
        $currEnd = clone $end;

        if ($end->diffInDays($start) >= 6) {
            // assume week-like window
            $currStart = $start->copy()->startOfDay();
            $currEnd = $start->copy()->addDays(6)->endOfDay();
        } else {
            $currEnd = $end->copy()->endOfDay();
            $currStart = $end->copy()->subDays(6)->startOfDay();
        }

        $prevEnd = $currStart->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays(6)->startOfDay();

        $currAvg = (float) (WorkoutSession::query()
            ->where('user_id', auth()->id())
            ->whereNotNull('bodyweight_kg')
            ->whereBetween('workout_date', [$currStart->toDateString(), $currEnd->toDateString()])
            ->avg('bodyweight_kg'));

        $prevAvg = (float) (WorkoutSession::query()
            ->where('user_id', auth()->id())
            ->whereNotNull('bodyweight_kg')
            ->whereBetween('workout_date', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->avg('bodyweight_kg'));

        if ($currAvg <= 0 || $prevAvg <= 0) {
            $delta = null;
        } else {
            $delta = round($currAvg - $prevAvg, 2);
        }

        return [
            'avg_curr' => $currAvg > 0 ? round($currAvg, 2) : null,
            'delta' => $delta,
        ];
    }

    private function parseDate(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
