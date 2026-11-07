<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 14：ユーザー情報取得機能（管理者）と CSV 出力
 */
class StaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => '管理者 一郎', 'email' => 'admin@example.com']);
        $this->staff = User::factory()->create(['name' => '山田 太郎', 'email' => 'taro@example.com']);
    }

    public function test_admin_can_see_all_general_users_name_and_email(): void
    {
        User::factory()->create(['name' => '佐藤 花子', 'email' => 'hanako@example.com']);

        $this->actingAs($this->admin)
            ->get('/admin/staff/list')
            ->assertOk()
            ->assertSeeInOrder(['山田 太郎', 'taro@example.com', '/admin/attendance/staff/'.$this->staff->id])
            ->assertSeeInOrder(['佐藤 花子', 'hanako@example.com'])
            ->assertDontSee('admin@example.com');
    }

    public function test_selected_users_attendance_is_displayed_correctly(): void
    {
        $this->createAttendance($this->staff, '2026-11-02', '08:58:00', '18:30:00');
        $this->createAttendance(User::factory()->create(), '2026-11-02', '07:07:00', '16:16:00');

        $this->actingAs($this->admin)
            ->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertOk()
            ->assertSee('山田 太郎さんの勤怠')
            ->assertSeeInOrder(['11/02(月)', '08:58', '18:30', '1:00', '8:32'])
            ->assertDontSee('07:07');
    }

    public function test_previous_month_is_displayed(): void
    {
        $this->createAttendance($this->staff, '2026-10-20', '09:20:00', '18:00:00');

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)->assertSee('?date=2026-10', false);
        $this->actingAs($this->admin)
            ->get('/admin/attendance/staff/'.$this->staff->id.'?date=2026-10')
            ->assertSee('2026/10')
            ->assertSee('09:20');
    }

    public function test_next_month_is_displayed(): void
    {
        $this->createAttendance($this->staff, '2026-12-21', '09:21:00', '18:00:00');

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)->assertSee('?date=2026-12', false);
        $this->actingAs($this->admin)
            ->get('/admin/attendance/staff/'.$this->staff->id.'?date=2026-12')
            ->assertSee('2026/12')
            ->assertSee('09:21');
    }

    public function test_detail_link_goes_to_attendance_detail(): void
    {
        $record = $this->createAttendance($this->staff, '2026-11-05');

        $this->actingAs($this->admin)
            ->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('/admin/attendance/'.$record->id, false);

        $this->actingAs($this->admin)
            ->get('/admin/attendance/'.$record->id)
            ->assertOk()
            ->assertSee('value="11月5日"', false);
    }

    public function test_csv_of_selected_month_can_be_downloaded(): void
    {
        $this->createAttendance($this->staff, '2026-11-02', '09:00:00', '18:00:00');
        $this->createAttendance($this->staff, '2026-10-30', '07:07:00', '16:16:00');

        $response = $this->actingAs($this->admin)
            ->post('/export', ['user_id' => $this->staff->id, 'year_month' => '2026-11']);

        $response->assertOk()->assertDownload('attendance_'.$this->staff->id.'_2026-11.csv');
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('日付,出勤,退勤,休憩,合計', $content);
        $this->assertStringContainsString('11/02(月),09:00,18:00,1:00,8:00', $content);
        $this->assertStringNotContainsString('07:07', $content);
        $this->assertSame(31, substr_count($content, "\n"));
    }

    public function test_csv_export_validates_input(): void
    {
        $this->actingAs($this->admin)
            ->post('/export', ['user_id' => 999, 'year_month' => '2026/11'])
            ->assertSessionHasErrors(['user_id', 'year_month']);
    }
}
