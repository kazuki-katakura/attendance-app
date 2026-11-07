<?php

namespace Tests\Feature\Attendance;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * テストケースID 11：勤怠詳細情報修正機能（一般ユーザー）
 */
class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '山田 太郎']);
        $this->record = $this->createAttendance($this->user, '2026-11-10');
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
            'comment' => '電車遅延のため',
        ], $overrides);
    }

    private function submit(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)
            ->from('/attendance/detail/'.$this->record->id)
            ->post('/attendance/detail/'.$this->record->id, $this->input($overrides));
    }

    public function test_error_when_clock_in_is_after_clock_out(): void
    {
        $this->submit(['new_clock_in' => '19:00'])
            ->assertRedirect('/attendance/detail/'.$this->record->id)
            ->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);
    }

    public function test_error_when_break_start_is_after_clock_out(): void
    {
        $this->submit(['new_break_in' => ['19:00', ''], 'new_break_out' => ['19:30', '']])
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_error_when_break_start_is_before_clock_in(): void
    {
        $this->submit(['new_break_in' => ['08:00', ''], 'new_break_out' => ['08:30', '']])
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

    public function test_error_when_time_format_is_invalid(): void
    {
        $this->submit(['new_clock_in' => 'abc', 'new_break_in' => ['1200', ''], 'new_break_out' => ['13:00', '']])
            ->assertSessionHasErrors([
                'new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です',
                'new_break_in.0' => '休憩時間が不適切な値です',
            ]);
    }

    public function test_correction_request_is_shown_to_admin(): void
    {
        $this->submit(['new_clock_in' => '09:30', 'new_break_in' => ['12:00', '15:00'], 'new_break_out' => ['13:00', '15:15']])
            ->assertRedirect('/stamp_correction_request/list');

        $application = Application::first();
        $this->assertSame(Application::STATUS_PENDING, $application->approval_status);
        $this->assertSame('09:30:00', $application->new_clock_in);
        $this->assertSame(2, $application->proposalBreaks()->count());
        $this->assertSame('09:00:00', $this->record->fresh()->clock_in);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get('/stamp_correction_request/list')
            ->assertSeeInOrder(['承認待ち', '山田 太郎', '2026/11/10', '電車遅延のため', '2026/11/18']);
        $this->actingAs($admin)
            ->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertSee('value="09:30"', false)
            ->assertSee('value="15:15"', false);
    }

    public function test_pending_list_shows_all_own_requests(): void
    {
        $this->submit(['comment' => '1件目の申請']);
        $other = $this->createAttendance($this->user, '2026-11-11');
        $this->actingAs($this->user)->post('/attendance/detail/'.$other->id, $this->input(['comment' => '2件目の申請']));
        $this->createAttendance(User::factory()->create(), '2026-11-12')
            ->applications()->create([
                'user_id' => User::factory()->create()->id,
                'approval_status' => Application::STATUS_PENDING,
                'application_date' => '2026-11-18',
                'new_date' => '2026-11-12',
                'new_clock_in' => '09:00:00',
                'new_clock_out' => '18:00:00',
                'comment' => '他人の申請',
            ]);

        $this->actingAs($this->user)
            ->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertViewIs('user.user-application-list')
            ->assertSee('1件目の申請')
            ->assertSee('2件目の申請')
            ->assertDontSee('他人の申請');
    }

    public function test_approved_list_shows_requests_approved_by_admin(): void
    {
        $this->submit(['comment' => '承認される申請']);
        $application = Application::first();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/stamp_correction_request/approve/'.$application->id);

        $formatted = $this->actingAs($this->user)
            ->get('/stamp_correction_request/list')
            ->viewData('formattedApplications');

        $this->assertSame('承認済み', $formatted->firstWhere('comment', '承認される申請')['approval_status']);
    }

    public function test_detail_link_goes_to_attendance_detail_and_pending_cannot_be_edited(): void
    {
        $this->submit();

        $this->actingAs($this->user)
            ->get('/stamp_correction_request/list')
            ->assertSee('/attendance/detail/'.$this->record->id, false);

        $this->actingAs($this->user)
            ->get('/attendance/detail/'.$this->record->id)
            ->assertOk()
            ->assertSee('承認待ちのため修正はできません。')
            ->assertDontSee('<button class="form__button--submit" type="submit">修正</button>', false);

        $this->submit(['comment' => '二重申請']);
        $this->assertSame(1, Application::count());
    }
}
