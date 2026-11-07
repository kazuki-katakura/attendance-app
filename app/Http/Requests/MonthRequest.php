<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class MonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 月次勤怠一覧の表示月（?date=YYYY-MM）のバリデーションルール。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.date_format' => '表示月は YYYY-MM 形式で指定してください',
        ];
    }

    /**
     * 表示する月の初日を返す。指定がなければ今月。
     */
    public function month(): Carbon
    {
        $date = $this->validated('date');

        return $date ? Carbon::createFromFormat('!Y-m', $date)->startOfMonth() : Carbon::today()->startOfMonth();
    }
}
