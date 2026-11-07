<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * テストケースID 13：勤怠詳細情報取得・修正機能（管理者）
 */
class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->staff = User::factory()->create(['name' => '山田 太郎']);
        $this->record = $this->createAttendance($this->staff, '2026-11-10', '08:50:00', '18:10:00', [['12:00:00', '13:00:00']], ['comment' => '通常勤務']);
    }

    /**
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00', ''],
            'new_break_out' => ['13:00', ''],
            'comment' => '打刻漏れの修正',
        ], $overrides);
    }

    private function submit(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from('/admin/attendance/'.$this->record->id)
            ->post('/admin/attendance/'.$this->record->id, $this->input($overrides));
    }

    public function test_detail_shows_selected_attendance(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/attendance/'.$this->record->id)
            ->assertOk()
            ->assertViewIs('admin.admin-detail')
            ->assertSee('value="山田 太郎"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="11月10日"', false)
            ->assertSee('value="08:50"', false)
            ->assertSee('value="18:10"', false)
            ->assertSee('name="new_break_in[0]"'."\n".'                                    value="12:00"', false)
            ->assertSee('通常勤務');
    }

    public function test_error_when_clock_in_is_after_clock_out(): void
    {
        $this->submit(['new_clock_in' => '19:00'])
            ->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);
    }

    public function test_error_when_break_start_is_after_clock_out(): void
    {
        $this->submit(['new_break_in' => ['19:00', ''], 'new_break_out' => ['19:30', '']])
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_error_when_break_end_is_after_clock_out(): void
    {
        $this->submit(['new_break_in' => ['17:00', ''], 'new_break_out' => ['19:00', '']])
            ->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    public function test_error_when_comment_is_empty(): void
    {
        $this->submit(['comment' => ''])
            ->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    public function test_admin_can_update_attendance_directly_and_it_is_reflected_to_user(): void
    {
        $this->submit(['new_break_in' => ['12:00', '15:00'], 'new_break_out' => ['12:45', '15:15']])
            ->assertRedirect('/admin/attendance/'.$this->record->id)
            ->assertSessionHasNoErrors();

        $record = $this->record->fresh();
        $this->assertSame('09:00:00', $record->clock_in);
        $this->assertSame('18:00:00', $record->clock_out);
        $this->assertSame('打刻漏れの修正', $record->comment);
        $this->assertSame('01:00:00', $record->total_break_time);
        $this->assertSame('08:00:00', $record->total_time);
        $this->assertSame(2, $record->breaks()->count());

        $this->actingAs($this->staff)
            ->get('/attendance/detail/'.$this->record->id)
            ->assertSee('name="new_clock_in" value="09:00"', false)
            ->assertSee('name="new_break_in[1]" value="15:00"', false);
    }

    public function test_general_user_cannot_open_admin_detail(): void
    {
        $this->actingAs($this->staff)->get('/admin/attendance/'.$this->record->id)->assertForbidden();
    }
}
