<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * 管理者はログアウト後に管理者ログイン画面へ、一般ユーザーはログイン画面へ戻す。
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return redirect($request->is('admin/*') ? '/admin/login' : '/login');
    }
}
