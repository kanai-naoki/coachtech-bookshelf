<?php

namespace App\Policies;

use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReadingPlanPolicy
{
    /**
     * 読書計画の更新（編集・更新・読了処理）が可能かどうかを判定する
     *
     * @param User $user
     * @param ReadingPlan $readingPlan
     * @return bool
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }

    /**
     * 読書計画の削除が可能かどうかを判定する
     *
     * @param User $user
     * @param ReadingPlan $readingPlan
     * @return bool
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }
}