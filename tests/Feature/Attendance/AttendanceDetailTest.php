<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 10：勤怠詳細情報取得機能（一般ユーザー）
 */
class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '山田 太郎']);
    }

    public function test_name_is_login_user_name(): void
    {
        $record = $this->createAttendance($this->user, '2026-11-10');

        $this->actingAs($this->user)->get('/attendance/detail/'.$record->id)->assertSee('value="山田 太郎"', false);
    }

    public function test_date_is_selected_date(): void
    {
        $record = $this->createAttendance($this->user, '2026-11-10');

        $this->actingAs($this->user)
            ->get('/attendance/detail/'.$record->id)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="11月10日"', false);
    }

    public function test_clock_in_and_out_match_stamps(): void
    {
        $record = $this->createAttendance($this->user, '2026-11-10', '08:55:00', '18:20:00');

        $this->actingAs($this->user)
            ->get('/attendance/detail/'.$record->id)
            ->assertSee('name="new_clock_in" value="08:55"', false)
            ->assertSee('name="new_clock_out" value="18:20"', false);
    }

    public function test_breaks_match_stamps_and_one_empty_row_is_added(): void
    {
        $record = $this->createAttendance($this->user, '2026-11-10', '09:00:00', '18:00:00', [['12:00:00', '13:00:00'], ['15:00:00', '15:10:00']]);

        $this->actingAs($this->user)
            ->get('/attendance/detail/'.$record->id)
            ->assertSee('name="new_break_in[0]" value="12:00"', false)
            ->assertSee('name="new_break_out[0]" value="13:00"', false)
            ->assertSee('name="new_break_in[1]" value="15:00"', false)
            ->assertSee('name="new_break_out[1]" value="15:10"', false)
            ->assertSee('name="new_break_in[2]" value=""', false)
            ->assertSee('休憩3');
    }

    public function test_other_users_attendance_is_not_found(): void
    {
        $record = $this->createAttendance(User::factory()->create(), '2026-11-10');

        $this->actingAs($this->user)->get('/attendance/detail/'.$record->id)->assertNotFound();
    }
}
