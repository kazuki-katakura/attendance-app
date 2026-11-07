<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * テストケースID 18：公開API 書き込み系
 */
class AttendanceRecordWriteApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_store_creates_attendance_record(): void
    {
        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'APIから登録',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.date', '2026-11-02')
            ->assertJsonPath('data.total_time', '09:00');

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->user->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'comment' => 'APIから登録',
        ]);
    }

    public function test_store_returns_422_with_japanese_messages(): void
    {
        $this->postJson('/api/v1/attendance-records', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', '勤怠日は必須です。')
            ->assertJsonPath('errors.clock_in.0', '出勤時刻は必須です。');

        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026/11/02',
            'clock_in' => '9:00',
            'clock_out' => '18:00',
            'comment' => str_repeat('あ', 256),
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', '勤怠日は YYYY-MM-DD 形式で指定してください。')
            ->assertJsonPath('errors.clock_in.0', '出勤時刻は HH:MM:SS 形式で指定してください。')
            ->assertJsonPath('errors.clock_out.0', '退勤時刻は HH:MM:SS 形式で指定してください。')
            ->assertJsonPath('errors.comment.0', '備考は 255 文字以内で入力してください。');

        $this->createAttendance($this->user, '2026-11-02');
        $this->postJson('/api/v1/attendance-records', ['date' => '2026-11-02', 'clock_in' => '18:00:00', 'clock_out' => '09:00:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', 'この日付の勤怠は既に登録されています。')
            ->assertJsonPath('errors.clock_out.0', '退勤時刻は出勤時刻より後の時刻を指定してください。');
    }

    public function test_update_updates_record_and_returns_404_for_unknown_id(): void
    {
        $record = $this->createAttendance($this->user, '2026-11-02');

        $this->putJson('/api/v1/attendance-records/'.$record->id, [
            'date' => '2026-11-02',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'comment' => '更新しました',
        ])
            ->assertOk()
            ->assertJsonPath('data.clock_in', '10:00:00')
            ->assertJsonPath('data.comment', '更新しました');

        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'clock_in' => '10:00:00', 'total_time' => '08:00:00']);

        $this->putJson('/api/v1/attendance-records/99999', ['comment' => 'x'])
            ->assertNotFound()
            ->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }

    public function test_update_supports_partial_update_and_validation(): void
    {
        $record = $this->createAttendance($this->user, '2026-11-02');

        $this->patchJson('/api/v1/attendance-records/'.$record->id, ['comment' => '備考だけ更新'])
            ->assertOk()
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.comment', '備考だけ更新');

        $this->patchJson('/api/v1/attendance-records/'.$record->id, ['clock_out' => '08:00:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.clock_out.0', '退勤時刻は出勤時刻より後の時刻を指定してください。');

        $this->patchJson('/api/v1/attendance-records/'.$record->id, ['date' => '2026-11-02'])->assertOk();

        $this->createAttendance($this->user, '2026-11-03');
        $this->patchJson('/api/v1/attendance-records/'.$record->id, ['date' => '2026-11-03'])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', 'この日付の勤怠は既に登録されています。');
    }

    public function test_destroy_deletes_record_and_returns_404_for_unknown_id(): void
    {
        $record = $this->createAttendance($this->user, '2026-11-02');

        $this->deleteJson('/api/v1/attendance-records/'.$record->id)->assertNoContent();

        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
        $this->assertDatabaseMissing('breaks', ['attendance_record_id' => $record->id]);

        $this->deleteJson('/api/v1/attendance-records/99999')
            ->assertNotFound()
            ->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }
}
