<?php

namespace App\Policies;

use App\Models\ReadingPlan;
use App\Models\User;

/**
 * 読書計画の認可ポリシー（作成者本人のみ操作可）。
 */
class ReadingPlanPolicy
{
    /**
     * 読書計画の更新（編集・更新・読了処理）が可能かどうかを判定する
     *
     * @param  User  $user  操作ユーザー
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }

    /**
     * 読書計画の削除が可能かどうかを判定する
     *
     * @param  User  $user  操作ユーザー
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }
}
