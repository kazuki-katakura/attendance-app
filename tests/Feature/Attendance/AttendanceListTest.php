<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 9：勤怠一覧情報取得機能（一般ユーザー）
 */
class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_all_own_attendance_records_are_displayed(): void
    {
        $this->createAttendance($this->user, '2026-11-02', '09:00:00', '18:00:00');
        $this->createAttendance($this->user, '2026-11-03', '08:45:00', '19:30:00', [['12:00:00', '13:00:00'], ['15:00:00', '15:15:00']]);
        $this->createAttendance(User::factory()->create(), '2026-11-04', '07:07:00', '16:16:00');

        $response = $this->actingAs($this->user)->get('/attendance/list')->assertOk();

        $response->assertSeeInOrder(['11/02(月)', '09:00', '18:00', '1:00', '8:00', '詳細'])
            ->assertSeeInOrder(['11/03(火)', '08:45', '19:30', '1:15', '9:30', '詳細'])
            ->assertDontSee('07:07');
        $this->assertCount(30, $response->viewData('formattedAttendanceRecords'));
    }

    public function test_current_month_is_displayed(): void
    {
        $this->actingAs($this->user)->get('/attendance/list')->assertSee('2026/11');
    }

    public function test_previous_month_is_displayed(): void
    {
        $this->createAttendance($this->user, '2026-10-15', '09:11:00', '18:00:00');

        $this->actingAs($this->user)
            ->get('/attendance/list')
            ->assertSee('?date=2026-10', false);

        $this->actingAs($this->user)
            ->get('/attendance/list?date=2026-10')
            ->assertSee('2026/10')
            ->assertSee('10/15(木)')
            ->assertSee('09:11');
    }

    public function test_next_month_is_displayed(): void
    {
        $this->createAttendance($this->user, '2026-12-01', '09:22:00', '18:00:00');

        $this->actingAs($this->user)
            ->get('/attendance/list')
            ->assertSee('?date=2026-12', false);

        $this->actingAs($this->user)
            ->get('/attendance/list?date=2026-12')
            ->assertSee('2026/12')
            ->assertSee('12/01(火)')
            ->assertSee('09:22');
    }

    public function test_detail_link_goes_to_attendance_detail(): void
    {
        $record = $this->createAttendance($this->user, '2026-11-10');

        $this->actingAs($this->user)
            ->get('/attendance/list')
            ->assertSee('/attendance/detail/'.$record->id, false);

        $this->actingAs($this->user)
            ->get('/attendance/detail/'.$record->id)
            ->assertOk()
            ->assertViewIs('user.user-detail');
    }
}
