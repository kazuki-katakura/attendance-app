<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * 管理者ログインは管理者の勤怠一覧へ、一般ログインは勤怠登録画面へ移動する。
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        if ($request->is('admin/*')) {
            return redirect()->intended('/admin/attendance/list');
        }

        return redirect()->intended('/attendance');
    }
}
