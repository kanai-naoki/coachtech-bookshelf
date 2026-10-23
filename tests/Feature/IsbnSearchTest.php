<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 書籍登録時のISBN検索（Google Books API連携）の機能テスト
 */
class IsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_ISBN = '9784798121963';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Http::preventStrayRequests();
    }

    /**
     * ISBN検索を実行する。
     */
    private function search(string $isbn): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->getJson('/books/isbn/' . rawurlencode($isbn));
    }

    /**
     * 1件ヒットしたGoogle Books APIレスポンスのfake。
     *
     * @param array<string, mixed> $volumeInfo
     */
    private function fakeFound(array $volumeInfo): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(['totalItems' => 1, 'items' => [['volumeInfo' => $volumeInfo]]]),
        ]);
    }

    // ==================== バリデーション ====================

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIsbnProvider(): array
    {
        return [
            '12桁' => ['978479812196'],
            '14桁' => ['97847981219630'],
            '1桁' => ['9'],
            'ハイフン付き' => ['978-4-7981-2196'],
        ];
    }

    /**
     * @dataProvider invalidIsbnProvider
     */
    public function test_バリデーション_13桁の数字以外は400でAPIを呼ばない(string $isbn): void
    {
        Http::fake();

        $this->search($isbn)
            ->assertStatus(400)
            ->assertJsonValidationErrors('isbn')
            ->assertJsonStructure(['message', 'errors' => ['isbn']]);

        Http::assertNothingSent();
    }

    public function test_バリデーション_未入力は400(): void
    {
        Http::fake();

        $this->actingAs($this->user)->getJson('/books/isbn')
            ->assertStatus(400)
            ->assertJsonPath('message', 'ISBNは必須です。');

        Http::assertNothingSent();
    }

    public function test_バリデーション_ちょうど13桁の数字は受け付けられる(): void
    {
        $this->fakeFound(['title' => 'テスト']);

        $this->search(self::VALID_ISBN)->assertOk();
    }

    public function test_バリデーション_先頭が0の13桁も受け付けられる(): void
    {
        $this->fakeFound(['title' => 'テスト']);

        $this->search('0123456789012')->assertOk();
    }

    public function test_バリデーション_桁数不正のメッセージ(): void
    {
        $this->search('abc')
            ->assertStatus(400)
            ->assertJsonPath('message', 'ISBNは13桁で入力してください。');
    }

    public function test_未ログインはログイン画面へリダイレクトされる(): void
    {
        Http::fake();

        $this->get('/books/isbn/' . self::VALID_ISBN)->assertRedirect('/login');

        Http::assertNothingSent();
    }

    // ==================== 正常系 (200) ====================

    public function test_正常系_書籍情報がJSONで返る(): void
    {
        $this->fakeFound([
            'title' => 'ドメイン駆動設計入門',
            'authors' => ['成瀬 允宣'],
            'publishedDate' => '2020-03-12',
            'description' => 'DDDの入門書',
            'imageLinks' => ['thumbnail' => 'https://example.com/cover.jpg'],
        ]);

        $this->search(self::VALID_ISBN)
            ->assertOk()
            ->assertExactJson([
                'title' => 'ドメイン駆動設計入門',
                'author' => '成瀬 允宣',
                'published_date' => '2020-03-12',
                'description' => 'DDDの入門書',
                'image_url' => 'https://example.com/cover.jpg',
            ]);
    }

    public function test_正常系_複数著者はカンマ区切りで連結される(): void
    {
        $this->fakeFound(['title' => '共著', 'authors' => ['著者A', '著者B', '著者C']]);

        $this->search(self::VALID_ISBN)
            ->assertOk()
            ->assertJsonPath('author', '著者A, 著者B, 著者C');
    }

    public function test_正常系_任意項目がなければ空文字またはnullで返る(): void
    {
        $this->fakeFound([]);

        $this->search(self::VALID_ISBN)
            ->assertOk()
            ->assertExactJson([
                'title' => '',
                'author' => '',
                'published_date' => null,
                'description' => null,
                'image_url' => null,
            ]);
    }

    public function test_正常系_複数ヒット時は先頭の書籍を返す(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(['items' => [
                ['volumeInfo' => ['title' => '1件目']],
                ['volumeInfo' => ['title' => '2件目']],
            ]]),
        ]);

        $this->search(self::VALID_ISBN)->assertOk()->assertJsonPath('title', '1件目');
    }

    public function test_正常系_APIへisbn条件とAPIキーを付けて問い合わせる(): void
    {
        config(['services.google_books.key' => 'test-key']);
        $this->fakeFound(['title' => 'テスト']);

        $this->search(self::VALID_ISBN)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), config('services.google_books.endpoint'))
            && $request['q'] === 'isbn:' . self::VALID_ISBN
            && $request['key'] === 'test-key');
    }

    // ==================== 準正常系 (404) ====================

    public function test_準正常系_itemsが空配列なら404(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['totalItems' => 0, 'items' => []])]);

        $this->search(self::VALID_ISBN)
            ->assertNotFound()
            ->assertExactJson(['message' => '該当する書籍が見つかりませんでした。']);
    }

    public function test_準正常系_itemsキー自体がなくても404(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['totalItems' => 0])]);

        $this->search(self::VALID_ISBN)->assertNotFound();
    }

    public function test_準正常系_空のレスポンスボディでも404(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response('', 200)]);

        $this->search(self::VALID_ISBN)->assertNotFound();
    }

    // ==================== 異常系 (500) ====================

    /**
     * @return array<string, array{int}>
     */
    public static function apiErrorStatusProvider(): array
    {
        return [
            '500 Internal Server Error' => [500],
            '503 Service Unavailable' => [503],
            '429 Too Many Requests' => [429],
            '403 Forbidden (キー無効)' => [403],
        ];
    }

    /**
     * @dataProvider apiErrorStatusProvider
     */
    public function test_異常系_APIがエラーを返したら500でクラッシュしない(int $status): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => 'x'], $status)]);

        $this->search(self::VALID_ISBN)
            ->assertStatus(500)
            ->assertExactJson(['message' => '外部APIとの通信に失敗しました。']);
    }

    public function test_異常系_タイムアウトでも500でクラッシュしない(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->search(self::VALID_ISBN)
            ->assertStatus(500)
            ->assertExactJson(['message' => '外部APIとの通信に失敗しました。']);
    }
}
