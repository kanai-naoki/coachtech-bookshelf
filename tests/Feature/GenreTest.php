<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ジャンルCRUDの機能テスト
 */
class GenreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ==================== 一覧・詳細表示 ====================

    public function test_index_正常系_一覧が表示され書籍数がカウントされる(): void
    {
        $genre = Genre::factory()->create(['name' => 'ミステリー']);
        Genre::factory()->create(['name' => 'SF']);
        $genre->books()->attach(Book::factory()->count(3)->create());

        $this->actingAs($this->user)
            ->get(route('genres.index'))
            ->assertOk()
            ->assertSee('ミステリー')
            ->assertSee('SF')
            ->assertSee('3冊')
            ->assertSee('0冊');
    }

    public function test_index_正常系_ジャンルが無い場合は空メッセージが表示される(): void
    {
        $this->actingAs($this->user)
            ->get(route('genres.index'))
            ->assertOk()
            ->assertSee('ジャンルが登録されていません。');
    }

    public function test_show_正常系_詳細に紐づく書籍が表示される(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['title' => '紐づく書籍']);
        $genre->books()->attach($book);
        Book::factory()->create(['title' => '無関係の書籍']);

        $this->actingAs($this->user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertSee('紐づく書籍')
            ->assertDontSee('無関係の書籍');
    }

    public function test_show_正常系_書籍が無い場合は空メッセージが表示される(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs($this->user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertSee('このジャンルの書籍はまだ登録されていません。');
    }

    public function test_show_正常系_ページネーションは10件ずつ(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->count(11)->create());

        $this->actingAs($this->user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books->count() === 10 && $books->total() === 11);

        $this->actingAs($this->user)
            ->get(route('genres.show', ['genre' => $genre, 'page' => 2]))
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books->count() === 1);
    }

    public function test_index_異常系_未ログインはログイン画面へリダイレクト(): void
    {
        $this->get(route('genres.index'))->assertRedirect(route('login'));
    }

    public function test_show_異常系_未ログインはログイン画面へリダイレクト(): void
    {
        $genre = Genre::factory()->create();

        $this->get(route('genres.show', $genre))->assertRedirect(route('login'));
    }

    public function test_show_異常系_存在しないジャンルは404(): void
    {
        $this->actingAs($this->user)->get(route('genres.show', 9999))->assertNotFound();
    }

    // ==================== 登録 (Store) ====================

    public function test_store_正常系_登録画面が表示される(): void
    {
        $this->actingAs($this->user)->get(route('genres.create'))->assertOk();
    }

    public function test_store_正常系_ジャンルを登録できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => '歴史'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを作成しました。');

        $this->assertDatabaseHas('genres', ['name' => '歴史', 'user_id' => $this->user->id]);
    }

    public function test_store_異常系_未ログインはログイン画面へリダイレクト(): void
    {
        $this->post(route('genres.store'), ['name' => '歴史'])->assertRedirect(route('login'));

        $this->assertDatabaseCount('genres', 0);
    }

    public function test_store_異常系_登録画面は未ログインだとリダイレクト(): void
    {
        $this->get(route('genres.create'))->assertRedirect(route('login'));
    }

    public function test_store_異常系_空送信は必須エラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'ジャンル名は必須です。']);

        $this->assertDatabaseCount('genres', 0);
    }

    public function test_store_異常系_重複するジャンル名はエラー(): void
    {
        Genre::factory()->create(['name' => '歴史']);

        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => '歴史'])
            ->assertSessionHasErrors(['name' => 'そのジャンル名は既に使用されています。']);

        $this->assertDatabaseCount('genres', 1);
    }

    public function test_store_異常系_配列など文字列以外はエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => ['a']])
            ->assertSessionHasErrors('name');
    }

    public function test_store_境界値_255文字は登録できる(): void
    {
        $name = str_repeat('あ', 255);

        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => $name])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', ['name' => $name]);
    }

    public function test_store_境界値_256文字はエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => str_repeat('あ', 256)])
            ->assertSessionHasErrors(['name' => 'ジャンル名は255文字以内で入力してください。']);

        $this->assertDatabaseCount('genres', 0);
    }

    // ==================== 更新 (Update) ====================

    public function test_update_正常系_編集画面が表示される(): void
    {
        $genre = Genre::factory()->create(['name' => '編集前']);

        $this->actingAs($this->user)
            ->get(route('genres.edit', $genre))
            ->assertOk()
            ->assertSee('編集前');
    }

    public function test_update_正常系_ジャンル名を更新できる(): void
    {
        $genre = Genre::factory()->create(['name' => '編集前']);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => '編集後'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを更新しました。');

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '編集後']);
    }

    public function test_update_正常系_自身と同じ名前のまま更新できる(): void
    {
        $genre = Genre::factory()->create(['name' => '同名']);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => '同名'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('genres.index'));
    }

    public function test_update_異常系_未ログインはログイン画面へリダイレクト(): void
    {
        $genre = Genre::factory()->create(['name' => '編集前']);

        $this->put(route('genres.update', $genre), ['name' => '編集後'])->assertRedirect(route('login'));

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '編集前']);
    }

    public function test_update_異常系_編集画面は未ログインだとリダイレクト(): void
    {
        $genre = Genre::factory()->create();

        $this->get(route('genres.edit', $genre))->assertRedirect(route('login'));
    }

    public function test_update_異常系_空送信は必須エラー(): void
    {
        $genre = Genre::factory()->create(['name' => '編集前']);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'ジャンル名は必須です。']);

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '編集前']);
    }

    public function test_update_異常系_他ジャンルと重複する名前はエラー(): void
    {
        Genre::factory()->create(['name' => '既存']);
        $genre = Genre::factory()->create(['name' => '編集前']);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => '既存'])
            ->assertSessionHasErrors(['name' => 'そのジャンル名は既に使用されています。']);

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '編集前']);
    }

    public function test_update_境界値_255文字は更新できる(): void
    {
        $genre = Genre::factory()->create();
        $name = str_repeat('a', 255);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => $name])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => $name]);
    }

    public function test_update_境界値_256文字はエラー(): void
    {
        $genre = Genre::factory()->create(['name' => '編集前']);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors(['name' => 'ジャンル名は255文字以内で入力してください。']);

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '編集前']);
    }

    // ==================== 削除 (Destroy) ====================

    public function test_destroy_正常系_書籍が無いジャンルを削除できる(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    public function test_destroy_異常系_未ログインはログイン画面へリダイレクト(): void
    {
        $genre = Genre::factory()->create();

        $this->delete(route('genres.destroy', $genre))->assertRedirect(route('login'));

        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    public function test_destroy_異常系_書籍が紐づくジャンルは削除できない(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->create());

        $this->actingAs($this->user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error', '書籍が登録されているジャンルは削除できません。');

        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    public function test_destroy_異常系_削除拒否のエラーメッセージが一覧に表示される(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->create());

        $this->actingAs($this->user)
            ->followingRedirects()
            ->delete(route('genres.destroy', $genre))
            ->assertSee('書籍が登録されているジャンルは削除できません。');
    }
}
