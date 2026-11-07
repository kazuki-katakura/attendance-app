<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * 未ログイン時の移動先。管理者用の画面は管理者ログイン画面、API は 401 の JSON を返す。
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        return $request->is('admin/*', 'stamp_correction_request/approve/*') ? '/admin/login' : route('login');
    }
}
