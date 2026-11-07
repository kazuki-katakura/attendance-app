<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 15：勤怠情報修正機能（管理者）
 */
class ApplicationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function createCorrectionRequest(User $user, string $date, string $status, string $comment): Application
    {
        $record = $this->createAttendance($user, $date);
        $application = Application::factory()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $user->id,
            'approval_status' => $status,
            'comment' => $comment,
        ]);
        $application->proposalBreaks()->createMany([
            ['break_in' => '12:30:00', 'break_out' => '13:15:00'],
            ['break_in' => '16:00:00', 'break_out' => '16:15:00'],
        ]);

        return $application;
    }

    public function test_all_pending_requests_are_displayed(): void
    {
        $this->createCorrectionRequest(User::factory()->create(['name' => '山田 太郎']), '2026-11-10', Application::STATUS_PENDING, '山田の申請');
        $this->createCorrectionRequest(User::factory()->create(['name' => '佐藤 花子']), '2026-11-11', Application::STATUS_PENDING, '佐藤の申請');

        $response = $this->actingAs($this->admin)->get('/stamp_correction_request/list');

        $response->assertOk()
            ->assertViewIs('admin.admin-application-list')
            ->assertSeeInOrder(['承認待ち', '佐藤 花子', '2026/11/11', '佐藤の申請'])
            ->assertSee('山田の申請');
        $this->assertCount(2, $response->viewData('applications')->where('approval_status', '承認待ち'));
    }

    public function test_all_approved_requests_are_displayed(): void
    {
        $this->createCorrectionRequest(User::factory()->create(), '2026-11-10', Application::STATUS_APPROVED, '承認済みの申請A');
        $this->createCorrectionRequest(User::factory()->create(), '2026-11-11', Application::STATUS_APPROVED, '承認済みの申請B');
        $this->createCorrectionRequest(User::factory()->create(), '2026-11-12', Application::STATUS_PENDING, '承認待ちの申請');

        $response = $this->actingAs($this->admin)->get('/stamp_correction_request/list');

        $approved = $response->viewData('applications')->where('approval_status', '承認済み');
        $this->assertEqualsCanonicalizing(['承認済みの申請A', '承認済みの申請B'], $approved->pluck('comment')->all());
        $response->assertSee('承認済みの申請A')->assertSee('承認済みの申請B');
    }

    public function test_request_detail_is_displayed_correctly(): void
    {
        $application = $this->createCorrectionRequest(User::factory()->create(['name' => '山田 太郎']), '2026-11-10', Application::STATUS_PENDING, '電車遅延のため');

        $this->actingAs($this->admin)
            ->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertViewIs('admin.admin-application-detail')
            ->assertSee('value="山田 太郎"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="11月10日"', false)
            ->assertSee('value="09:30"', false)
            ->assertSee('value="18:30"', false)
            ->assertSee('value="12:30"', false)
            ->assertSee('value="16:15"', false)
            ->assertSee('電車遅延のため')
            ->assertSee('承認</button>', false);
    }

    public function test_approval_updates_attendance_and_status(): void
    {
        $staff = User::factory()->create();
        $application = $this->createCorrectionRequest($staff, '2026-11-10', Application::STATUS_PENDING, '電車遅延のため');

        $this->actingAs($this->admin)
            ->post('/stamp_correction_request/approve/'.$application->id)
            ->assertRedirect('/stamp_correction_request/approve/'.$application->id);

        $this->assertSame(Application::STATUS_APPROVED, $application->fresh()->approval_status);

        $record = AttendanceRecord::find($application->attendance_record_id);
        $this->assertSame('09:30:00', $record->clock_in);
        $this->assertSame('18:30:00', $record->clock_out);
        $this->assertSame('電車遅延のため', $record->comment);
        $this->assertSame(['12:30:00', '16:00:00'], $record->breaks()->pluck('break_in')->all());
        $this->assertSame('01:00:00', $record->total_break_time);
        $this->assertSame('08:00:00', $record->total_time);

        $this->actingAs($this->admin)
            ->get('/stamp_correction_request/approve/'.$application->id)
            ->assertSee('<p class="applied-form__item">承認済み</p>', false);

        $this->actingAs($staff)
            ->get('/stamp_correction_request/list')
            ->assertSeeInOrder(['承認済み', '電車遅延のため']);
    }

    public function test_general_user_cannot_approve(): void
    {
        $staff = User::factory()->create();
        $application = $this->createCorrectionRequest($staff, '2026-11-10', Application::STATUS_PENDING, '申請');

        $this->actingAs($staff)->get('/stamp_correction_request/approve/'.$application->id)->assertForbidden();
        $this->actingAs($staff)->post('/stamp_correction_request/approve/'.$application->id)->assertForbidden();
        $this->assertSame(Application::STATUS_PENDING, $application->fresh()->approval_status);
    }
}
