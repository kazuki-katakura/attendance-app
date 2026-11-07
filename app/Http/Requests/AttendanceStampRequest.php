<?php

namespace App\Http\Requests;

use App\Services\AttendanceStampService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceStampRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 打刻ボタン（出勤・休憩入・休憩戻・退勤）のバリデーションルール。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(AttendanceStampService::ACTIONS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => '打刻の種類を選択してください',
            'action.in' => '打刻の種類が不正です',
        ];
    }
}
