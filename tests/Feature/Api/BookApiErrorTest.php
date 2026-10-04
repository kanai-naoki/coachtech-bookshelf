<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API エラーハンドリング・連動削除
 */
class BookApiErrorTest extends TestCase
{
    use RefreshDatabase;

    // ---------- 正常系（データ整合性） ----------

    public function test_削除で中間テーブルとレビューとお気に入りも連動削除される(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre);
        $review = Review::factory()->create(['book_id' => $book->id]);
        $fan = User::factory()->create();
        $book->favoriteUsers()->attach($fan);
        $review->likedByUsers()->attach($fan);

        $this->deleteJson("/api/v1/books/{$book->id}")->assertNoContent();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
        // ジャンルとユーザー本体は残る
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
        $this->assertDatabaseHas('users', ['id' => $fan->id]);
    }

    public function test_更新で自分の既存_isb_nを送っても重複エラーにならない(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();

        $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '変更', 'author' => $book->author, 'isbn' => $book->isbn,
            'published_date' => '2024-01-01', 'genre_ids' => [$genre->id],
        ])->assertOk();
    }

    // ---------- 異常系 ----------

    public function test_必須項目が空だと422でエラーメッセージが返る(): void
    {
        $this->postJson('/api/v1/books', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'title', 'author', 'isbn', 'published_date', 'genre_ids'])
            ->assertJsonPath('errors.title.0', 'タイトルは必須です。')
            ->assertJsonPath('errors.author.0', '著者名は必須です。')
            ->assertJsonPath('errors.isbn.0', 'ISBNは必須です。')
            ->assertJsonPath('errors.published_date.0', '出版日は必須です。')
            ->assertJsonPath('errors.genre_ids.0', 'ジャンルは1つ以上選択してください。');
    }

    public function test_既存_isb_nでの登録は重複エラー(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $existing = Book::factory()->create();

        $this->postJson('/api/v1/books', [
            'user_id' => $user->id, 'title' => 't', 'author' => 'a', 'isbn' => $existing->isbn,
            'published_date' => '2024-01-01', 'genre_ids' => [$genre->id],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.isbn.0', 'そのISBNは既に使用されています。');
    }

    public function test_更新で必須項目が空だと422(): void
    {
        $book = Book::factory()->create();

        $this->putJson("/api/v1/books/{$book->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'author', 'isbn', 'published_date', 'genre_ids']);
    }

    public function test_存在しないジャンル_i_dを指定すると422(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/books', [
            'user_id' => $user->id, 'title' => 't', 'author' => 'a', 'isbn' => '1111111111111',
            'published_date' => '2024-01-01', 'genre_ids' => [99999],
        ])->assertUnprocessable()->assertJsonValidationErrors('genre_ids.0');
    }

    public function test_存在しない_i_dの更新は404(): void
    {
        $this->putJson('/api/v1/books/99999', [])->assertNotFound();
    }

    public function test_存在しない_i_dの削除は404(): void
    {
        $this->deleteJson('/api/v1/books/99999')->assertNotFound();
    }

    // ---------- 境界値 ----------

    public function test_genre_idsが空配列だと422(): void
    {
        $book = Book::factory()->create();

        $this->putJson("/api/v1/books/{$book->id}", [
            'title' => 't', 'author' => 'a', 'isbn' => $book->isbn,
            'published_date' => '2024-01-01', 'genre_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('genre_ids');
    }

    public function test_不正な日付と_ur_lは422(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();

        $this->putJson("/api/v1/books/{$book->id}", [
            'title' => 't', 'author' => 'a', 'isbn' => $book->isbn,
            'published_date' => 'not-a-date', 'image_url' => 'not-url', 'genre_ids' => [$genre->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['published_date', 'image_url']);
    }
}
