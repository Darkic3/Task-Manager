<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Cache;

/**
 * One call for the whole Reports page: every section for the selected
 * window plus previous-window deltas for the headline KPIs.
 *
 * The assembled overview is cached per user + range for 120 seconds
 * (reports aggregate ~12 bounded queries; a short TTL keeps the page
 * snappy while staying fresh enough for study tracking).
 */
final class UnifiedReportService
{
    public static function overview(int $userId, ReportRange $range): array
    {
        $key = "reports:overview:{$userId}:{$range->preset}:{$range->from->toDateString()}:{$range->to->toDateString()}";

        return Cache::remember($key, 120, fn () => self::build($userId, $range));
    }

    private static function build(int $userId, ReportRange $range): array
    {        $from = $range->from;
        $to = $range->to;

        $tasks = TaskReportService::summarize($userId, $from, $to);
        $routines = RoutineReportService::summarize($userId, $from, $to);
        $avoid = AvoidanceReportService::summarize($userId, $from, $to);
        $workouts = WorkoutReportService::summarize($userId, $from, $to);
        $time = TimeReportService::summarize($userId, $from, $to);
        $study = StudyReportService::summarize($userId, $from, $to);

        $prevTasks = TaskReportService::summarize($userId, $range->prevFrom, $range->prevTo);
        $prevRoutines = RoutineReportService::summarize($userId, $range->prevFrom, $range->prevTo);
        $prevAvoid = AvoidanceReportService::summarize($userId, $range->prevFrom, $range->prevTo);
        $prevWorkouts = WorkoutReportService::summarize($userId, $range->prevFrom, $range->prevTo);
        $prevTime = TimeReportService::summarize($userId, $range->prevFrom, $range->prevTo);
        $prevStudy = StudyReportService::summarize($userId, $range->prevFrom, $range->prevTo);

        $report = [
            'tasks' => $tasks,
            'routines' => $routines,
            'avoid' => $avoid,
            'workouts' => $workouts,
            'time' => $time,
            'study' => $study,
            'deltas' => [
                'tasks_completed' => $tasks['completed'] - $prevTasks['completed'],
                'task_rate' => $tasks['rate'] - $prevTasks['rate'],
                'routine_rate' => $routines['avg_rate'] - $prevRoutines['avg_rate'],
                'slips' => $avoid['slip_total'] - $prevAvoid['slip_total'],
                'workouts' => $workouts['completed'] - $prevWorkouts['completed'],
                'time' => $time['total'] - $prevTime['total'],
                'study_pages' => round($study['pages_completed'] - $prevStudy['pages_completed'], 1),
                'study_goal_rate' => $study['goal_rate'] - $prevStudy['goal_rate'],
            ],
        ];

        $report['insights'] = InsightService::generate($userId, $range, $report);

        return $report;
    }
}
