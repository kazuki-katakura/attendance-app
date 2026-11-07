<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケースID 4〜8：日時取得・ステータス確認・出勤・休憩・退勤
 */
class StampTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function stamp(string $action): void
    {
        $this->actingAs($this->user)->post('/attendance', ['action' => $action])->assertRedirect('/attendance');
    }

    public function test_current_datetime_is_displayed_in_ui_format(): void
    {
        $this->actingAs($this->user)
            ->get('/attendance')
            ->assertOk()
            ->assertViewHas('formattedDate', '2026年11月18日(水)')
            ->assertViewHas('formattedTime', '10:30')
            ->assertSee('2026年11月18日(水)')
            ->assertSee('10:30');
    }

    public function test_status_is_off_duty_when_not_clocked_in(): void
    {
        $this->actingAs($this->user)->get('/attendance')->assertSee('勤務外');
    }

    public function test_status_is_working_after_clock_in(): void
    {
        $this->createAttendance($this->user, '2026-11-18', '09:00:00', null, []);

        $this->actingAs($this->user)->get('/attendance')->assertSee('出勤中');
    }

    public function test_status_is_on_break_during_break(): void
    {
        $this->createAttendance($this->user, '2026-11-18', '09:00:00', null, [['10:00:00', null]]);

        $this->actingAs($this->user)->get('/attendance')->assertSee('休憩中');
    }

    public function test_status_is_finished_after_clock_out(): void
    {
        $this->createAttendance($this->user, '2026-11-18');

        $this->actingAs($this->user)->get('/attendance')->assertSee('退勤済');
    }

    public function test_clock_in_button_works(): void
    {
        $this->actingAs($this->user)->get('/attendance')->assertSee('value="clock_in">出勤</button>', false);

        $this->stamp('clock_in');

        $this->actingAs($this->user)->get('/attendance')->assertSee('出勤中')->assertDontSee('value="clock_in"', false);
        $this->assertDatabaseHas('attendance_records', ['user_id' => $this->user->id, 'clock_in' => '10:30:00']);
    }

    public function test_clock_in_is_allowed_only_once_a_day(): void
    {
        $this->createAttendance($this->user, '2026-11-18');

        $this->actingAs($this->user)->get('/attendance')->assertDontSee('value="clock_in"', false);

        $this->stamp('clock_in');
        $this->assertSame(1, AttendanceRecord::count());
    }

    public function test_clock_in_time_is_shown_in_attendance_list(): void
    {
        $this->stamp('clock_in');

        $this->actingAs($this->user)->get('/attendance/list')->assertSee('11/18(水)')->assertSee('10:30');
    }

    public function test_break_in_button_works(): void
    {
        $this->stamp('clock_in');
        $this->actingAs($this->user)->get('/attendance')->assertSee('value="break_in">休憩入</button>', false);

        $this->stamp('break_in');

        $this->actingAs($this->user)->get('/attendance')->assertSee('休憩中');
    }

    public function test_break_can_be_taken_many_times_a_day(): void
    {
        $this->stamp('clock_in');
        $this->stamp('break_in');
        $this->stamp('break_out');

        $this->actingAs($this->user)->get('/attendance')->assertSee('value="break_in">休憩入</button>', false);
    }

    public function test_break_out_button_works(): void
    {
        $this->stamp('clock_in');
        $this->stamp('break_in');
        $this->actingAs($this->user)->get('/attendance')->assertSee('value="break_out">休憩戻</button>', false);

        $this->stamp('break_out');

        $this->actingAs($this->user)->get('/attendance')->assertSee('出勤中');
    }

    public function test_break_out_can_be_done_many_times_a_day(): void
    {
        $this->stamp('clock_in');
        $this->stamp('break_in');
        $this->stamp('break_out');
        $this->stamp('break_in');

        $this->actingAs($this->user)->get('/attendance')->assertSee('value="break_out">休憩戻</button>', false);
    }

    public function test_break_time_is_shown_in_attendance_list(): void
    {
        $this->stamp('clock_in');
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 12, 0));
        $this->stamp('break_in');
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 12, 45));
        $this->stamp('break_out');

        $this->actingAs($this->user)->get('/attendance/list')->assertSee('0:45');
        $this->assertDatabaseHas('breaks', ['break_in' => '12:00:00', 'break_out' => '12:45:00']);
    }

    public function test_clock_out_button_works(): void
    {
        $this->stamp('clock_in');
        $this->actingAs($this->user)->get('/attendance')->assertSee('value="clock_out">退勤</button>', false);

        $this->stamp('clock_out');

        $this->actingAs($this->user)->get('/attendance')->assertSee('退勤済')->assertSee('お疲れ様でした。');
    }

    public function test_clock_out_time_is_shown_in_attendance_list(): void
    {
        $this->stamp('clock_in');
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 19, 15));
        $this->stamp('clock_out');

        $this->actingAs($this->user)->get('/attendance/list')->assertSee('19:15')->assertSee('8:45');
    }

    public function test_cannot_clock_out_twice_or_during_break(): void
    {
        $this->stamp('clock_in');
        $this->stamp('break_in');
        $this->stamp('clock_out');
        $this->assertNull(AttendanceRecord::first()->clock_out);

        $this->stamp('break_out');
        $this->stamp('clock_out');
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 20, 0));
        $this->stamp('clock_out');

        $this->assertSame('10:30:00', AttendanceRecord::first()->clock_out);
    }

    public function test_invalid_action_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post('/attendance', ['action' => 'unknown'])
            ->assertSessionHasErrors(['action' => '打刻の種類が不正です']);
    }
}
