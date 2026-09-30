<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API 一覧・詳細取得
 */
class BookApiReadTest extends TestCase
{
    use RefreshDatabase;

    // ---------- 正常系 ----------

    public function test_一覧取得で200と20件とページネーション構造が返る(): void
    {
        Book::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/books');

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'title', 'author', 'isbn', 'genres', 'average_rating', 'review_count']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_一覧取得で集計値が正しい(): void
    {
        $book = Book::factory()->create();
        Review::factory()->create(['book_id' => $book->id, 'rating' => 5]);
        Review::factory()->create(['book_id' => $book->id, 'rating' => 4]);

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonPath('data.0.review_count', 2)
            ->assertJsonPath('data.0.average_rating', 4.5);
    }

    public function test_レビューがない書籍の集計値は0(): void
    {
        Book::factory()->create();

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonPath('data.0.review_count', 0)
            ->assertJsonPath('data.0.average_rating', 0);
    }

    public function test_キーワード検索でタイトルと著者が対象になる(): void
    {
        Book::factory()->create(['title' => 'Laravel入門', 'author' => 'A']);
        Book::factory()->create(['title' => 'Other', 'author' => 'Laravel太郎']);
        Book::factory()->create(['title' => 'Unrelated', 'author' => 'B']);

        $this->getJson('/api/v1/books?keyword=Laravel')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_ジャンル絞り込みができる(): void
    {
        $genre = Genre::factory()->create();
        $in = Book::factory()->create();
        $in->genres()->attach($genre);
        Book::factory()->create();

        $this->getJson('/api/v1/books?genre_id='.$genre->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $in->id);
    }

    public function test_per_pageで件数を指定できる(): void
    {
        Book::factory()->count(5)->create();

        $this->getJson('/api/v1/books?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 3);
    }

    public function test_詳細取得で書籍情報とジャンルとレビュー集計が返る(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre);
        Review::factory()->count(3)->create(['book_id' => $book->id, 'rating' => 3]);

        $this->getJson("/api/v1/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', $book->title)
            ->assertJsonPath('data.isbn', $book->isbn)
            ->assertJsonPath('data.genres.0.id', $genre->id)
            ->assertJsonPath('data.review_count', 3)
            ->assertJsonPath('data.average_rating', 3);
    }

    // ---------- 異常系 ----------

    public function test_一覧のper_pageが文字列だと422(): void
    {
        $this->getJson('/api/v1/books?per_page=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_存在しないジャンルIDで絞り込むと422(): void
    {
        $this->getJson('/api/v1/books?genre_id=99999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genre_id');
    }

    public function test_詳細取得で存在しないIDは404(): void
    {
        $this->getJson('/api/v1/books/99999')->assertNotFound();
    }

    // ---------- 境界値 ----------

    public function test_per_page最大値100は許可され101は422(): void
    {
        $this->getJson('/api/v1/books?per_page=100')->assertOk();
        $this->getJson('/api/v1/books?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_per_page最小値1は許可され0は422(): void
    {
        $this->getJson('/api/v1/books?per_page=1')->assertOk();
        $this->getJson('/api/v1/books?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_データ0件でも200で空配列(): void
    {
        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }
}
