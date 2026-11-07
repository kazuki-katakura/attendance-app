<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * トップ（ヘッダーのロゴ）から、ログイン状態に応じた画面へ移動する。
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect('/login');
        }

        return redirect($user->isAdmin() ? '/admin/attendance/list' : '/attendance');
    }
}
