<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * テストケースID 1：認証機能（一般ユーザー）
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト 太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_register_page_is_displayed_and_links_to_login(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertViewIs('user.register')
            ->assertSee('href="/login"', false);
    }

    public function test_name_is_required(): void
    {
        $this->post('/register', $this->validInput(['name' => '']))
            ->assertSessionHasErrors(['name' => 'お名前を入力してください']);
    }

    public function test_email_is_required(): void
    {
        $this->post('/register', $this->validInput(['email' => '']))
            ->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_email_must_be_email_format(): void
    {
        $this->post('/register', $this->validInput(['email' => 'invalid-email']))
            ->assertSessionHasErrors(['email' => 'メールアドレスはメール形式で入力してください']);
    }

    public function test_password_must_be_at_least_8_characters(): void
    {
        $this->post('/register', $this->validInput(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->post('/register', $this->validInput(['password_confirmation' => 'different123']))
            ->assertSessionHasErrors(['password' => 'パスワードと一致しません']);
    }

    public function test_password_is_required(): void
    {
        $this->post('/register', $this->validInput(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_user_is_saved_when_input_is_valid(): void
    {
        $this->post('/register', $this->validInput())
            ->assertRedirect('/email/verify');

        $user = User::where('email', 'taro@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('テスト 太郎', $user->name);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertAuthenticatedAs($user);
    }
}
