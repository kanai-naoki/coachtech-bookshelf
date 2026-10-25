<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * コマンドスケジュールを定義する。
     */
    protected function schedule(Schedule $schedule): void
    {
        // 毎日 0:05 に期限切れの進行中計画を自動失効させる
        $schedule->command('reading-plans:expire')->dailyAt('00:05');

        // 毎日 20:00 にリマインダー通知を送信する
        $schedule->command('reminders:send-reading-plans')->dailyAt('20:00');
    }

    /**
     * コンソールコマンドを登録する。
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
