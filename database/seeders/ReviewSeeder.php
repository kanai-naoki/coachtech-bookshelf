<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ja_JP');
        $users = User::all();
        $books = Book::all();

        $totalReviews = 0;
        $maxReviews = 32;

        foreach ($books as $book) {
            if ($totalReviews >= $maxReviews) {
                break;
            }

            // 同一ユーザーが同じ本に重複投稿しないようシャッフル
            $shuffledUsers = $users->shuffle();

            // 各書籍に2〜4件のレビューを配分（32件上限の調整付き）
            $countForThisBook = min(rand(2, 4), $maxReviews - $totalReviews);

            for ($i = 0; $i < $countForThisBook; $i++) {
                $user = $shuffledUsers[$i];

                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => $faker->numberBetween(3, 5),
                    'comment' => $faker->realText(100),
                ]);

                $totalReviews++;
            }
        }
    }
}
