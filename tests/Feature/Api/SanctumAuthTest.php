<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * テストケースID 19：Sanctum 認証
 */
class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->record = $this->createAttendance($this->owner, '2026-11-02');
    }

    public function test_write_apis_return_401_without_authentication(): void
    {
        $this->postJson('/api/v1/attendance-records', ['date' => '2026-11-03', 'clock_in' => '09:00:00'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->putJson('/api/v1/attendance-records/'.$this->record->id, ['comment' => 'x'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->deleteJson('/api/v1/attendance-records/'.$this->record->id)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->post('/api/v1/attendance-records')->assertUnauthorized();
        $this->assertDatabaseHas('attendance_records', ['id' => $this->record->id]);
    }

    public function test_authenticated_user_can_update_and_delete_own_record(): void
    {
        Sanctum::actingAs($this->owner, ['*']);

        $this->putJson('/api/v1/attendance-records/'.$this->record->id, ['comment' => '本人が更新'])->assertOk();
        $this->deleteJson('/api/v1/attendance-records/'.$this->record->id)->assertNoContent();
    }

    public function test_other_user_gets_403(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->putJson('/api/v1/attendance-records/'.$this->record->id, ['comment' => '他人が更新'])
            ->assertForbidden()
            ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);
        $this->deleteJson('/api/v1/attendance-records/'.$this->record->id)
            ->assertForbidden()
            ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);

        $this->assertDatabaseHas('attendance_records', ['id' => $this->record->id, 'comment' => null]);
    }

    public function test_admin_can_update_and_delete_any_record(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);

        $this->putJson('/api/v1/attendance-records/'.$this->record->id, ['comment' => '管理者が更新'])->assertOk();
        $this->deleteJson('/api/v1/attendance-records/'.$this->record->id)->assertNoContent();
    }
}
