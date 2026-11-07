<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceSeeder extends Seeder
{
    /**
     * user1 の今月のパターン（出勤・退勤）と日数。
     */
    private const USER1_CURRENT_MONTH_PATTERNS = [
        ['clock_in' => '09:00:00', 'clock_out' => '18:00:00', 'days' => 10],
        ['clock_in' => '09:00:00', 'clock_out' => '20:00:00', 'days' => 3],
        ['clock_in' => '09:30:00', 'clock_out' => '18:00:00', 'days' => 2],
        ['clock_in' => '09:00:00', 'clock_out' => '17:00:00', 'days' => 1],
        ['clock_in' => '08:00:00', 'clock_out' => '21:00:00', 'days' => 1],
    ];

    /**
     * 全ユーザーの勤怠と休憩を作成する。
     */
    public function run(): void
    {
        $this->seedUser1(User::where('email', 'user1@example.com')->firstOrFail());

        User::whereIn('email', ['user2@example.com', 'user3@example.com'])
            ->get()
            ->each(fn (User $user) => $this->seedRealisticRecords($user));
    }

    /**
     * user1：マイ勤怠レポートの予測値（総労働744時間 等）になるデータ。
     * 過去5ヶ月は各月の平日15日を通常勤務、今月は平日17日を決まったパターンで作る。
     * 今日は打刻を試せるように空けておく。
     */
    private function seedUser1(User $user): void
    {
        $today = Carbon::today();

        collect(range(5, 1))
            ->flatMap(fn (int $monthsAgo): Collection => $this->weekdays($today->copy()->startOfMonth()->subMonths($monthsAgo))->take(15))
            ->each(fn (Carbon $date) => $this->createRecord($user, $date, '09:00:00', '18:00:00', [['12:00:00', '13:00:00']]));

        $currentMonthDays = $this->weekdays($today->copy()->startOfMonth())
            ->reject(fn (Carbon $date): bool => $date->isSameDay($today))
            ->take(17)
            ->values();

        collect(self::USER1_CURRENT_MONTH_PATTERNS)
            ->flatMap(fn (array $pattern): array => array_fill(0, $pattern['days'], $pattern))
            ->each(fn (array $pattern, int $index) => $this->createRecord(
                $user,
                $currentMonthDays[$index],
                $pattern['clock_in'],
                $pattern['clock_out'],
                [['12:00:00', '13:00:00']]
            ));
    }

    /**
     * user1 以外：過去2ヶ月〜昨日までの平日に、ばらつきのある勤怠を作る。
     */
    private function seedRealisticRecords(User $user): void
    {
        $start = Carbon::today()->startOfMonth()->subMonths(2);

        collect(range(0, $start->diffInDays(Carbon::yesterday())))
            ->map(fn (int $offset): Carbon => $start->copy()->addDays($offset))
            ->filter(fn (Carbon $date): bool => $date->isWeekday() && fake()->boolean(90))
            ->each(function (Carbon $date) use ($user): void {
                $clockIn = Carbon::createFromTime(8, 45)->addMinutes(fake()->numberBetween(0, 30));
                $clockOut = Carbon::createFromTime(17, 55)->addMinutes(fake()->numberBetween(0, 120));
                $breaks = [['12:00:00', '13:00:00']];

                if (fake()->boolean(30)) {
                    $breaks[] = ['15:00:00', '15:15:00'];
                }

                $this->createRecord($user, $date, $clockIn->format('H:i:00'), $clockOut->format('H:i:00'), $breaks);
            });
    }

    /**
     * 勤怠1日分と休憩を作成し、合計時間を計算する。
     *
     * @param  array<int, array{0: string, 1: string}>  $breaks
     */
    private function createRecord(User $user, Carbon $date, string $clockIn, string $clockOut, array $breaks): AttendanceRecord
    {
        $record = $user->attendanceRecords()->create([
            'date' => $date->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        $record->breaks()->createMany(
            collect($breaks)->map(fn (array $break): array => ['break_in' => $break[0], 'break_out' => $break[1]])->all()
        );

        return $record->recalculateTotals();
    }

    /**
     * 指定した月の平日を日付順に返す。
     *
     * @return Collection<int, Carbon>
     */
    private function weekdays(Carbon $month): Collection
    {
        return collect(range(1, $month->daysInMonth))
            ->map(fn (int $day): Carbon => $month->copy()->day($day))
            ->filter(fn (Carbon $date): bool => $date->isWeekday())
            ->values();
    }
}
