<?php

namespace Tests;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 10, 30, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * 勤怠と休憩をまとめて作成し、合計時間を計算する。
     *
     * @param  array<int, array{0: string, 1: string|null}>  $breaks
     */
    protected function createAttendance(User $user, string $date, string $clockIn = '09:00:00', ?string $clockOut = '18:00:00', array $breaks = [['12:00:00', '13:00:00']], array $attributes = []): AttendanceRecord
    {
        $record = AttendanceRecord::factory()->for($user)->create(array_merge([
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ], $attributes));

        $record->breaks()->createMany(
            collect($breaks)->map(fn (array $break): array => ['break_in' => $break[0], 'break_out' => $break[1]])->all()
        );

        return $record->recalculateTotals();
    }
}
