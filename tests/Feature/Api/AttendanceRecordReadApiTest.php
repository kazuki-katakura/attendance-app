<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 17：公開API 読み取り系
 */
class AttendanceRecordReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_json_with_data_and_meta(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->getJson('/api/v1/attendance-records')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonStructure([
                'data' => [['id', 'user_id', 'user_name', 'date', 'clock_in', 'clock_out', 'total_time', 'total_break_time', 'comment']],
                'links',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_index_can_be_filtered_and_paginated(): void
    {
        $taro = User::factory()->create(['name' => '山田 太郎']);
        $hanako = User::factory()->create();
        $this->createAttendance($taro, '2026-11-02');
        $this->createAttendance($taro, '2026-11-03');
        $this->createAttendance($taro, '2026-10-30');
        $this->createAttendance($hanako, '2026-11-02');

        $this->getJson('/api/v1/attendance-records?user_id='.$taro->id)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.date', '2026-11-03')
            ->assertJsonPath('data.0.user_name', '山田 太郎')
            ->assertJsonPath('data.0.total_time', '08:00')
            ->assertJsonPath('data.0.total_break_time', '01:00');
        $this->getJson('/api/v1/attendance-records?date=2026-11-02')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/attendance-records?month=2026-11&user_id='.$taro->id)->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/attendance-records?per_page=1&page=2')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 4);
    }

    public function test_index_validation_error(): void
    {
        $this->getJson('/api/v1/attendance-records?per_page=101&month=2026/11')
            ->assertStatus(422)
            ->assertJsonPath('errors.per_page.0', '1ページあたりの件数は100以下で指定してください。')
            ->assertJsonPath('errors.month.0', '年月は YYYY-MM 形式で指定してください。');
    }

    public function test_show_returns_detail_with_user_breaks_and_applications(): void
    {
        $user = User::factory()->create(['name' => '山田 太郎']);
        $record = $this->createAttendance($user, '2026-11-02', '09:00:00', '18:00:00', [['12:00:00', '13:00:00']], ['comment' => '通常勤務']);
        Application::factory()->create(['attendance_record_id' => $record->id, 'user_id' => $user->id]);

        $this->getJson('/api/v1/attendance-records/'.$record->id)
            ->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.user.name', '山田 太郎')
            ->assertJsonPath('data.date', '2026-11-02')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.breaks.0.break_in', '12:00:00')
            ->assertJsonPath('data.breaks.0.break_out', '13:00:00')
            ->assertJsonPath('data.applications.0.approval_status', '承認待ち')
            ->assertJsonPath('data.comment', '通常勤務');
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->getJson('/api/v1/attendance-records/99999')
            ->assertNotFound()
            ->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }
}
