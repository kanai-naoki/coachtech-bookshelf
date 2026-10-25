<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 書籍一覧画面の検索・フィルタ・ソート・ページネーションの機能テスト
 */
class BookIndexSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一覧にビューへ渡された書籍のタイトル配列を返す。
     *
     * @return array<int, string>
     */
    private function titles(TestResponse $response): array
    {
        return $response->viewData('books')->pluck('title')->all();
    }

    /**
     * 書籍を作成してジャンルを紐付ける。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function book(array $attributes = [], ?Genre $genre = null): Book
    {
        $book = Book::factory()->create($attributes);

        if ($genre) {
            $book->genres()->attach($genre->id);
        }

        return $book;
    }

    // ==================== 基本表示・リセット ====================

    public function test_条件なしで10件ずつページネーション表示される(): void
    {
        Book::factory()->count(12)->create();

        $response = $this->get(route('books.index'))->assertOk();

        $books = $response->viewData('books');
        $this->assertInstanceOf(LengthAwarePaginator::class, $books);
        $this->assertCount(10, $books);
        $this->assertSame(12, $books->total());
    }

    public function test_リセットリンクは検索条件なしの一覧を指す(): void
    {
        $this->get(route('books.index', ['keyword' => 'x', 'genre' => 1, 'sort' => 'title']))
            ->assertOk()
            ->assertSee('href="'.route('books.index').'"', false)
            ->assertSee('リセット');
    }

    public function test_リセット後は全件が表示され検索結果件数は表示されない(): void
    {
        $this->book(['title' => '絞り込み対象']);
        $this->book(['title' => '別の本']);

        $filtered = $this->get(route('books.index', ['keyword' => '絞り込み']));
        $this->assertSame(['絞り込み対象'], $this->titles($filtered));
        $filtered->assertSee('検索結果: 1件');

        $reset = $this->get(route('books.index'))->assertOk();
        $this->assertEqualsCanonicalizing(['絞り込み対象', '別の本'], $this->titles($reset));
        $reset->assertDontSee('検索結果:');
    }

    // ==================== キーワード検索 ====================

    public function test_キーワードはタイトルの部分一致で検索できる(): void
    {
        $this->book(['title' => 'Laravel入門']);
        $this->book(['title' => 'PHPの基礎']);

        $response = $this->get(route('books.index', ['keyword' => 'ravel']));

        $this->assertSame(['Laravel入門'], $this->titles($response));
    }

    public function test_キーワードは著者名の部分一致で検索できる(): void
    {
        $this->book(['title' => '本A', 'author' => '夏目漱石']);
        $this->book(['title' => '本B', 'author' => '芥川龍之介']);

        $response = $this->get(route('books.index', ['keyword' => '漱石']));

        $this->assertSame(['本A'], $this->titles($response));
    }

    public function test_キーワードは完全一致でもヒットする(): void
    {
        $this->book(['title' => '吾輩は猫である', 'author' => '夏目漱石']);
        $this->book(['title' => '坊っちゃん', 'author' => '夏目漱石']);

        $response = $this->get(route('books.index', ['keyword' => '吾輩は猫である']));

        $this->assertSame(['吾輩は猫である'], $this->titles($response));
    }

    public function test_該当0件のときメッセージが表示される(): void
    {
        $this->book(['title' => '存在する本']);

        $this->get(route('books.index', ['keyword' => '存在しないキーワード']))
            ->assertOk()
            ->assertSee('書籍が見つかりませんでした。')
            ->assertSee('検索結果: 0件')
            ->assertDontSee('存在する本');
    }

    public function test_空のキーワードは絞り込みを行わない(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->get(route('books.index', ['keyword' => '']));

        $this->assertCount(3, $response->viewData('books'));
    }

    // ==================== ジャンル絞り込み・複合条件 ====================

    public function test_ジャンル単体で絞り込める(): void
    {
        $target = Genre::factory()->create();
        $other = Genre::factory()->create();
        $this->book(['title' => '対象ジャンルの本'], $target);
        $this->book(['title' => '他ジャンルの本'], $other);
        $this->book(['title' => 'ジャンルなしの本']);

        $response = $this->get(route('books.index', ['genre' => $target->id]));

        $this->assertSame(['対象ジャンルの本'], $this->titles($response));
        $response->assertSee('検索結果: 1件');
    }

    public function test_キーワードとジャンルとソートを同時に指定できる(): void
    {
        $genre = Genre::factory()->create();
        $this->book(['title' => 'PHP 中級'], $genre);
        $this->book(['title' => 'PHP 入門'], $genre);
        $this->book(['title' => 'PHP 他ジャンル']);
        $this->book(['title' => 'Ruby 入門'], $genre);

        $response = $this->get(route('books.index', [
            'keyword' => 'PHP',
            'genre' => $genre->id,
            'sort' => 'title',
        ]));

        $this->assertSame(['PHP 中級', 'PHP 入門'], $this->titles($response));
    }

    // ==================== ソート ====================

    public function test_既定は登録日の新しい順である(): void
    {
        $this->book(['title' => '古い', 'created_at' => now()->subDays(2)]);
        $this->book(['title' => '新しい', 'created_at' => now()]);
        $this->book(['title' => '中間', 'created_at' => now()->subDay()]);

        $this->assertSame(['新しい', '中間', '古い'], $this->titles($this->get(route('books.index'))));
        $this->assertSame(
            ['新しい', '中間', '古い'],
            $this->titles($this->get(route('books.index', ['sort' => 'latest'])))
        );
    }

    public function test_登録日の古い順で並ぶ(): void
    {
        $this->book(['title' => '古い', 'created_at' => now()->subDays(2)]);
        $this->book(['title' => '新しい', 'created_at' => now()]);
        $this->book(['title' => '中間', 'created_at' => now()->subDay()]);

        $response = $this->get(route('books.index', ['sort' => 'oldest']));

        $this->assertSame(['古い', '中間', '新しい'], $this->titles($response));
    }

    public function test_タイトル順で並ぶ(): void
    {
        $this->book(['title' => 'C']);
        $this->book(['title' => 'A']);
        $this->book(['title' => 'B']);

        $response = $this->get(route('books.index', ['sort' => 'title']));

        $this->assertSame(['A', 'B', 'C'], $this->titles($response));
    }

    public function test_評価順は高い順でレビューなしの本が最後尾になる(): void
    {
        $this->book(['title' => 'レビューなし']);
        $low = $this->book(['title' => '低評価']);
        $high = $this->book(['title' => '高評価']);
        Review::factory()->create(['book_id' => $low->id, 'rating' => 2]);
        Review::factory()->create(['book_id' => $high->id, 'rating' => 5]);

        $response = $this->get(route('books.index', ['sort' => 'rating']));

        $this->assertSame(['高評価', '低評価', 'レビューなし'], $this->titles($response));
    }

    public function test_未知のソート指定は新しい順にフォールバックする(): void
    {
        $this->book(['title' => '古い', 'created_at' => now()->subDay()]);
        $this->book(['title' => '新しい', 'created_at' => now()]);

        $response = $this->get(route('books.index', ['sort' => 'unknown']));

        $this->assertSame(['新しい', '古い'], $this->titles($response));
    }

    // ==================== ページネーションとの連携 ====================

    public function test_2ページ目でも検索条件がページリンクに保持される(): void
    {
        $genre = Genre::factory()->create();
        foreach (range(1, 25) as $i) {
            $this->book(['title' => sprintf('検索対象%02d', $i)], $genre);
        }
        $this->book(['title' => '無関係']);
        $query = ['keyword' => '検索対象', 'genre' => $genre->id, 'sort' => 'title'];

        $page1 = $this->get(route('books.index', $query))->assertOk()->viewData('books');
        $next = $page1->nextPageUrl();
        parse_str(parse_url($next, PHP_URL_QUERY), $params);

        $this->assertSame('検索対象', $params['keyword']);
        $this->assertSame((string) $genre->id, $params['genre']);
        $this->assertSame('title', $params['sort']);
        $this->assertSame('2', $params['page']);

        $page2 = $this->get(route('books.index', $query + ['page' => 2]))->assertOk();
        $this->assertSame(
            ['検索対象11', '検索対象12', '検索対象13', '検索対象14', '検索対象15',
                '検索対象16', '検索対象17', '検索対象18', '検索対象19', '検索対象20'],
            $this->titles($page2)
        );
        $this->assertSame(25, $page2->viewData('books')->total());
    }

    public function test_ページ番号はリンクにkeywordを含むクエリが出力される(): void
    {
        Book::factory()->count(11)->create(['title' => '連携テスト']);

        $this->get(route('books.index', ['keyword' => '連携']))
            ->assertOk()
            ->assertSee('keyword=%E9%80%A3%E6%90%BA', false);
    }

    // ==================== セキュリティ・特殊文字 ====================

    public function test_パーセントはワイルドカードではなくリテラルとして検索される(): void
    {
        $this->book(['title' => '100%達成']);
        $this->book(['title' => '百パーセント']);
        $this->book(['title' => '無関係な本']);

        $response = $this->get(route('books.index', ['keyword' => '%']));

        $this->assertSame(['100%達成'], $this->titles($response));
    }

    public function test_アンダースコアは任意1文字ではなくリテラルとして検索される(): void
    {
        $this->book(['title' => 'a_c']);
        $this->book(['title' => 'abc']);
        $this->book(['title' => 'axc']);

        $response = $this->get(route('books.index', ['keyword' => 'a_c']));

        $this->assertSame(['a_c'], $this->titles($response));
    }

    public function test_著者名でもワイルドカードはエスケープされる(): void
    {
        $this->book(['title' => '本1', 'author' => '山田_太郎']);
        $this->book(['title' => '本2', 'author' => '山田次郎']);

        $response = $this->get(route('books.index', ['keyword' => '山田_']));

        $this->assertSame(['本1'], $this->titles($response));
    }

    public function test_エスケープ文字の感嘆符自体も検索できる(): void
    {
        $this->book(['title' => 'すごい!本']);
        $this->book(['title' => '普通の本']);

        $response = $this->get(route('books.index', ['keyword' => '!']));

        $this->assertSame(['すごい!本'], $this->titles($response));
    }

    public function test_sq_lインジェクション文字列は安全に処理される(): void
    {
        $this->book(['title' => '通常の本']);

        $response = $this->get(route('books.index', ['keyword' => "' OR 1=1; DROP TABLE books; --"]))
            ->assertOk();

        $this->assertSame([], $this->titles($response));
        $this->assertDatabaseCount('books', 1);
    }

    public function test_タイトルの_htm_lタグはエスケープして表示される(): void
    {
        $this->book(['title' => '<script>alert("xss")</script>', 'author' => '<b>著者</b>']);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertDontSee('<b>著者</b>', false)
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }

    public function test_検索キーワードの_htm_lタグは入力欄でエスケープされる(): void
    {
        $this->get(route('books.index', ['keyword' => '"><script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
