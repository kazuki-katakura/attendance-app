<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 3：ログイン認証機能（管理者）
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => 'password']);
    }

    public function test_admin_login_page_is_displayed(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertViewIs('admin.admin-login');
    }

    public function test_email_is_required(): void
    {
        $this->post('/admin/login', ['email' => '', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_password_is_required(): void
    {
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => ''])
            ->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_wrong_credentials_show_error(): void
    {
        $this->post('/admin/login', ['email' => 'wrong@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);

        $this->assertGuest();
    }

    public function test_general_user_cannot_login_as_admin(): void
    {
        User::factory()->create(['email' => 'user@example.com', 'password' => 'password']);

        $this->post('/admin/login', ['email' => 'user@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);

        $this->assertGuest();
    }

    public function test_admin_can_login_and_logout(): void
    {
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect('/admin/attendance/list');
        $this->assertAuthenticated();

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_admin_login_and_general_user_is_forbidden(): void
    {
        $this->get('/admin/attendance/list')->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create())
            ->get('/admin/attendance/list')
            ->assertForbidden();
    }
}
