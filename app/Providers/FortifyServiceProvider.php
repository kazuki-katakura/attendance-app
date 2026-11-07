<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\LoginRequest;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Http\Responses\VerificationLinkSentResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Fortify が使うリクエスト・レスポンスを差し替える。
     */
    public function register(): void
    {
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->singleton(EmailVerificationNotificationSentResponse::class, VerificationLinkSentResponse::class);
    }

    /**
     * 画面・ユーザー作成・認証方法・レート制限を設定する。
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::registerView(fn (): View => view('user.register'));
        Fortify::loginView(fn (): View => view('user.user-login'));
        Fortify::verifyEmailView(fn (): View => view('auth.verify-email'));

        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', $request->input('email'))->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if ($request->is('admin/*') && ! $user->isAdmin()) {
                return null;
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
