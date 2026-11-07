<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Http\Requests\DayRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * その日の全ユーザーの勤怠一覧を表示する。
     */
    public function index(DayRequest $request): View
    {
        $day = $request->day();

        return view('admin.admin-attendance-list', [
            'date' => $day,
            'previousDay' => $day->copy()->subDay()->toDateString(),
            'nextDay' => $day->copy()->addDay()->toDateString(),
            'users' => User::orderBy('id')->get(),
            'attendanceRecords' => AttendanceRecord::with('user')->whereDate('date', $day)->get(),
        ]);
    }

    /**
     * 勤怠詳細を表示する。
     */
    public function show(int $id, AttendanceCorrectionService $correctionService): View
    {
        $record = AttendanceRecord::with(['user', 'breaks'])->findOrFail($id);
        $detail = $correctionService->detail($record);

        return view('admin.admin-detail', [
            'user' => $record->user,
            'attendanceRecord' => array_merge($detail, $this->currentValues($record)),
        ]);
    }

    /**
     * 管理者として勤怠を直接修正する。
     */
    public function update(AdminAttendanceUpdateRequest $request, int $id, AttendanceCorrectionService $correctionService): RedirectResponse
    {
        $record = AttendanceRecord::findOrFail($id);

        $correctionService->update($record, $request->attendance(), $request->breaks());

        return redirect("/admin/attendance/{$id}")->with('message', '勤怠を修正しました');
    }

    /**
     * 管理者の詳細画面には、承認待ちの申請ではなく現在の勤怠記録の値を表示する。
     *
     * @return array{clock_in: string, clock_out: string, breaks: array<int, array{break_in: string, break_out: string}>, comment: string|null}
     */
    private function currentValues(AttendanceRecord $record): array
    {
        return [
            'clock_in' => AttendanceRecord::formatTime($record->clock_in),
            'clock_out' => AttendanceRecord::formatTime($record->clock_out),
            'breaks' => $record->breaks
                ->map(fn ($break): array => [
                    'break_in' => AttendanceRecord::formatTime($break->break_in),
                    'break_out' => AttendanceRecord::formatTime($break->break_out),
                ])
                ->all(),
            'comment' => $record->comment,
        ];
    }
}
