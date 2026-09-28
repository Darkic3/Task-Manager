<?php

namespace App\Services\Reports;

/**
 * One call for the whole Reports page: every section for the selected
 * window plus previous-window deltas for the headline KPIs.
 */
final class UnifiedReportService
{
    public static function overview(int $userId, ReportRange $range): array
    {
        $from = $range->from;
        $to = $range->to;

        $tasks = TaskReportService::summarize($userId, $from, $to);
        $routines = RoutineReportService::summarize($userId, $from, $to);
        $avoid = AvoidanceReportService::summarize($userId, $from, $to);
        $workouts = WorkoutReportService::summarize($userId, $from, $to);
        $time = TimeReportService::summarize($userId, $from, $to);

        $prevTasks = TaskReportService::summarize($userId, $range->prevFrom, $range->prevTo);
        $prevRoutines = RoutineReportService::summarize($userId, $range->prevFrom, $range->prevTo);
        $prevAvoid = AvoidanceReportService::summarize($userId, $range->prevFrom, $range->prevTo);
        $prevWorkouts = WorkoutReportService::summarize($userId, $range->prevFrom, $range->prevTo);
        $prevTime = TimeReportService::summarize($userId, $range->prevFrom, $range->prevTo);

        $report = [
            'tasks' => $tasks,
            'routines' => $routines,
            'avoid' => $avoid,
            'workouts' => $workouts,
            'time' => $time,
            'deltas' => [
                'tasks_completed' => $tasks['completed'] - $prevTasks['completed'],
                'task_rate' => $tasks['rate'] - $prevTasks['rate'],
                'routine_rate' => $routines['avg_rate'] - $prevRoutines['avg_rate'],
                'slips' => $avoid['slip_total'] - $prevAvoid['slip_total'],
                'workouts' => $workouts['completed'] - $prevWorkouts['completed'],
                'time' => $time['total'] - $prevTime['total'],
            ],
        ];

        $report['insights'] = InsightService::generate($userId, $range, $report);

        return $report;
    }
}
