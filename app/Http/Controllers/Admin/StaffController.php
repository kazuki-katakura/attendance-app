<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportAttendanceRequest;
use App\Http\Requests\MonthRequest;
use App\Models\User;
use App\Services\AttendanceCsvExporter;
use App\Services\MonthlyAttendanceService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    /**
     * 一般ユーザー（スタッフ）の一覧を表示する。
     */
    public function index(): View
    {
        return view('admin.staff-list', [
            'users' => User::where('admin_status', false)->orderBy('id')->get(),
        ]);
    }

    /**
     * スタッフ別の月次勤怠一覧を表示する。
     */
    public function attendance(MonthRequest $request, int $id, MonthlyAttendanceService $monthlyAttendance): View
    {
        $user = User::findOrFail($id);
        $month = $request->month();

        return view('admin.staff-attendance-list', [
            'user' => $user,
            'date' => $month,
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $monthlyAttendance->rows($user, $month),
        ]);
    }

    /**
     * スタッフの月次勤怠を CSV でダウンロードする。
     */
    public function export(ExportAttendanceRequest $request, AttendanceCsvExporter $exporter): StreamedResponse
    {
        return $exporter->download(User::findOrFail($request->validated('user_id')), $request->month());
    }
}
