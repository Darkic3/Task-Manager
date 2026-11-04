<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportRange;
use App\Services\Reports\UnifiedReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportsController extends Controller
{
    public function overview(Request $request)
    {
        $request->validate([
            'range' => 'nullable|in:today,week,month,custom',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $range = ReportRange::fromRequest(
            $request->input('range', 'week'),
            $request->input('from'),
            $request->input('to')
        );

        $data = UnifiedReportService::overview(Auth::id(), $range);

        $laws = \App\Services\Reports\LawReportService::summarize(Auth::id(), $range->from, $range->to);
        $prevLaws = \App\Services\Reports\LawReportService::summarize(Auth::id(), $range->prevFrom, $range->prevTo);

        return view('reports.overview', array_merge($data, [
            'range' => $range,
            'periods' => config('routines.periods', []),
            'laws' => $laws,
            'laws_delta' => $laws['total_xp'] - $prevLaws['total_xp'],
        ]));
    }
}
