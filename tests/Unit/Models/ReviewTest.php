<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    // ---- 正常系 ----

    public function test_user_はbelongs_toで_userを返す(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(BelongsTo::class, $review->user());
        $this->assertTrue($review->user->is($user));
    }

    public function test_book_はbelongs_toで_bookを返す(): void
    {
        $book = Book::factory()->create();
        $review = Review::factory()->create(['book_id' => $book->id]);

        $this->assertInstanceOf(BelongsTo::class, $review->book());
        $this->assertTrue($review->book->is($book));
    }

    public function test_liked_by_users_はreview_likes経由のbelongs_to_manyで_userを返す(): void
    {
        $review = Review::factory()->create();
        $user = User::factory()->create();
        $review->likedByUsers()->attach($user->id);

        $relation = $review->likedByUsers();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('review_likes', $relation->getTable());
        $this->assertTrue($review->likedByUsers->first()->is($user));
    }

    // ---- 異常系 ----

    public function test_同一ユーザーが同一書籍に2件レビューするとユニーク制約違反(): void
    {
        $review = Review::factory()->create();

        $this->expectException(QueryException::class);
        Review::factory()->create([
            'user_id' => $review->user_id,
            'book_id' => $review->book_id,
        ]);
    }

    public function test_同じユーザーが重複していいねするとユニーク制約違反(): void
    {
        $review = Review::factory()->create();
        $user = User::factory()->create();
        $review->likedByUsers()->attach($user->id);

        $this->expectException(QueryException::class);
        $review->likedByUsers()->attach($user->id);
    }

    public function test_存在しない書籍_i_dのレビューは外部キー制約違反(): void
    {
        $this->expectException(QueryException::class);
        Review::factory()->create(['book_id' => 999999]);
    }

    // ---- 境界値 ----

    public function test_いいねが0件なら空コレクション(): void
    {
        $this->assertTrue(Review::factory()->create()->likedByUsers->isEmpty());
    }

    public function test_異なるユーザーなら同一書籍に複数レビューできる(): void
    {
        $book = Book::factory()->create();
        Review::factory()->count(2)->create(['book_id' => $book->id]);

        $this->assertCount(2, $book->reviews);
    }

    public function test_レビュー削除でいいねがcascade削除される(): void
    {
        $review = Review::factory()->create();
        $review->likedByUsers()->attach(User::factory()->create()->id);

        $review->delete();

        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }
}
