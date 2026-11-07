<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Notifications\Messages\MailMessage;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * 認証メールの文面を日本語にする。
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject('【coachtech勤怠管理】メールアドレスの認証')
            ->greeting($notifiable->name.' 様')
            ->line('ご登録ありがとうございます。下のボタンを押して、メールアドレスの認証を完了してください。')
            ->action('メールアドレスを認証する', $url)
            ->line('このメールに心当たりがない場合は、破棄してください。')
            ->salutation('coachtech勤怠管理'));
    }
}
