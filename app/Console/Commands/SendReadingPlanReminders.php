<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReadingPlan; // 読書計画モデル
use App\Notifications\ReadingPlanReminder; // 通知クラス

class SendReadingPlanReminders extends Command
{
    protected $signature = 'reminders:send-reading-plans';
    protected $description = '期限が近づいた、または超過した読書計画のリマインダー通知を送信する';

    public function handle()
    {
        $today = now()->toDateString();
        $threeDaysLater = now()->addDays(3)->toDateString();
        $threeDaysAgo = now()->subDays(3)->toDateString();

        // 1. 【3日前】の計画を取得して通知
        $plansThreeDaysBefore = ReadingPlan::whereDate('target_date', $threeDaysLater)->get();
        foreach ($plansThreeDaysBefore as $plan) {
            if ($plan->user) {
                $plan->user->notify(new ReadingPlanReminder($plan, 'three_days_before'));
            }
        }

        // 2. 【当日】の計画を取得して通知
        $plansToday = ReadingPlan::whereDate('target_date', $today)->get();
        foreach ($plansToday as $plan) {
            if ($plan->user) {
                $plan->user->notify(new ReadingPlanReminder($plan, 'due_date'));
            }
        }

        // 3. 【3日後（超過）】の計画を取得して通知（必要ならステータス変更もここで）
        $plansThreeDaysAgo = ReadingPlan::whereDate('target_date', $threeDaysAgo)->get();
        foreach ($plansThreeDaysAgo as $plan) {
            if ($plan->user) {
                $plan->user->notify(new ReadingPlanReminder($plan, 'three_days_after'));
            }
        }

        $this->info('読書計画のリマインダー通知および日次パッチ処理が完了しました。');
    }
}
