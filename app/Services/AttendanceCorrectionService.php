<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionService
{
    /**
     * 勤怠詳細画面に表示するデータを作る。承認待ちの申請があれば申請内容を表示する。
     *
     * @return array{id: int, year: string, date: string, clock_in: string, clock_out: string, breaks: array<int, array{break_in: string, break_out: string}>, comment: string|null, application: Application|null}
     */
    public function detail(AttendanceRecord $record): array
    {
        $record->loadMissing(['breaks', 'applications.proposalBreaks']);
        $pending = $record->pendingApplication();

        $source = $pending === null
            ? ['clock_in' => $record->clock_in, 'clock_out' => $record->clock_out, 'comment' => $record->comment, 'breaks' => $record->breaks]
            : ['clock_in' => $pending->new_clock_in, 'clock_out' => $pending->new_clock_out, 'comment' => $pending->comment, 'breaks' => $pending->proposalBreaks];

        return [
            'id' => $record->id,
            'year' => $record->date->format('Y年'),
            'date' => $record->date->format('n月j日'),
            'clock_in' => AttendanceRecord::formatTime($source['clock_in']),
            'clock_out' => AttendanceRecord::formatTime($source['clock_out']),
            'breaks' => $source['breaks']
                ->map(fn ($break): array => [
                    'break_in' => AttendanceRecord::formatTime($break->break_in),
                    'break_out' => AttendanceRecord::formatTime($break->break_out),
                ])
                ->values()
                ->all(),
            'comment' => $source['comment'],
            'application' => $pending,
        ];
    }

    /**
     * 一般ユーザーの修正申請を作成する（承認されるまで勤怠記録は変えない）。
     *
     * @param  array{clock_in: string, clock_out: string, comment: string}  $attendance
     * @param  array<int, array{break_in: string, break_out: string}>  $breaks
     */
    public function submit(User $user, AttendanceRecord $record, array $attendance, array $breaks): Application
    {
        return DB::transaction(function () use ($user, $record, $attendance, $breaks): Application {
            $application = $user->applications()->create([
                'attendance_record_id' => $record->id,
                'approval_status' => Application::STATUS_PENDING,
                'application_date' => Carbon::today()->toDateString(),
                'new_date' => $record->date->toDateString(),
                'new_clock_in' => $attendance['clock_in'],
                'new_clock_out' => $attendance['clock_out'],
                'comment' => $attendance['comment'],
            ]);
            $application->proposalBreaks()->createMany($breaks);

            return $application;
        });
    }

    /**
     * 管理者が勤怠記録を直接修正する。
     *
     * @param  array{clock_in: string, clock_out: string, comment: string}  $attendance
     * @param  array<int, array{break_in: string, break_out: string}>  $breaks
     */
    public function update(AttendanceRecord $record, array $attendance, array $breaks): void
    {
        DB::transaction(function () use ($record, $attendance, $breaks): void {
            $record->fill($attendance);
            $record->breaks()->delete();
            $record->breaks()->createMany($breaks);
            $record->recalculateTotals();
        });
    }

    /**
     * 修正申請を承認し、申請内容を勤怠記録に反映する。
     */
    public function approve(Application $application): void
    {
        DB::transaction(function () use ($application): void {
            $application->loadMissing(['attendanceRecord', 'proposalBreaks']);

            $this->update(
                $application->attendanceRecord,
                [
                    'clock_in' => $application->new_clock_in,
                    'clock_out' => $application->new_clock_out,
                    'comment' => $application->comment,
                ],
                $application->proposalBreaks
                    ->map(fn ($break): array => ['break_in' => $break->break_in, 'break_out' => $break->break_out])
                    ->all()
            );

            $application->update(['approval_status' => Application::STATUS_APPROVED]);
        });
    }
}
