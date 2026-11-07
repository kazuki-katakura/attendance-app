<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 12：勤怠一覧情報取得機能（管理者）
 */
class DailyAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_all_users_attendance_of_the_day_is_displayed_correctly(): void
    {
        $taro = User::factory()->create(['name' => '山田 太郎']);
        $hanako = User::factory()->create(['name' => '佐藤 花子']);
        $this->createAttendance($taro, '2026-11-18', '09:00:00', '18:00:00');
        $this->createAttendance($hanako, '2026-11-18', '08:30:00', '19:45:00', [['12:00:00', '13:00:00'], ['15:00:00', '15:30:00']]);
        $this->createAttendance($taro, '2026-11-17', '07:07:00', '16:16:00');

        $this->actingAs($this->admin)
            ->get('/admin/attendance/list')
            ->assertOk()
            ->assertSeeInOrder(['山田 太郎', '09:00', '18:00', '1:00', '8:00', '詳細'])
            ->assertSeeInOrder(['佐藤 花子', '08:30', '19:45', '1:30', '9:45', '詳細'])
            ->assertDontSee('07:07');
    }

    public function test_today_is_displayed(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/attendance/list')
            ->assertSee('2026年11月18日の勤怠')
            ->assertSee('2026/11/18');
    }

    public function test_previous_day_is_displayed(): void
    {
        $this->createAttendance(User::factory()->create(), '2026-11-17', '09:17:00', '18:00:00');

        $this->actingAs($this->admin)->get('/admin/attendance/list')->assertSee('?date=2026-11-17', false);
        $this->actingAs($this->admin)
            ->get('/admin/attendance/list?date=2026-11-17')
            ->assertSee('2026/11/17')
            ->assertSee('09:17');
    }

    public function test_next_day_is_displayed(): void
    {
        $this->createAttendance(User::factory()->create(), '2026-11-19', '09:19:00', '18:00:00');

        $this->actingAs($this->admin)->get('/admin/attendance/list')->assertSee('?date=2026-11-19', false);
        $this->actingAs($this->admin)
            ->get('/admin/attendance/list?date=2026-11-19')
            ->assertSee('2026/11/19')
            ->assertSee('09:19');
    }

    public function test_detail_link_goes_to_admin_attendance_detail(): void
    {
        $record = $this->createAttendance(User::factory()->create(), '2026-11-18');

        $this->actingAs($this->admin)
            ->get('/admin/attendance/list')
            ->assertSee('/admin/attendance/'.$record->id, false);
    }
}
