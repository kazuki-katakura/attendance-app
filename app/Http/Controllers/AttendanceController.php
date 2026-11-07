<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Http\Requests\AttendanceStampRequest;
use App\Http\Requests\MonthRequest;
use App\Models\AttendanceRecord;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceStampService;
use App\Services\MonthlyAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /**
     * 勤怠登録（打刻）画面を表示する。
     */
    public function index(Request $request): View
    {
        $now = Carbon::now();

        return view('user.attendance-register', [
            'user' => $request->user(),
            'formattedDate' => $now->format('Y年n月j日').'('.self::WEEKDAYS[$now->dayOfWeek].')',
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    /**
     * 出勤・休憩入・休憩戻・退勤を打刻する。
     */
    public function store(AttendanceStampRequest $request, AttendanceStampService $stampService): RedirectResponse
    {
        if (! $stampService->stamp($request->user(), $request->validated('action'))) {
            return redirect('/attendance')->with('error', '現在のステータスでは打刻できません');
        }

        return redirect('/attendance');
    }

    /**
     * 自分の月次勤怠一覧を表示する。
     */
    public function list(MonthRequest $request, MonthlyAttendanceService $monthlyAttendance): View
    {
        $month = $request->month();

        return view('user.user-attendance-list', [
            'date' => $month,
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $monthlyAttendance->rows($request->user(), $month),
        ]);
    }

    /**
     * 自分の勤怠詳細を表示する。他人の勤怠は 404 にする。
     */
    public function show(Request $request, int $id, AttendanceCorrectionService $correctionService): View
    {
        $record = $this->findOwnRecord($request, $id);

        return view('user.user-detail', [
            'user' => $request->user(),
            'data' => $correctionService->detail($record),
        ]);
    }

    /**
     * 勤怠の修正申請を行う。承認待ちの申請がある間は申請できない。
     */
    public function update(AttendanceCorrectionRequest $request, int $id, AttendanceCorrectionService $correctionService): RedirectResponse
    {
        $record = $this->findOwnRecord($request, $id);

        if ($record->pendingApplication() !== null) {
            return redirect("/attendance/detail/{$id}");
        }

        $correctionService->submit($request->user(), $record, $request->attendance(), $request->breaks());

        return redirect('/stamp_correction_request/list');
    }

    /**
     * ログインユーザー自身の勤怠記録を取得する。
     */
    private function findOwnRecord(Request $request, int $id): AttendanceRecord
    {
        return $request->user()
            ->attendanceRecords()
            ->with(['breaks', 'applications.proposalBreaks'])
            ->findOrFail($id);
    }
}
