<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * テストケースID 16：メール認証機能
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_is_sent_after_register(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'テスト 太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Notification::assertSentTo(User::where('email', 'taro@example.com')->first(), VerifyEmail::class);
    }

    public function test_unverified_user_is_guided_to_verification_page_with_link_to_mail_site(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/attendance')->assertRedirect('/email/verify');

        $this->actingAs($user)
            ->get('/email/verify')
            ->assertOk()
            ->assertViewIs('auth.verify-email')
            ->assertSee('認証はこちらから')
            ->assertSee('http://localhost:8025', false);
    }

    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from('/email/verify')
            ->post('/email/verification-notification')
            ->assertRedirect('/email/verify')
            ->assertSessionHas('message', '認証メールを再送しました。');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_completing_verification_redirects_to_attendance_page(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect('/attendance?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())->get('/attendance')->assertOk()->assertViewIs('user.attendance-register');
    }
}
