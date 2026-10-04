<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 書籍CRUDの機能テスト
 *
 * テスト名の接頭辞: 表示 / 登録 / 更新 / 削除、区分は 正常系・異常系・境界値
 * ※ 本アプリの画像は「アップロード」ではなく image_url（URL文字列）で扱うため、
 *   画像関連は URL形式・255文字上限のテストとして実装している。
 */
class BookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * 有効な書籍入力データ
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000001',
            'published_date' => '2024-01-01',
            'description' => '説明文',
            'image_url' => 'https://example.com/cover.jpg',
            'genres' => [Genre::factory()->create()->id],
        ], $overrides);
    }

    /**
     * 指定長のURLを生成
     */
    private function urlOfLength(int $length): string
    {
        $prefix = 'https://example.com/';

        return $prefix.str_repeat('a', $length - strlen($prefix));
    }

    // ==================== 一覧・詳細表示 ====================

    public function test_表示_正常系_ゲストでも一覧が表示される(): void
    {
        Book::factory()->create(['title' => '吾輩は猫である']);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('吾輩は猫である');
    }

    public function test_表示_正常系_ページネーションで10件ずつ表示される(): void
    {
        foreach (range(1, 11) as $i) {
            Book::factory()->create([
                'title' => sprintf('ページ書籍%02d', $i),
                'created_at' => now()->subMinutes(20 - $i),
            ]);
        }

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('ページ書籍11')
            ->assertDontSee('ページ書籍01');

        $this->get(route('books.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('ページ書籍01')
            ->assertDontSee('ページ書籍11');
    }

    public function test_表示_正常系_詳細にジャンルとレビューが表示される(): void
    {
        $book = Book::factory()->create(['title' => '詳細表示の本']);
        $book->genres()->attach(Genre::factory()->create(['name' => '歴史']));

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('詳細表示の本')
            ->assertSee('歴史');
    }

    public function test_表示_異常系_存在しない書籍の詳細は404(): void
    {
        $this->get(route('books.show', 99999))->assertNotFound();
    }

    public function test_表示_境界値_ちょうど10件では2ページ目に書籍が表示されない(): void
    {
        Book::factory()->count(10)->create();

        $this->get(route('books.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('書籍が登録されていません', false);
    }

    // ==================== 登録 (Store) ====================

    public function test_登録_正常系_登録画面が表示される(): void
    {
        $this->actingAs($this->user)
            ->get(route('books.create'))
            ->assertOk();
    }

    public function test_登録_正常系_複数ジャンルを紐付けて登録できる(): void
    {
        $genres = Genre::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->post(route('books.store'), $this->validData([
            'genres' => $genres->pluck('id')->all(),
        ]));

        $book = Book::where('isbn', '9784000000001')->firstOrFail();
        $response->assertRedirect(route('books.show', $book));
        $this->assertSame($this->user->id, $book->user_id);
        $this->assertEqualsCanonicalizing($genres->pluck('id')->all(), $book->genres()->pluck('genres.id')->all());
        $this->assertDatabaseCount('book_genre', 3);
    }

    public function test_登録_正常系_画像_ur_lと説明が任意項目として省略できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['description' => null, 'image_url' => null]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', ['isbn' => '9784000000001', 'image_url' => null, 'description' => null]);
    }

    public function test_登録_正常系_画像_ur_lが保存される(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', ['isbn' => '9784000000001', 'image_url' => 'https://example.com/cover.jpg']);
    }

    public function test_登録_異常系_未ログインはloginへリダイレクト(): void
    {
        $this->get(route('books.create'))->assertRedirect('/login');
        $this->post(route('books.store'), $this->validData())->assertRedirect('/login');
        $this->assertDatabaseCount('books', 0);
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function 必須項目の欠落を返す(): array
    {
        return [
            'タイトル' => ['title', ''],
            '著者' => ['author', ''],
            'ISBN' => ['isbn', ''],
            '出版日' => ['published_date', ''],
            'ジャンル未選択' => ['genres', []],
        ];
    }

    /**
     * @dataProvider 必須項目の欠落を返す
     */
    public function test_登録_異常系_必須項目が未入力だとエラー(string $field, mixed $value): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData([$field => $value]))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_登録_異常系_isb_nが重複するとエラー(): void
    {
        Book::factory()->create(['isbn' => '9784000000001']);

        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData())
            ->assertSessionHasErrors(['isbn' => 'そのISBNは既に使用されています。']);
    }

    public function test_登録_異常系_存在しないジャンル_i_dはエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['genres' => [99999]]))
            ->assertSessionHasErrors('genres.0');
    }

    public function test_登録_異常系_画像_ur_lの形式が不正だとエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['image_url' => 'not-a-url']))
            ->assertSessionHasErrors('image_url');
    }

    public function test_登録_異常系_出版日が日付形式でないとエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['published_date' => 'abc']))
            ->assertSessionHasErrors('published_date');
    }

    public function test_登録_境界値_タイトル255文字は登録できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['title' => str_repeat('あ', 255)]))
            ->assertSessionHasNoErrors();
    }

    public function test_登録_境界値_タイトル256文字はエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['title' => str_repeat('あ', 256)]))
            ->assertSessionHasErrors(['title' => 'タイトルは255文字以内で入力してください。']);
    }

    public function test_登録_境界値_著者255文字は登録できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['author' => str_repeat('あ', 255)]))
            ->assertSessionHasNoErrors();
    }

    public function test_登録_境界値_著者256文字はエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['author' => str_repeat('あ', 256)]))
            ->assertSessionHasErrors('author');
    }

    public function test_登録_境界値_isb_n13桁は登録できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['isbn' => '1234567890123']))
            ->assertSessionHasNoErrors();
    }

    public function test_登録_境界値_isb_n12桁はエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['isbn' => '123456789012']))
            ->assertSessionHasErrors(['isbn' => 'ISBNは13桁で入力してください。']);
    }

    public function test_登録_境界値_isb_n14桁はエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['isbn' => '12345678901234']))
            ->assertSessionHasErrors('isbn');
    }

    public function test_登録_境界値_画像_ur_l255文字は登録できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['image_url' => $this->urlOfLength(255)]))
            ->assertSessionHasNoErrors();
    }

    public function test_登録_境界値_画像_ur_l256文字はエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['image_url' => $this->urlOfLength(256)]))
            ->assertSessionHasErrors(['image_url' => '画像URLは255文字以内で入力してください。']);
    }

    // ==================== 更新 (Update) ====================

    public function test_更新_正常系_編集画面が表示される(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id, 'title' => '編集前の本']);

        $this->actingAs($this->user)
            ->get(route('books.edit', $book))
            ->assertOk()
            ->assertSee('編集前の本');
    }

    public function test_更新_正常系_内容とジャンルが更新される(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $book->genres()->attach(Genre::factory()->count(2)->create());
        $newGenres = Genre::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validData([
                'title' => '更新後タイトル',
                'isbn' => $book->isbn,
                'genres' => $newGenres->pluck('id')->all(),
            ]))
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新後タイトル']);
        $this->assertEqualsCanonicalizing(
            $newGenres->pluck('id')->all(),
            $book->genres()->pluck('genres.id')->all()
        );
    }

    public function test_更新_正常系_自身の_isb_nのままなら重複エラーにならない(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id, 'isbn' => '9784111111111']);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validData(['isbn' => '9784111111111']))
            ->assertSessionHasNoErrors();
    }

    public function test_更新_異常系_未ログインはloginへリダイレクト(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.edit', $book))->assertRedirect('/login');
        $this->put(route('books.update', $book), $this->validData())->assertRedirect('/login');
    }

    public function test_更新_異常系_他人の書籍の編集画面は403(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)->get(route('books.edit', $book))->assertForbidden();
    }

    public function test_更新_異常系_他人の書籍の更新は403で変更されない(): void
    {
        $book = Book::factory()->create(['title' => '元のタイトル']);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validData(['isbn' => $book->isbn]))
            ->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元のタイトル']);
    }

    public function test_更新_異常系_他の書籍の_isb_nと重複するとエラー(): void
    {
        Book::factory()->create(['isbn' => '9784222222222']);
        $book = Book::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validData(['isbn' => '9784222222222']))
            ->assertSessionHasErrors('isbn');
    }

    public function test_更新_異常系_必須項目が未入力だとエラー(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), ['title' => '', 'author' => '', 'isbn' => '', 'genres' => []])
            ->assertSessionHasErrors(['title', 'author', 'isbn', 'genres']);
    }

    public function test_更新_異常系_画像_ur_lの形式が不正だとエラー(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validData(['isbn' => $book->isbn, 'image_url' => 'invalid']))
            ->assertSessionHasErrors('image_url');
    }

    public function test_更新_境界値_タイトル255文字は更新できる(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validData(['isbn' => $book->isbn, 'title' => str_repeat('あ', 255)]))
            ->assertSessionHasNoErrors();
    }

    public function test_更新_境界値_タイトル256文字はエラー(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validData(['isbn' => $book->isbn, 'title' => str_repeat('あ', 256)]))
            ->assertSessionHasErrors('title');
    }

    public function test_更新_境界値_画像_ur_l256文字はエラー(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validData(['isbn' => $book->isbn, 'image_url' => $this->urlOfLength(256)]))
            ->assertSessionHasErrors('image_url');
    }

    // ==================== 削除 (Destroy) ====================

    public function test_削除_正常系_自分の書籍を削除でき中間テーブルも消える(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $book->genres()->attach(Genre::factory()->count(2)->create());

        $this->actingAs($this->user)
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseCount('book_genre', 0);
        $this->assertDatabaseCount('genres', 2);
    }

    public function test_削除_異常系_未ログインはloginへリダイレクト(): void
    {
        $book = Book::factory()->create();

        $this->delete(route('books.destroy', $book))->assertRedirect('/login');
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_削除_異常系_他人の書籍の削除は403(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)->delete(route('books.destroy', $book))->assertForbidden();
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_削除_異常系_存在しない書籍は404(): void
    {
        $this->actingAs($this->user)->delete(route('books.destroy', 99999))->assertNotFound();
    }

    public function test_削除_境界値_他書籍には影響しない(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $other = Book::factory()->create();

        $this->actingAs($this->user)->delete(route('books.destroy', $book));

        $this->assertDatabaseHas('books', ['id' => $other->id]);
        $this->assertDatabaseCount('books', 1);
    }
}
