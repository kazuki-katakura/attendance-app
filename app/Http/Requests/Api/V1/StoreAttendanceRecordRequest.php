<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\AttendanceRecordApiMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRecordRequest extends FormRequest
{
    use AttendanceRecordApiMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 勤怠登録APIのバリデーションルール。同じユーザーの同じ日付は登録できない。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendance_records', 'date')->where('user_id', $this->user()->id),
            ],
            'clock_in' => ['required', 'date_format:H:i:s'],
            'clock_out' => ['nullable', 'date_format:H:i:s', 'after:clock_in'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
