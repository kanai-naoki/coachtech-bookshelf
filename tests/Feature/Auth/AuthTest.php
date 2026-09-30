<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 認証機能（会員登録・ログイン・ログアウト）の機能テスト
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 会員登録の有効な入力値
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function registerData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    // ==================== 会員登録: 正常系 ====================

    public function test_register_画面が表示される(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_register_正しい入力で登録しログイン状態になる(): void
    {
        $response = $this->post('/register', $this->registerData());

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['name' => 'テスト太郎', 'email' => 'taro@example.com']);
    }

    public function test_register_パスワードはハッシュ化されて保存される(): void
    {
        $this->post('/register', $this->registerData());

        $user = User::where('email', 'taro@example.com')->firstOrFail();
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    // ==================== 会員登録: 異常系 ====================

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function invalidRegisterProvider(): array
    {
        return [
            '名前が未入力' => [['name' => ''], 'name'],
            '名前が256文字' => [['name' => str_repeat('あ', 256)], 'name'],
            'メールが未入力' => [['email' => ''], 'email'],
            'メール形式が不正' => [['email' => 'not-an-email'], 'email'],
            'パスワードが未入力' => [['password' => '', 'password_confirmation' => ''], 'password'],
            'パスワード確認が不一致' => [['password_confirmation' => 'different123'], 'password'],
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     */
    #[DataProvider('invalidRegisterProvider')]
    public function test_register_不正な入力はバリデーションエラー(array $overrides, string $errorKey): void
    {
        $response = $this->from('/register')->post('/register', $this->registerData($overrides));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors($errorKey);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_重複メールはエラーになり登録されない(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $response = $this->from('/register')->post('/register', $this->registerData());

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_register_エラーメッセージが画面に表示される(): void
    {
        $response = $this->followingRedirects()
            ->from('/register')
            ->post('/register', $this->registerData(['email' => '']));

        $response->assertOk();
        $response->assertSee($this->app['translator']->get('validation.required', [
            'attribute' => $this->app['translator']->get('validation.attributes.email'),
        ]));
    }

    // ==================== 会員登録: 境界値 ====================

    public function test_register_パスワード8文字は登録できる(): void
    {
        $response = $this->post('/register', $this->registerData([
            'password' => 'abcd1234',
            'password_confirmation' => 'abcd1234',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_register_パスワード7文字はバリデーションエラー(): void
    {
        $response = $this->from('/register')->post('/register', $this->registerData([
            'password' => 'abcd123',
            'password_confirmation' => 'abcd123',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    // ==================== ログイン: 正常系 ====================

    public function test_login_画面が表示される(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_正しい認証情報でログインできる(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password123']);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_ログイン後はセッションIDが再生成される(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_login_ログイン済みならゲスト用画面にアクセスするとリダイレクトされる(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect('/');
    }

    // ==================== ログイン: 異常系 ====================

    public function test_login_誤ったパスワードは拒否される(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_存在しないメールは拒否される(): void
    {
        $response = $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'password123']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_メール未入力はバリデーションエラー(): void
    {
        $response = $this->from('/login')->post('/login', ['email' => '', 'password' => 'password123']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_パスワード未入力はバリデーションエラー(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => '']);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_login_未ログインで認証必須ページはログイン画面へリダイレクトされる(): void
    {
        $this->get('/genres')->assertRedirect('/login');
    }

    // ==================== ログイン: 境界値 ====================

    public function test_login_8文字パスワードのユーザーでログインできる(): void
    {
        $user = User::factory()->create(['password' => Hash::make('abcd1234')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'abcd1234']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_パスワード末尾1文字違いは拒否される(): void
    {
        $user = User::factory()->create(['password' => Hash::make('abcd1234')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'abcd123']);

        $this->assertGuest();
    }

    // ==================== ログアウト ====================

    public function test_logout_ログアウトでき未認証状態になる(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_logout_ログアウト後は認証必須ページにアクセスできない(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout');

        $this->get('/genres')->assertRedirect('/login');
    }

    public function test_logout_セッショントークンが再生成される(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['foo' => 'bar']);
        $before = session()->token();

        $this->post('/logout');

        $this->assertNotSame($before, session()->token());
        $this->assertNull(session('foo'));
    }

    public function test_logout_未ログインでのログアウトはログイン画面へリダイレクトされる(): void
    {
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
