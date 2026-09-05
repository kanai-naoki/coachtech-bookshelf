<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $firstUser = User::first();
        $userId = $firstUser ? $firstUser->id : 1;

        $genres = ['小説', 'ビジネス', '技術書', '自己啓発', 'エッセイ', '歴史', '科学', '芸術', '料理', '旅行'];

        foreach ($genres as $name) {
            Genre::firstOrCreate(['user_id' => $userId], ['name' => $name]);
        }
    }
}
