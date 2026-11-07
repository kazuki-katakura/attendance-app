<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceReportService
{
    public const MONTHS = 6;

    public const WORK_START = '09:00:00';

    public const WORK_END = '18:00:00';

    public const LONG_WORK_MINUTES = 600;

    /**
     * 過去6ヶ月（今月を含む）の勤怠統計を作る。
     *
     * @return array{summary: array{total_work_minutes: int, total_overtime_minutes: int, avg_work_minutes: int}, monthlyTrend: array<int, array{month: string, work_minutes: int, overtime_minutes: int}>, anomalies: array{late_count: int, early_leave_count: int, long_work_count: int}}
     */
    public function build(User $user): array
    {
        $currentMonth = Carbon::today()->startOfMonth();
        $firstMonth = $currentMonth->copy()->subMonths(self::MONTHS - 1);

        $records = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$firstMonth->toDateString(), $currentMonth->copy()->endOfMonth()->toDateString()])
            ->whereNotNull('clock_out')
            ->orderBy('date')
            ->get();

        return [
            'summary' => $this->summary($records),
            'monthlyTrend' => $this->monthlyTrend($records, $firstMonth),
            'anomalies' => $this->anomalies($records, $currentMonth),
        ];
    }

    /**
     * 総労働時間・総残業時間・1日あたりの平均労働時間（すべて分）。
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @return array{total_work_minutes: int, total_overtime_minutes: int, avg_work_minutes: int}
     */
    private function summary(Collection $records): array
    {
        $totalWork = $records->sum(fn (AttendanceRecord $record): int => $record->workMinutes());

        return [
            'total_work_minutes' => $totalWork,
            'total_overtime_minutes' => $records->sum(fn (AttendanceRecord $record): int => $record->overtimeMinutes()),
            'avg_work_minutes' => $records->isEmpty() ? 0 : (int) round($totalWork / $records->count()),
        ];
    }

    /**
     * 月ごとの労働時間・残業時間。データがない月は 0 にする。
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @return array<int, array{month: string, work_minutes: int, overtime_minutes: int}>
     */
    private function monthlyTrend(Collection $records, Carbon $firstMonth): array
    {
        $byMonth = $records->groupBy(fn (AttendanceRecord $record): string => $record->date->format('Y-m'));

        return collect(range(0, self::MONTHS - 1))
            ->map(fn (int $offset): Carbon => $firstMonth->copy()->addMonths($offset))
            ->map(function (Carbon $month) use ($byMonth): array {
                $monthRecords = $byMonth->get($month->format('Y-m'), collect());

                return [
                    'month' => $month->format('Y/m'),
                    'work_minutes' => $monthRecords->sum(fn (AttendanceRecord $record): int => $record->workMinutes()),
                    'overtime_minutes' => $monthRecords->sum(fn (AttendanceRecord $record): int => $record->overtimeMinutes()),
                ];
            })
            ->all();
    }

    /**
     * 今月の遅刻・早退・長時間労働の回数。
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @return array{late_count: int, early_leave_count: int, long_work_count: int}
     */
    private function anomalies(Collection $records, Carbon $currentMonth): array
    {
        $thisMonth = $records->filter(fn (AttendanceRecord $record): bool => $record->date->isSameMonth($currentMonth));

        return [
            'late_count' => $thisMonth->filter(fn (AttendanceRecord $record): bool => $record->clock_in > self::WORK_START)->count(),
            'early_leave_count' => $thisMonth->filter(fn (AttendanceRecord $record): bool => $record->clock_out < self::WORK_END)->count(),
            'long_work_count' => $thisMonth->filter(fn (AttendanceRecord $record): bool => $record->workMinutes() > self::LONG_WORK_MINUTES)->count(),
        ];
    }
}
