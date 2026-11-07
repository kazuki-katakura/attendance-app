<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 2：ログイン認証機能（一般ユーザー）
 */
class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['email' => 'user@example.com', 'password' => 'password']);
    }

    public function test_login_page_is_displayed_and_links_to_register(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertViewIs('user.user-login')
            ->assertSee('href="/register"', false);
    }

    public function test_email_is_required(): void
    {
        $this->post('/login', ['email' => '', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_password_is_required(): void
    {
        $this->post('/login', ['email' => 'user@example.com', 'password' => ''])
            ->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_wrong_credentials_show_error(): void
    {
        $this->post('/login', ['email' => 'wrong@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);

        $this->assertGuest();
    }

    public function test_user_can_login_and_is_redirected_to_attendance(): void
    {
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'password'])
            ->assertRedirect('/attendance');

        $this->assertAuthenticated();
    }

    public function test_user_can_logout_from_header(): void
    {
        $this->actingAs(User::first())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
