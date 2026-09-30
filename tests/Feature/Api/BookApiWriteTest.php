<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API 登録・更新・削除
 */
class BookApiWriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, Genre $genre, array $override = []): array
    {
        return array_merge([
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567890',
            'published_date' => '2024-01-01',
            'description' => '説明',
            'image_url' => 'https://example.com/a.png',
            'genre_ids' => [$genre->id],
        ], $override);
    }

    // ---------- 正常系 ----------

    public function test_新規登録で201とDB保存とジャンル紐付けができる(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/books', $this->payload($user, $genre))
            ->assertCreated()
            ->assertJsonPath('data.title', 'テスト書籍')
            ->assertJsonPath('data.genres.0.id', $genre->id)
            ->assertJsonPath('data.review_count', 0);

        $this->assertDatabaseHas('books', ['isbn' => '9781234567890', 'user_id' => $user->id]);
        $this->assertDatabaseHas('book_genre', ['genre_id' => $genre->id]);
    }

    public function test_更新で200とDB値とジャンルが更新される(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $old = Genre::factory()->create();
        $new = Genre::factory()->create();
        $book->genres()->attach($old);

        $this->actingAs($user)
            ->putJson("/api/v1/books/{$book->id}", $this->payload($user, $new, [
                'title' => '更新後タイトル',
                'isbn' => $book->isbn,
            ]))
            ->assertOk()
            ->assertJsonPath('data.title', '更新後タイトル');

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新後タイトル']);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $new->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $old->id]);
    }

    public function test_削除で204が返りボディが空でDBから消える(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/books/{$book->id}");

        $response->assertNoContent();
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    // ---------- 異常系 ----------

    public function test_登録時に存在しないuser_idは422(): void
    {
        $genre = Genre::factory()->create();

        $this->postJson('/api/v1/books', [
            'user_id' => 99999, 'title' => 't', 'author' => 'a', 'isbn' => '1111111111111',
            'published_date' => '2024-01-01', 'genre_ids' => [$genre->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_更新で他書籍と重複するISBNは422(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $other = Book::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $this->putJson("/api/v1/books/{$book->id}", $this->payload($user, $genre, ['isbn' => $other->isbn]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');
    }

    // ---------- 境界値 ----------

    public function test_ISBN12桁は422(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->postJson('/api/v1/books', $this->payload($user, $genre, ['isbn' => '123456789012']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');
    }

    public function test_ISBN13桁は登録でき14桁は422(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->postJson('/api/v1/books', $this->payload($user, $genre, ['isbn' => '1234567890123']))
            ->assertCreated();
        $this->postJson('/api/v1/books', $this->payload($user, $genre, ['isbn' => '12345678901234']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');
    }

    public function test_タイトル255文字は登録でき256文字は422(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->postJson('/api/v1/books', $this->payload($user, $genre, ['title' => str_repeat('a', 255)]))
            ->assertCreated();
        $this->postJson('/api/v1/books', $this->payload($user, $genre, [
            'title' => str_repeat('a', 256), 'isbn' => '9990000000000',
        ]))->assertUnprocessable()->assertJsonValidationErrors('title');
    }

    public function test_任意項目がnullでも登録できる(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->postJson('/api/v1/books', $this->payload($user, $genre, ['description' => null, 'image_url' => null]))
            ->assertCreated()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.image_url', null);
    }
}
