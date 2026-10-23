<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReadingPlans extends Command
{
    /**
     * コマンド名。
     *
     * @var string
     */
    protected $signature = 'reading-plans:expire';

    /**
     * コマンドの説明。
     *
     * @var string
     */
    protected $description = '期限を過ぎても進行中のままの読書計画を「期限切れ」に自動更新する';

    /**
     * 期限超過の進行中計画を一括で期限切れに更新する（1トランザクション）。
     *
     * @return int 終了コード
     */
    public function handle(): int
    {
        $count = DB::transaction(
            fn (): int => ReadingPlan::overdue()->update(['status' => ReadingPlanStatus::Expired->value])
        );

        $this->info("{$count} 件の読書計画を期限切れにしました。");

        return self::SUCCESS;
    }
}
