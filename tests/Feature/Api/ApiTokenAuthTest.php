<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sanctum APIトークン認証・認可と、トークン付きでのAPI CRUD
 */
class ApiTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'password123';

    /**
     * パスワード既知のユーザーを作成する。
     */
    private function user(): User
    {
        return User::factory()->create(['password' => self::PASSWORD]);
    }

    /**
     * ログインAPIでトークンを取得する。
     */
    private function loginToken(User $user): string
    {
        return $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk()->json('access_token');
    }

    /**
     * 同一テスト内で認証ガードのキャッシュを破棄し、次のリクエストでトークンを再評価させる。
     */
    private function nextRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * Bearerトークン付きでJSONリクエストを送る。
     *
     * @param  array<string, mixed>  $data
     */
    private function api(string $method, string $uri, ?string $token, array $data = []): TestResponse
    {
        $this->nextRequest();

        return ($token ? $this->withToken($token) : $this)->json($method, $uri, $data);
    }

    /**
     * 書籍の入力データ。
     *
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => 'トークン経由の書籍',
            'author' => '著者',
            'isbn' => '9781234567890',
            'published_date' => '2024-01-01',
            'description' => '説明',
            'image_url' => 'https://example.com/a.png',
            'genre_ids' => [Genre::factory()->create()->id],
        ], $override);
    }

    // ==================== 1. トークン生成（ログイン） ====================

    public function test_正しい認証情報でアクセストークンが取得できる(): void
    {
        $user = $this->user();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk()
            ->assertJsonStructure(['access_token', 'token_type'])
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'auth_token',
        ]);
        $this->assertStringNotContainsString(self::PASSWORD, $response->getContent());
    }

    public function test_取得したトークンは平文で_d_bに保存されない(): void
    {
        $token = $this->loginToken($this->user());

        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $token]);
    }

    public function test_ログインを繰り返すと別々のトークンが発行される(): void
    {
        $user = $this->user();

        $first = $this->loginToken($user);
        $second = $this->loginToken($user);

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_パスワード誤りは422でトークンが発行されない(): void
    {
        $user = $this->user();

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', '認証情報が正しくありません。');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_未登録メールは422でトークンが発行されない(): void
    {
        $this->postJson('/api/v1/login', ['email' => 'nobody@example.com', 'password' => self::PASSWORD])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidLoginProvider(): array
    {
        return [
            'メール未入力' => [['password' => 'x'], 'email'],
            'メール空文字' => [['email' => '', 'password' => 'x'], 'email'],
            'メール形式不正' => [['email' => 'not-an-email', 'password' => 'x'], 'email'],
            'パスワード未入力' => [['email' => 'a@example.com'], 'password'],
            'パスワード空文字' => [['email' => 'a@example.com', 'password' => ''], 'password'],
            '全項目未入力' => [[], 'email'],
        ];
    }

    /**
     * @dataProvider invalidLoginProvider
     *
     * @param  array<string, mixed>  $body
     */
    public function test_不正な入力はバリデーションエラー(array $body, string $errorKey): void
    {
        $this->postJson('/api/v1/login', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_ログイン_ap_iはメール未入力のメッセージを返す(): void
    {
        $this->postJson('/api/v1/login', ['password' => 'x'])
            ->assertJsonPath('errors.email.0', 'メールアドレスは必須です。');
    }

    // ==================== 2. 401 / 403 ====================

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedWriteProvider(): array
    {
        return [
            '登録' => ['POST', '/api/v1/books'],
            '更新' => ['PUT', '/api/v1/books/1'],
            '削除' => ['DELETE', '/api/v1/books/1'],
            'ログアウト' => ['POST', '/api/v1/logout'],
        ];
    }

    /**
     * @dataProvider protectedWriteProvider
     */
    public function test_トークンなしの書き込み系は401(string $method, string $uri): void
    {
        $this->json($method, $uri, [])->assertUnauthorized();
    }

    /**
     * @dataProvider protectedWriteProvider
     */
    public function test_無効なトークンの書き込み系は401(string $method, string $uri): void
    {
        $this->api($method, $uri, 'invalid-token')->assertUnauthorized();
        $this->api($method, $uri, '999|nonexistenttoken')->assertUnauthorized();
    }

    public function test_無効なトークンでは書籍が登録されない(): void
    {
        $this->api('POST', '/api/v1/books', 'invalid-token', $this->payload())->assertUnauthorized();

        $this->assertDatabaseCount('books', 0);
    }

    public function test_無効なトークンでは他人の書籍を更新削除できない(): void
    {
        $book = Book::factory()->create(['title' => '元のタイトル']);

        $this->api('PUT', "/api/v1/books/{$book->id}", 'invalid', $this->payload())->assertUnauthorized();
        $this->api('DELETE', "/api/v1/books/{$book->id}", 'invalid')->assertUnauthorized();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元のタイトル']);
    }

    public function test_他人の書籍の更新は403で内容が変わらない(): void
    {
        $token = $this->loginToken($this->user());
        $book = Book::factory()->create(['title' => '他人の書籍']);

        $this->api('PUT', "/api/v1/books/{$book->id}", $token, $this->payload(['title' => '乗っ取り']))
            ->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '他人の書籍']);
    }

    public function test_他人の書籍の削除は403で残る(): void
    {
        $token = $this->loginToken($this->user());
        $book = Book::factory()->create();

        $this->api('DELETE', "/api/v1/books/{$book->id}", $token)->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_公開_ap_iの閲覧はトークンなしでも200(): void
    {
        $book = Book::factory()->create();

        $this->api('GET', '/api/v1/books', null)->assertOk();
        $this->api('GET', "/api/v1/books/{$book->id}", null)->assertOk();
    }

    // ==================== 3. 正常系CRUD ====================

    public function test_トークンで書籍を登録でき所有者はトークンのユーザーになる(): void
    {
        $user = $this->user();
        $other = User::factory()->create();
        $token = $this->loginToken($user);
        $data = $this->payload(['user_id' => $other->id]);

        $this->api('POST', '/api/v1/books', $token, $data)
            ->assertCreated()
            ->assertJsonPath('data.title', 'トークン経由の書籍')
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('books', ['isbn' => '9781234567890', 'user_id' => $user->id]);
        $this->assertDatabaseHas('book_genre', ['genre_id' => $data['genre_ids'][0]]);
    }

    public function test_トークンで自分の書籍を更新できる(): void
    {
        $user = $this->user();
        $token = $this->loginToken($user);
        $book = Book::factory()->create(['user_id' => $user->id]);
        $genre = Genre::factory()->create();

        $this->api('PUT', "/api/v1/books/{$book->id}", $token, $this->payload([
            'title' => '更新後', 'genre_ids' => [$genre->id],
        ]))->assertOk()->assertJsonPath('data.title', '更新後');

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新後']);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $genre->id]);
    }

    public function test_トークンで自分の書籍を削除できる(): void
    {
        $user = $this->user();
        $token = $this->loginToken($user);
        $book = Book::factory()->create(['user_id' => $user->id]);
        $book->genres()->attach(Genre::factory()->create());

        $response = $this->api('DELETE', "/api/v1/books/{$book->id}", $token);

        $response->assertNoContent();
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
    }

    public function test_認証済みでもバリデーションエラーは422(): void
    {
        $token = $this->loginToken($this->user());

        $this->api('POST', '/api/v1/books', $token, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'author', 'isbn', 'genre_ids']);
    }

    // ==================== 4. ログアウトとトークン失効 ====================

    public function test_ログアウトで_d_bから該当トークンが削除される(): void
    {
        $user = $this->user();
        $token = $this->loginToken($user);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->api('POST', '/api/v1/logout', $token)
            ->assertOk()
            ->assertJsonPath('message', 'ログアウトしました。');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_ログアウト後に同じトークンで保護_ap_iへアクセスすると401(): void
    {
        $user = $this->user();
        $token = $this->loginToken($user);
        $book = Book::factory()->create(['user_id' => $user->id]);

        $this->api('DELETE', "/api/v1/books/{$book->id}", $token)->assertNoContent();
        $this->api('POST', '/api/v1/logout', $token)->assertOk();

        $this->api('POST', '/api/v1/books', $token, $this->payload())->assertUnauthorized();
        $this->api('POST', '/api/v1/logout', $token)->assertUnauthorized();
        $this->assertDatabaseCount('books', 0);
    }

    public function test_ログアウトは使用したトークンのみ失効し他のトークンは有効(): void
    {
        $user = $this->user();
        $tokenA = $this->loginToken($user);
        $tokenB = $this->loginToken($user);
        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->api('POST', '/api/v1/logout', $tokenA)->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->api('POST', '/api/v1/books', $tokenA, $this->payload())->assertUnauthorized();
        $this->api('POST', '/api/v1/books', $tokenB, $this->payload())->assertCreated();
    }

    public function test_他ユーザーのトークンはログアウトの影響を受けない(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $aliceToken = $this->loginToken($alice);
        $this->loginToken($bob);

        $this->api('POST', '/api/v1/logout', $aliceToken)->assertOk();

        $this->assertSame(0, $alice->tokens()->count());
        $this->assertSame(1, $bob->tokens()->count());
    }
}
