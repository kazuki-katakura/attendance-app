<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 20：マイ勤怠レポート機能
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/attendance/report')->assertRedirect('/login');
    }

    public function test_statistics_are_calculated_correctly(): void
    {
        $user = User::factory()->create();
        $this->createAttendance($user, '2026-11-02', '09:00:00', '18:00:00');
        $this->createAttendance($user, '2026-11-03', '09:30:00', '20:00:00');
        $this->createAttendance($user, '2026-11-04', '08:00:00', '21:00:00');
        $this->createAttendance($user, '2026-11-05', '09:00:00', '17:00:00');
        $this->createAttendance($user, '2026-09-10', '09:00:00', '18:00:00');
        $this->createAttendance($user, '2026-04-30', '09:00:00', '18:00:00');
        $this->createAttendance($user, '2026-11-18', '09:00:00', null, []);

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertOk()
            ->assertViewIs('reports.index')
            ->assertViewHas('summary', [
                'total_work_minutes' => 2670,
                'total_overtime_minutes' => 330,
                'avg_work_minutes' => 534,
            ])
            ->assertViewHas('anomalies', [
                'late_count' => 1,
                'early_leave_count' => 1,
                'long_work_count' => 1,
            ])
            ->assertSee('44h 30m')
            ->assertSee('5h 30m')
            ->assertSee('8h 54m');

        $trend = $response->viewData('monthlyTrend');
        $this->assertSame(['2026/06', '2026/07', '2026/08', '2026/09', '2026/10', '2026/11'], array_column($trend, 'month'));
        $this->assertSame([0, 0, 0, 480, 0, 2190], array_column($trend, 'work_minutes'));
        $this->assertSame([0, 0, 0, 0, 0, 330], array_column($trend, 'overtime_minutes'));
    }

    public function test_user_without_records_is_handled_safely(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/attendance/report');

        $response->assertOk()
            ->assertViewHas('summary', ['total_work_minutes' => 0, 'total_overtime_minutes' => 0, 'avg_work_minutes' => 0])
            ->assertViewHas('anomalies', ['late_count' => 0, 'early_leave_count' => 0, 'long_work_count' => 0]);
        $this->assertSame([0, 0, 0, 0, 0, 0], array_column($response->viewData('monthlyTrend'), 'work_minutes'));
    }

    public function test_seeded_user1_matches_expected_values(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs(User::where('email', 'user1@example.com')->first())
            ->get('/attendance/report')
            ->assertViewHas('summary', [
                'total_work_minutes' => 744 * 60,
                'total_overtime_minutes' => 10 * 60,
                'avg_work_minutes' => 8 * 60 + 5,
            ])
            ->assertViewHas('anomalies', ['late_count' => 2, 'early_leave_count' => 1, 'long_work_count' => 1])
            ->assertSee('744h 0m')
            ->assertSee('10h 0m')
            ->assertSee('8h 5m');
    }
}
