<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class DayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 日次勤怠一覧の表示日（?date=YYYY-MM-DD）のバリデーションルール。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.date_format' => '表示日は YYYY-MM-DD 形式で指定してください',
        ];
    }

    /**
     * 表示する日を返す。指定がなければ今日。
     */
    public function day(): Carbon
    {
        $date = $this->validated('date');

        return $date ? Carbon::createFromFormat('!Y-m-d', $date) : Carbon::today();
    }
}
