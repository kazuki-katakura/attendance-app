<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;

class AttendanceStampService
{
    public const ACTIONS = ['clock_in', 'break_in', 'break_out', 'clock_out'];

    /**
     * 打刻を実行する。今のステータスでは押せない打刻の場合は false を返す。
     */
    public function stamp(User $user, string $action): bool
    {
        $now = Carbon::now()->format('H:i:00');
        $record = $user->todayAttendanceRecord();

        return match ($action) {
            'clock_in' => $this->clockIn($user, $record, $now),
            'break_in' => $this->breakIn($record, $now),
            'break_out' => $this->breakOut($record, $now),
            'clock_out' => $this->clockOut($record, $now),
            default => false,
        };
    }

    /**
     * 出勤する。1日1回のみ。
     */
    private function clockIn(User $user, ?AttendanceRecord $record, string $now): bool
    {
        if ($record !== null) {
            return false;
        }

        $user->attendanceRecords()->create([
            'date' => Carbon::today()->toDateString(),
            'clock_in' => $now,
        ]);

        return true;
    }

    /**
     * 休憩に入る。出勤中であれば1日に何回でも可能。
     */
    private function breakIn(?AttendanceRecord $record, string $now): bool
    {
        if ($record === null || $record->clock_out !== null || $record->openBreak() !== null) {
            return false;
        }

        $record->breaks()->create(['break_in' => $now]);

        return true;
    }

    /**
     * 休憩から戻る。
     */
    private function breakOut(?AttendanceRecord $record, string $now): bool
    {
        $openBreak = $record?->openBreak();
        if ($openBreak === null) {
            return false;
        }

        $openBreak->update(['break_out' => $now]);
        $record->recalculateTotals();

        return true;
    }

    /**
     * 退勤する。1日1回のみで、休憩中は退勤できない。
     */
    private function clockOut(?AttendanceRecord $record, string $now): bool
    {
        if ($record === null || $record->clock_out !== null || $record->openBreak() !== null) {
            return false;
        }

        $record->clock_out = $now;
        $record->recalculateTotals();

        return true;
    }
}
