<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ApplicationSeeder extends Seeder
{
    /**
     * user2 の修正申請（承認待ち1件・承認済み1件）を作成する。
     */
    public function run(): void
    {
        $user = User::where('email', 'user2@example.com')->firstOrFail();
        [$pendingRecord, $approvedRecord] = $user->attendanceRecords()->orderByDesc('date')->take(2)->get();

        $pending = $user->applications()->create([
            'attendance_record_id' => $pendingRecord->id,
            'approval_status' => Application::STATUS_PENDING,
            'application_date' => Carbon::today()->toDateString(),
            'new_date' => $pendingRecord->date->toDateString(),
            'new_clock_in' => '09:00:00',
            'new_clock_out' => '18:00:00',
            'comment' => '退勤の打刻を忘れたため',
        ]);
        $pending->proposalBreaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);

        $approved = $user->applications()->create([
            'attendance_record_id' => $approvedRecord->id,
            'approval_status' => Application::STATUS_APPROVED,
            'application_date' => $approvedRecord->date->copy()->addDay()->toDateString(),
            'new_date' => $approvedRecord->date->toDateString(),
            'new_clock_in' => $approvedRecord->clock_in,
            'new_clock_out' => $approvedRecord->clock_out,
            'comment' => '電車遅延のため',
        ]);
        $approved->proposalBreaks()->createMany(
            $approvedRecord->breaks->map(fn ($break): array => ['break_in' => $break->break_in, 'break_out' => $break->break_out])->all()
        );
        $approvedRecord->update(['comment' => '電車遅延のため']);
    }
}
