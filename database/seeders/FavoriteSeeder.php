<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        foreach ($users as $user) {
            // 各ユーザーごとに3〜5冊の本をランダムに抽出
            $randomBookIds = $books->random(rand(3, 5))->pluck('id');

            // 既存の紐付けを解除せずに中間テーブルへ登録 (重複防止)
            $user->favoriteBooks()->syncWithoutDetaching($randomBookIds);
        }
    }
}
