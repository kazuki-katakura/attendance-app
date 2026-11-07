<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * 勤怠記録の持ち主が、その勤怠に対して申請した形で作る。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_record_id' => AttendanceRecord::factory(),
            'user_id' => fn (array $attributes): int => AttendanceRecord::find($attributes['attendance_record_id'])->user_id,
            'approval_status' => Application::STATUS_PENDING,
            'application_date' => Carbon::today()->toDateString(),
            'new_date' => fn (array $attributes): string => AttendanceRecord::find($attributes['attendance_record_id'])->date->toDateString(),
            'new_clock_in' => '09:30:00',
            'new_clock_out' => '18:30:00',
            'comment' => '電車遅延のため',
        ];
    }

    /**
     * 承認済みの申請にする。
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => Application::STATUS_APPROVED,
        ]);
    }
}
