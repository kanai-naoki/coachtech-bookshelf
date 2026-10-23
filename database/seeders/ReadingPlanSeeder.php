<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Enums\ReadingPlanStatus;

class ReadingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        // 動作テスト対象ユーザーの取得（山田太郎: ID 1, 鈴木花子: ID 2 等を想定）
        $userYamada = $users->first();
        $userSuzuki = $users->skip(1)->first() ?? $users->first();

        // 各シナリオ用に異なる書籍を割り当て
        $scenarios = [
            // 山田太郎 (主要シナリオ集約 ID 1〜5)
            [
                'user_id' => $userYamada->id,
                'book_id' => $books[0]->id ?? 1,
                'target_date' => Carbon::today()->addDays(3),
                'status' => ReadingPlanStatus::InProgress,
                'completed_at' => null,
            ], // 1. 3日前リマインダー対象
            [
                'user_id' => $userYamada->id,
                'book_id' => $books[1]->id ?? 2,
                'target_date' => Carbon::today(),
                'status' => ReadingPlanStatus::InProgress,
                'completed_at' => null,
            ], // 2. 当日リマインダー対象
            [
                'user_id' => $userYamada->id,
                'book_id' => $books[2]->id ?? 3,
                'target_date' => Carbon::today()->subDays(3),
                'status' => ReadingPlanStatus::InProgress,
                'completed_at' => null,
            ], // 3. パッチでAuto-expire化 + 3日後再エンゲージメント対象
            [
                'user_id' => $userYamada->id,
                'book_id' => $books[3]->id ?? 4,
                'target_date' => Carbon::today()->addDays(7),
                'status' => ReadingPlanStatus::InProgress,
                'completed_at' => null,
            ], // 4. リマインダー対象外
            [
                'user_id' => $userYamada->id,
                'book_id' => $books[4]->id ?? 5,
                'target_date' => Carbon::today()->subDays(10),
                'status' => ReadingPlanStatus::Completed,
                'completed_at' => Carbon::today()->subDays(5),
            ], // 5. 完了済み

            // 鈴木花子 (他ユーザー認可テスト用 ID 6)
            [
                'user_id' => $userSuzuki->id,
                'book_id' => $books[5]->id ?? 6,
                'target_date' => Carbon::today()->addDays(5),
                'status' => ReadingPlanStatus::InProgress,
                'completed_at' => null,
            ], // 6. 他ユーザー権限チェック(403確認用)
        ];

        foreach ($scenarios as $scenario) {
            ReadingPlan::create($scenario);
        }
    }
}
