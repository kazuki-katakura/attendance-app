<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => Carbon::today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ];
    }

    /**
     * 作成後に休憩・労働時間の合計を計算する。
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (AttendanceRecord $record) => $record->recalculateTotals());
    }

    /**
     * 退勤していない（出勤中の）勤怠にする。
     */
    public function working(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_out' => null,
        ]);
    }
}
