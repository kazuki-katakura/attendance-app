<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MonthlyAttendanceService
{
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /**
     * 指定した月の1日〜末日まで、1日1行の勤怠一覧を作る。勤怠がない日は空欄にする。
     *
     * @return Collection<int, array{id: int|null, date: string, clock_in: string, clock_out: string, total_break_time: string|null, total_time: string|null}>
     */
    public function rows(User $user, Carbon $month): Collection
    {
        $records = $this->records($user, $month)
            ->keyBy(fn (AttendanceRecord $record): string => $record->date->toDateString());

        return collect(range(1, $month->daysInMonth))
            ->map(fn (int $day): Carbon => $month->copy()->day($day))
            ->map(function (Carbon $date) use ($records): array {
                $record = $records->get($date->toDateString());

                return [
                    'id' => $record?->id,
                    'date' => $this->formatDate($date),
                    'clock_in' => AttendanceRecord::formatTime($record?->clock_in),
                    'clock_out' => AttendanceRecord::formatTime($record?->clock_out),
                    'total_break_time' => $record?->total_break_time,
                    'total_time' => $record?->total_time,
                ];
            });
    }

    /**
     * 指定した月の勤怠記録を日付順で取得する。
     *
     * @return Collection<int, AttendanceRecord>
     */
    public function records(User $user, Carbon $month): Collection
    {
        return $user->attendanceRecords()
            ->with('breaks')
            ->inMonth($month)
            ->orderBy('date')
            ->get();
    }

    /**
     * 日付を「06/01(木)」の形式にする。
     */
    public function formatDate(Carbon $date): string
    {
        return $date->format('m/d').'('.self::WEEKDAYS[$date->dayOfWeek].')';
    }
}
