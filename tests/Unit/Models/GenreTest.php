<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    // ---- 正常系 ----

    public function test_user_はbelongsToでUserを返す(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(BelongsTo::class, $genre->user());
        $this->assertTrue($genre->user->is($user));
    }

    public function test_books_はbook_genre経由のbelongsToManyでBookを返す(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $genre->books()->attach($book->id);

        $relation = $genre->books();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('book_genre', $relation->getTable());
        $this->assertTrue($genre->books->first()->is($book));
    }

    // ---- 異常系 ----

    public function test_同じ書籍を重複して紐付けるとユニーク制約違反(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $genre->books()->attach($book->id);

        $this->expectException(QueryException::class);
        $genre->books()->attach($book->id);
    }

    public function test_存在しないユーザーIDのジャンルは外部キー制約違反(): void
    {
        $this->expectException(QueryException::class);
        Genre::factory()->create(['user_id' => 999999]);
    }

    // ---- 境界値 ----

    public function test_書籍が0件なら空コレクション(): void
    {
        $this->assertTrue(Genre::factory()->create()->books->isEmpty());
    }

    public function test_ジャンル削除で中間テーブルのみcascade削除され書籍は残る(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $genre->books()->attach($book->id);

        $genre->delete();

        $this->assertDatabaseMissing('book_genre', ['genre_id' => $genre->id]);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }
}
