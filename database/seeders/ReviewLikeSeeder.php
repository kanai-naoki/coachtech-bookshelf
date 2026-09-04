<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $reviews = Review::all();

        foreach ($reviews as $review) {
            // ランダムで「いいね」する人数（0〜3人）を決める
            $likeCount = rand(0, 3);

            if ($likeCount === 0) {
                continue;
            }

            // レビュー投稿者本人を除外したユーザー一覧を取得
            $eligibleUsers = $users->where('id', '!=', $review->user_id);

            // 対象ユーザー数が要求件数より少ない場合の調整（念のため）
            $count = min($likeCount, $eligibleUsers->count());

            if ($count > 0) {
                // 条件を満外ユーザーの中からランダムで指定人数を選ぶ
                $likedUsers = $eligibleUsers->random($count);

                // Userモデル側（またはReviewモデル側）のリレーションメソッドで syncWithoutDetaching を呼び出す
                foreach ($likedUsers as $user) {
                    $user->reviewLikes()->syncWithoutDetaching([$review->id]);
                }
            }
        }
    }
}
