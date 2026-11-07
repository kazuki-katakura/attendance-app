<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceCsvExporter
{
    private const HEADER = ['日付', '出勤', '退勤', '休憩', '合計'];

    private const BOM = "\xEF\xBB\xBF";

    public function __construct(private MonthlyAttendanceService $monthlyAttendance) {}

    /**
     * スタッフの月次勤怠を、画面と同じ列構成の BOM 付き CSV でダウンロードさせる。
     */
    public function download(User $user, Carbon $month): StreamedResponse
    {
        $rows = $this->monthlyAttendance->rows($user, $month)
            ->map(fn (array $row): array => [
                $row['date'],
                $row['clock_in'],
                $row['clock_out'],
                $this->formatDuration($row['total_break_time']),
                $this->formatDuration($row['total_time']),
            ]);

        $fileName = sprintf('attendance_%d_%s.csv', $user->id, $month->format('Y-m'));

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, self::BOM);
            fputcsv($handle, self::HEADER);
            $rows->each(fn (array $row) => fputcsv($handle, $row));
            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 「01:30:00」を画面と同じ「1:30」にする。
     */
    private function formatDuration(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('G:i') : '';
    }
}
