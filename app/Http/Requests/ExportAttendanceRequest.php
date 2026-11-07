<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ExportAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * CSV出力の対象ユーザーと年月のバリデーションルール。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year_month' => ['required', 'date_format:Y-m'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => '出力するユーザーを指定してください',
            'user_id.integer' => '出力するユーザーが不正です',
            'user_id.exists' => '出力するユーザーが存在しません',
            'year_month.required' => '出力する年月を指定してください',
            'year_month.date_format' => '出力する年月は YYYY-MM 形式で指定してください',
        ];
    }

    /**
     * 出力する月の初日を返す。
     */
    public function month(): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $this->validated('year_month'))->startOfMonth();
    }
}
