<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * お気に入り（一覧表示・トグル）の機能テスト
 *
 * テスト名の接頭辞: 一覧 / トグル、区分は 正常系・異常系・境界値
 */
class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ---------- 一覧表示 ----------

    public function test_一覧_正常系_自分のお気に入り書籍のみ表示される(): void
    {
        $mine = Book::factory()->create(['title' => 'MyFavoriteBook']);
        $others = Book::factory()->create(['title' => 'OthersFavoriteBook']);
        $notFav = Book::factory()->create(['title' => 'NotFavoriteBook']);
        $this->user->favoriteBooks()->attach($mine->id);
        User::factory()->create()->favoriteBooks()->attach($others->id);

        $this->actingAs($this->user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('MyFavoriteBook')
            ->assertDontSee('OthersFavoriteBook')
            ->assertDontSee('NotFavoriteBook');
    }

    public function test_一覧_異常系_未ログインはログイン画面へリダイレクトされる(): void
    {
        $this->get(route('favorites.index'))->assertRedirect('/login');
    }

    public function test_一覧_境界値_0件のときは空メッセージが表示される(): void
    {
        $this->actingAs($this->user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('お気に入りに登録された書籍はありません。');
    }

    public function test_一覧_境界値_10件ちょうどは1ページに収まる(): void
    {
        $this->user->favoriteBooks()->attach(Book::factory()->count(10)->create()->pluck('id'));

        $response = $this->actingAs($this->user)->get(route('favorites.index'));

        $response->assertOk();
        $this->assertCount(10, $response->viewData('books'));
        $this->assertFalse($response->viewData('books')->hasMorePages());
    }

    public function test_一覧_境界値_11件は2ページ目に1件表示される(): void
    {
        $this->user->favoriteBooks()->attach(Book::factory()->count(11)->create()->pluck('id'));

        $page1 = $this->actingAs($this->user)->get(route('favorites.index'));
        $this->assertCount(10, $page1->viewData('books'));
        $this->assertTrue($page1->viewData('books')->hasMorePages());

        $page2 = $this->actingAs($this->user)->get(route('favorites.index', ['page' => 2]));
        $page2->assertOk();
        $this->assertCount(1, $page2->viewData('books'));
    }

    // ---------- トグル ----------

    public function test_トグル_正常系_未登録の書籍をお気に入りに追加できる(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)
            ->from('/books')
            ->post(route('favorites.toggle', $book))
            ->assertRedirect('/books');

        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_トグル_正常系_登録済みの書籍はお気に入りから解除される(): void
    {
        $book = Book::factory()->create();
        $this->user->favoriteBooks()->attach($book->id);

        $this->actingAs($this->user)->post(route('favorites.toggle', $book));

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_トグル_正常系_他ユーザーのお気に入りには影響しない(): void
    {
        $book = Book::factory()->create();
        $other = User::factory()->create();
        $other->favoriteBooks()->attach($book->id);

        $this->actingAs($this->user)->post(route('favorites.toggle', $book));

        $this->assertDatabaseCount('favorites', 2);
        $this->actingAs($this->user)->post(route('favorites.toggle', $book));
        $this->assertDatabaseHas('favorites', ['user_id' => $other->id, 'book_id' => $book->id]);
        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_トグル_異常系_未ログインはログイン画面へリダイレクトされ保存されない(): void
    {
        $book = Book::factory()->create();

        $this->post(route('favorites.toggle', $book))->assertRedirect('/login');

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_トグル_異常系_存在しない書籍は404(): void
    {
        $this->actingAs($this->user)
            ->post(route('favorites.toggle', 999999))
            ->assertNotFound();

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_トグル_境界値_連続トグルでも重複エラーにならない(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($this->user);

        foreach ([1, 0, 1] as $expected) {
            $this->post(route('favorites.toggle', $book))->assertRedirect();
            $this->assertSame($expected, $this->user->favoriteBooks()->count());
        }
    }
}
