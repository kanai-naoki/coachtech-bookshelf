<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    // ---- 正常系 ----

    public function test_genres_はhas_manyで_genreを返す(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(HasMany::class, $user->genres());
        $this->assertTrue($user->genres->first()->is($genre));
    }

    public function test_books_はhas_manyで_bookを返す(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(HasMany::class, $user->books());
        $this->assertTrue($user->books->first()->is($book));
    }

    public function test_reviews_はhas_manyで_reviewを返す(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(HasMany::class, $user->reviews());
        $this->assertTrue($user->reviews->first()->is($review));
    }

    public function test_favorite_books_はfavorites経由のbelongs_to_manyで_bookを返す(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book->id);

        $relation = $user->favoriteBooks();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('favorites', $relation->getTable());
        $this->assertTrue($user->favoriteBooks->first()->is($book));
    }

    public function test_liked_reviews_はreview_likes経由のbelongs_to_manyで_reviewを返す(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $user->likedReviews()->attach($review->id);

        $relation = $user->likedReviews();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('review_likes', $relation->getTable());
        $this->assertTrue($user->likedReviews->first()->is($review));
    }

    // ---- 異常系 ----

    public function test_他ユーザーのデータは含まれない(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Book::factory()->create(['user_id' => $other->id]);
        Genre::factory()->create(['user_id' => $other->id]);
        Review::factory()->create(['user_id' => $other->id]);

        $this->assertCount(0, $user->genres);
        $this->assertCount(0, $user->reviews);
        $this->assertCount(1, $other->books);
        $this->assertCount(0, $user->books->where('user_id', $other->id));
    }

    public function test_同じ書籍を重複してお気に入り登録するとユニーク制約違反(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book->id);

        $this->expectException(QueryException::class);
        $user->favoriteBooks()->attach($book->id);
    }

    public function test_同じレビューに重複していいねするとユニーク制約違反(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $user->likedReviews()->attach($review->id);

        $this->expectException(QueryException::class);
        $user->likedReviews()->attach($review->id);
    }

    // ---- 境界値 ----

    public function test_関連データが0件なら空コレクション(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->genres->isEmpty());
        $this->assertTrue($user->books->isEmpty());
        $this->assertTrue($user->reviews->isEmpty());
        $this->assertTrue($user->favoriteBooks->isEmpty());
        $this->assertTrue($user->likedReviews->isEmpty());
    }

    public function test_ユーザー削除で関連データがcascade削除される(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        Review::factory()->create(['user_id' => $user->id]);
        $user->favoriteBooks()->attach($book->id);

        $user->delete();

        $this->assertDatabaseMissing('books', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('reviews', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id]);
    }

    public function test_detachで1件だけ解除できる(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(2)->create();
        $user->favoriteBooks()->attach($books->pluck('id'));

        $user->favoriteBooks()->detach($books->first()->id);

        $this->assertCount(1, $user->fresh()->favoriteBooks);
    }
}
