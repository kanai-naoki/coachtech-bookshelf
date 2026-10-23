<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SendReadingPlanReminders extends Command
{
    /**
     * コマンド名。
     *
     * @var string
     */
    protected $signature = 'reminders:send-reading-plans';

    /**
     * コマンドの説明。
     *
     * @var string
     */
    protected $description = '期限が近づいた、または超過した読書計画のリマインダー通知を送信する';

    /**
     * 期限の3日前・当日・3日後の読書計画の所有者へ通知を送る。
     * 通知の保存は1トランザクションで行い、途中で失敗した場合は1件も残さない。
     *
     * @return int 終了コード
     */
    public function handle(): int
    {
        $sent = DB::transaction(
            fn (): int => $this->reminderRules()->sum(fn (array $rule): int => $this->notify($rule))
        );

        $this->info("読書計画のリマインダー通知を {$sent} 件送信しました。");

        return self::SUCCESS;
    }

    /**
     * 通知タイミングごとの対象日・対象ステータス・タイミング識別子を返す。
     *
     * @return Collection<int, array{date: string, timing: string, statuses: array<int, string>}>
     */
    private function reminderRules(): Collection
    {
        $inProgress = ReadingPlanStatus::InProgress->value;
        $expired = ReadingPlanStatus::Expired->value;

        return collect([
            ['date' => now()->addDays(3)->toDateString(), 'timing' => 'three_days_before', 'statuses' => [$inProgress]],
            ['date' => now()->toDateString(), 'timing' => 'on_due_date', 'statuses' => [$inProgress]],
            ['date' => now()->subDays(3)->toDateString(), 'timing' => 'three_days_after', 'statuses' => [$inProgress, $expired]],
        ]);
    }

    /**
     * 1つの通知ルールに該当する読書計画の所有者へ通知する。
     *
     * @param array{date: string, timing: string, statuses: array<int, string>} $rule 通知ルール
     * @return int 通知した件数
     */
    private function notify(array $rule): int
    {
        return ReadingPlan::with(['user', 'book'])
            ->dueOn($rule['date'])
            ->whereIn('status', $rule['statuses'])
            ->get()
            ->filter(fn (ReadingPlan $plan): bool => $plan->user !== null)
            ->each(fn (ReadingPlan $plan) => $plan->user->notify(new ReadingPlanReminder($plan, $rule['timing'])))
            ->count();
    }
}
