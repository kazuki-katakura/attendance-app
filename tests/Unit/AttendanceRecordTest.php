<?php

namespace Tests\Unit;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_and_overtime_minutes_exclude_breaks(): void
    {
        $record = $this->createAttendance(User::factory()->create(), '2026-11-02', '08:00:00', '21:00:00', [['12:00:00', '13:00:00'], ['15:00:00', '15:30:00']]);

        $this->assertSame(90, $record->breakMinutes());
        $this->assertSame(690, $record->workMinutes());
        $this->assertSame(210, $record->overtimeMinutes());
        $this->assertSame('01:30:00', $record->total_break_time);
        $this->assertSame('11:30:00', $record->total_time);
    }

    public function test_working_record_has_no_totals(): void
    {
        $record = $this->createAttendance(User::factory()->create(), '2026-11-18', '09:00:00', null, [['10:00:00', null]]);

        $this->assertSame(0, $record->workMinutes());
        $this->assertNull($record->total_time);
        $this->assertNotNull($record->openBreak());
    }

    public function test_time_helpers(): void
    {
        $this->assertSame(90, AttendanceRecord::minutesBetween('09:00', '10:30:00'));
        $this->assertSame('08:05:00', AttendanceRecord::minutesToTime(485));
        $this->assertSame('09:05', AttendanceRecord::formatTime('09:05:00'));
        $this->assertSame('', AttendanceRecord::formatTime(null));
    }
}
