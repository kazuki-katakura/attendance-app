<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse;

class VerificationLinkSentResponse implements EmailVerificationNotificationSentResponse
{
    /**
     * 認証メールを再送したことをメール認証誘導画面に表示する。
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return back()->with('message', '認証メールを再送しました。');
    }
}
