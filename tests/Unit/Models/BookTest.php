<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    // ---- 正常系 ----

    public function test_user_はbelongsToでUserを返す(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(BelongsTo::class, $book->user());
        $this->assertTrue($book->user->is($user));
    }

    public function test_reviews_はhasManyでReviewを返す(): void
    {
        $book = Book::factory()->create();
        $reviews = Review::factory()->count(2)->create(['book_id' => $book->id]);

        $this->assertInstanceOf(HasMany::class, $book->reviews());
        $this->assertEqualsCanonicalizing($reviews->pluck('id')->all(), $book->reviews->pluck('id')->all());
    }

    public function test_genres_はbook_genre経由のbelongsToManyでGenreを返す(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre->id);

        $relation = $book->genres();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('book_genre', $relation->getTable());
        $this->assertTrue($book->genres->first()->is($genre));
    }

    public function test_favoriteUsers_はfavorites経由のbelongsToManyでUserを返す(): void
    {
        $book = Book::factory()->create();
        $user = User::factory()->create();
        $book->favoriteUsers()->attach($user->id);

        $relation = $book->favoriteUsers();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('favorites', $relation->getTable());
        $this->assertTrue($book->favoriteUsers->first()->is($user));
    }

    // ---- 異常系 ----

    public function test_同じジャンルを重複して紐付けるとユニーク制約違反(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre->id);

        $this->expectException(QueryException::class);
        $book->genres()->attach($genre->id);
    }

    public function test_存在しないジャンルIDの紐付けは外部キー制約違反(): void
    {
        $book = Book::factory()->create();

        $this->expectException(QueryException::class);
        $book->genres()->attach(999999);
    }

    public function test_他の書籍のレビューは含まれない(): void
    {
        $book = Book::factory()->create();
        Review::factory()->create();

        $this->assertCount(0, $book->reviews);
    }

    // ---- 境界値 ----

    public function test_関連データが0件なら空コレクション(): void
    {
        $book = Book::factory()->create();

        $this->assertTrue($book->reviews->isEmpty());
        $this->assertTrue($book->genres->isEmpty());
        $this->assertTrue($book->favoriteUsers->isEmpty());
    }

    public function test_syncで複数ジャンルを置き換えられる(): void
    {
        $book = Book::factory()->create();
        [$a, $b, $c] = Genre::factory()->count(3)->create()->all();
        $book->genres()->sync([$a->id, $b->id]);
        $book->genres()->sync([$c->id]);

        $this->assertSame([$c->id], $book->fresh()->genres->pluck('id')->all());
    }

    public function test_書籍削除でレビューと中間テーブルがcascade削除される(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre->id);
        $book->favoriteUsers()->attach(User::factory()->create()->id);
        Review::factory()->create(['book_id' => $book->id]);

        $book->delete();

        $this->assertDatabaseMissing('reviews', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }
}
