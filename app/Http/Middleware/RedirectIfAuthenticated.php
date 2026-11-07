<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * ログイン済みのユーザーがログイン・会員登録画面を開いたら、それぞれのトップ画面へ移動する。
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            $user = Auth::guard($guard)->user();
            if ($user !== null) {
                return redirect($user->isAdmin() ? '/admin/attendance/list' : RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
