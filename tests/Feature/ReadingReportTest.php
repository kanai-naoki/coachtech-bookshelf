<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * マイ読書レポート（/reports）の機能テスト
 */
class ReadingReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * 指定評価のレビューを持つ書籍を作成する（1ユーザー1書籍1レビューのため書籍は都度作成）。
     *
     * @param array<int, Genre> $genres
     * @param array<string, mixed> $bookAttributes
     */
    private function review(int $rating, array $genres = [], ?User $user = null, array $bookAttributes = []): Review
    {
        $book = Book::factory()->create($bookAttributes);
        $book->genres()->attach(collect($genres)->pluck('id')->all());

        return Review::factory()->create([
            'user_id' => ($user ?? $this->user)->id,
            'book_id' => $book->id,
            'rating' => $rating,
        ]);
    }

    private function genre(string $name): Genre
    {
        return Genre::factory()->create(['name' => $name]);
    }

    private function report(): TestResponse
    {
        return $this->actingAs($this->user)->get('/reports');
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        return $this->report()->assertOk()->viewData('stats');
    }

    // ==================== 1. 認証・認可 ====================

    public function test_未認証はログイン画面へリダイレクトされる(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_認証済みなら200でレポートが表示される(): void
    {
        $this->report()
            ->assertOk()
            ->assertViewIs('reports.index')
            ->assertSee('マイ読書レポート')
            ->assertSee('基本統計')
            ->assertSee('評価分布')
            ->assertSee('高評価書籍 TOP5')
            ->assertSee('ジャンル別評価傾向 TOP5');
    }

    public function test_ビューにはsummaryなど4種類の統計が渡される(): void
    {
        $stats = $this->stats();

        $this->assertSame(
            ['summary', 'rating_distribution', 'top_rated_books', 'genre_ratings'],
            array_keys($stats)
        );
    }

    // ==================== 2. サマリー ====================

    public function test_総レビュー数と冊数と平均評価が正しく計算される(): void
    {
        foreach ([5, 4, 3, 2] as $rating) {
            $this->review($rating);
        }

        $summary = $this->stats()['summary'];

        $this->assertSame(4, $summary['total_reviews']);
        $this->assertSame(4, $summary['books_read']);
        $this->assertEquals(3.5, $summary['average_rating']);
        $this->report()->assertSee('3.5');
    }

    public function test_平均評価は小数第1位に丸められる(): void
    {
        foreach ([5, 4, 4] as $rating) {
            $this->review($rating);
        }

        $this->assertEquals(4.3, $this->stats()['summary']['average_rating']);
    }

    public function test_他ユーザーのレビューは集計に含まれない(): void
    {
        $this->review(5);
        $other = User::factory()->create();
        $this->review(1, [], $other);
        $this->review(1, [], $other);

        $summary = $this->stats()['summary'];

        $this->assertSame(1, $summary['total_reviews']);
        $this->assertSame(1, $summary['books_read']);
        $this->assertEquals(5, $summary['average_rating']);
    }

    public function test_レビューが0件でもエラーにならず初期値で表示される(): void
    {
        $response = $this->report()->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(0, $stats['summary']['total_reviews']);
        $this->assertSame(0, $stats['summary']['books_read']);
        $this->assertEquals(0, $stats['summary']['average_rating']);
        $this->assertSame([0, 0, 0, 0, 0], $stats['rating_distribution']->all());
        $this->assertCount(0, $stats['top_rated_books']);
        $this->assertCount(0, $stats['genre_ratings']);

        $response->assertSee('4星以上の書籍がありません')
            ->assertSee('ジャンルが設定された書籍のレビューがありません');
    }

    // ==================== 3. 評価分布 ====================

    public function test_星ごとの件数が1から5の順に集計される(): void
    {
        // 星1:1件, 星2:0件, 星3:2件, 星4:3件, 星5:1件
        foreach ([1, 3, 3, 4, 4, 4, 5] as $rating) {
            $this->review($rating);
        }
        $this->review(2, [], User::factory()->create());

        $distribution = $this->stats()['rating_distribution'];

        $this->assertSame([1, 0, 2, 3, 1], $distribution->all());
    }

    public function test_評価分布が画面に件数で表示される(): void
    {
        foreach ([4, 4, 4, 5] as $rating) {
            $this->review($rating);
        }

        $this->report()
            ->assertSee('3件')
            ->assertSee('1件')
            ->assertSee('0件')
            ->assertSee('★★★★★')
            ->assertSee('width: 100%', false)
            ->assertSee('width: 0%', false);
    }

    // ==================== 4. 高評価書籍TOP5 ====================

    public function test_4星以上の書籍が評価の高い順に最大5件に絞られる(): void
    {
        $this->review(3, [], null, ['title' => '星3の本']);
        $this->review(4, [], null, ['title' => '星4の本A']);
        $this->review(5, [], null, ['title' => '星5の本A']);
        $this->review(4, [], null, ['title' => '星4の本B']);
        $this->review(5, [], null, ['title' => '星5の本B']);
        $this->review(4, [], null, ['title' => '星4の本C']);
        $this->review(5, [], null, ['title' => '星5の本C']);
        $this->review(2, [], null, ['title' => '星2の本']);

        $top = $this->stats()['top_rated_books'];

        $this->assertCount(5, $top);
        $this->assertSame([5, 5, 5, 4, 4], $top->pluck('rating')->all());
        $this->assertEqualsCanonicalizing(
            ['星5の本A', '星5の本B', '星5の本C'],
            $top->take(3)->pluck('title')->all()
        );
        $this->assertNotContains('星3の本', $top->pluck('title')->all());
        $this->assertNotContains('星2の本', $top->pluck('title')->all());
    }

    public function test_ちょうど4星は対象で3星は対象外(): void
    {
        $four = $this->review(4);
        $this->review(3);

        $top = $this->stats()['top_rated_books'];

        $this->assertSame([$four->book_id], $top->pluck('id')->all());
    }

    public function test_5件未満ならある分だけ表示される(): void
    {
        $this->review(5);
        $this->review(4);

        $this->assertCount(2, $this->stats()['top_rated_books']);
    }

    public function test_高評価書籍は詳細ページへのリンクと情報が表示される(): void
    {
        $review = $this->review(4, [], null, ['title' => 'リンク確認の本', 'author' => 'リンク著者']);

        $response = $this->report()
            ->assertSee('href="' . route('books.show', $review->book_id) . '"', false)
            ->assertSee('リンク確認の本')
            ->assertSee('リンク著者')
            ->assertSee('★★★★☆');

        $this->assertSame(
            ['id' => $review->book_id, 'title' => 'リンク確認の本', 'author' => 'リンク著者', 'rating' => 4],
            $response->viewData('stats')['top_rated_books']->first()
        );

        $this->get(route('books.show', $review->book_id))->assertOk();
    }

    public function test_他ユーザーの高評価書籍は表示されない(): void
    {
        $this->review(5, [], User::factory()->create(), ['title' => '他人の高評価本']);

        $this->report()->assertDontSee('他人の高評価本')->assertSee('4星以上の書籍がありません');
    }

    // ==================== 5. ジャンル別評価傾向TOP5 ====================

    public function test_ジャンルごとの平均評価と件数が算出される(): void
    {
        $fiction = $this->genre('小説');
        $tech = $this->genre('技術書');
        $this->review(5, [$fiction]);
        $this->review(4, [$fiction]);
        $this->review(3, [$tech]);

        $ratings = $this->stats()['genre_ratings'];

        $this->assertSame(
            [
                ['id' => $fiction->id, 'name' => '小説', 'average_rating' => 4.5, 'count' => 2],
                ['id' => $tech->id, 'name' => '技術書', 'average_rating' => 3.0, 'count' => 1],
            ],
            $ratings->all()
        );
    }

    public function test_複数ジャンルを持つ書籍は各ジャンルに計上される(): void
    {
        $a = $this->genre('ジャンルA');
        $b = $this->genre('ジャンルB');
        $this->review(4, [$a, $b]);

        $ratings = $this->stats()['genre_ratings']->keyBy('name');

        $this->assertSame(1, $ratings['ジャンルA']['count']);
        $this->assertSame(1, $ratings['ジャンルB']['count']);
    }

    public function test_ジャンルなしの書籍と他ユーザーのレビューは集計されない(): void
    {
        $genre = $this->genre('小説');
        $this->review(5);
        $this->review(1, [$genre], User::factory()->create());

        $this->assertCount(0, $this->stats()['genre_ratings']);
    }

    public function test_ジャンルは平均評価の高い順に最大5件に絞られる(): void
    {
        foreach ([1, 5, 3, 2, 4, 1] as $i => $rating) {
            $this->review($rating, [$this->genre("ジャンル{$i}")]);
        }

        $ratings = $this->stats()['genre_ratings'];

        $this->assertCount(5, $ratings);
        $this->assertSame([5.0, 4.0, 3.0, 2.0, 1.0], $ratings->pluck('average_rating')->all());
    }

    public function test_ジャンルの平均は小数第1位に丸められる(): void
    {
        $genre = $this->genre('丸め確認');
        foreach ([5, 4, 4] as $rating) {
            $this->review($rating, [$genre]);
        }

        $this->assertSame(4.3, $this->stats()['genre_ratings']->first()['average_rating']);
    }

    public function test_ジャンルは詳細ページへのリンクと情報が表示される(): void
    {
        $genre = $this->genre('リンク確認ジャンル');
        $this->review(5, [$genre]);
        $this->review(4, [$genre]);

        $this->report()
            ->assertSee('href="' . route('genres.show', $genre->id) . '"', false)
            ->assertSee('リンク確認ジャンル')
            ->assertSee('2件のレビュー')
            ->assertSee('4.5');

        $this->get(route('genres.show', $genre->id))->assertOk();
    }
}
