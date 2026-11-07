<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * ユーザー・勤怠・修正申請のダミーデータを作成する。
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AttendanceSeeder::class,
            ApplicationSeeder::class,
        ]);
    }
}
