<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class RegisterController extends Controller
{
    /**
     * 会員登録を行い、認証メールを送ってメール認証誘導画面へ移動する。
     */
    public function store(RegisterRequest $request, CreatesNewUsers $creator): RedirectResponse
    {
        $user = $creator->create($request->validated());

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
