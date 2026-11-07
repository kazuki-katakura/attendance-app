<?php

namespace App\Http\Controllers;

use App\Services\AttendanceReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ勤怠レポート（過去6ヶ月の統計）を表示する。
     */
    public function index(Request $request, AttendanceReportService $reportService): View
    {
        return view('reports.index', $reportService->build($request->user()));
    }
}
