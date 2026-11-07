<?php

namespace App\Http\Requests\Concerns;

/**
 * 勤怠登録・更新APIで共通の日本語エラーメッセージ。
 */
trait AttendanceRecordApiMessages
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.after' => '退勤時刻は出勤時刻より後の時刻を指定してください。',
            'comment.string' => '備考は文字列で入力してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
