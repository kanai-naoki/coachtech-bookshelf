<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ランキング（レビュー平均評価TOP10）の機能テスト
 *
 * テスト名の接頭辞: ランキング、区分は 正常系・異常系・境界値
 */
class RankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 指定した評価のレビューを持つ書籍を作成する
     *
     * @param  array<int, int>  $ratings
     */
    private function createBookWithRatings(array $ratings, string $title): Book
    {
        $book = Book::factory()->create(['title' => $title]);

        foreach ($ratings as $rating) {
            Review::factory()->create(['book_id' => $book->id, 'rating' => $rating]);
        }

        return $book;
    }

    public function test_ランキング_正常系_平均評価の高い順に表示される(): void
    {
        $low = $this->createBookWithRatings([2, 3], '低評価の本');     // 2.5
        $high = $this->createBookWithRatings([5, 4], '高評価の本');    // 4.5
        $mid = $this->createBookWithRatings([3, 4, 4], '中評価の本');  // 3.67

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewIs('ranking.index');
        $this->assertSame(
            [$high->id, $mid->id, $low->id],
            $response->viewData('rankedBooks')->pluck('id')->all()
        );
        $response->assertSeeInOrder(['高評価の本', '中評価の本', '低評価の本']);
    }

    public function test_ランキング_正常系_ゲストでも閲覧できる(): void
    {
        $this->createBookWithRatings([5], '公開の本');

        $this->get('/ranking')->assertOk()->assertSee('公開の本');
    }

    public function test_ランキング_異常系_レビューが0件の書籍は表示されない(): void
    {
        $this->createBookWithRatings([4], 'レビューありの本');
        $this->createBookWithRatings([], 'レビューなしの本');

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertSee('レビューありの本');
        $response->assertDontSee('レビューなしの本');
        $this->assertCount(1, $response->viewData('rankedBooks'));
    }

    public function test_ランキング_異常系_レビューが1件もない場合は空メッセージが表示される(): void
    {
        $this->createBookWithRatings([], 'レビューなしの本');

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('まだレビューが投稿された書籍がありません。')
            ->assertDontSee('レビューなしの本');
    }

    public function test_ランキング_境界値_11件以上ある場合は上位10件のみ取得される(): void
    {
        // 12冊作成し、本1・本2のみ最低評価(1)、残り10冊は最高評価(5)
        $books = collect(range(1, 12))->map(
            fn (int $i): Book => $this->createBookWithRatings([$i <= 2 ? 1 : 5], "本{$i}")
        );

        $response = $this->get(route('ranking.index'));

        $ranked = $response->viewData('rankedBooks');
        $this->assertCount(10, $ranked);
        // 最低評価(1)の本1・本2は圏外
        $this->assertEmpty($ranked->pluck('id')->intersect($books->take(2)->pluck('id')));    }

    public function test_ランキング_境界値_ちょうど10件なら全件表示される(): void
    {
        foreach (range(1, 10) as $i) {
            $this->createBookWithRatings([3], "本{$i}");
        }

        $this->assertCount(10, $this->get(route('ranking.index'))->viewData('rankedBooks'));
    }

    public function test_ランキング_境界値_同率の書籍はいずれも同じ平均評価で表示される(): void
    {
        $top = $this->createBookWithRatings([5], '首位の本');
        $tieA = $this->createBookWithRatings([4, 4], '同率A');     // 4.0
        $tieB = $this->createBookWithRatings([3, 5], '同率B');     // 4.0
        $last = $this->createBookWithRatings([2], '最下位の本');

        $ranked = $this->get(route('ranking.index'))->viewData('rankedBooks');

        $this->assertCount(4, $ranked);
        // 同率同士の並びは不定だが、同率グループは首位と最下位の間に連続して並ぶ
        $this->assertSame($top->id, $ranked[0]->id);
        $this->assertSame($last->id, $ranked[3]->id);
        $this->assertEqualsCanonicalizing(
            [$tieA->id, $tieB->id],
            [$ranked[1]->id, $ranked[2]->id]
        );
        $this->assertEquals($ranked[1]->reviews_avg_rating, $ranked[2]->reviews_avg_rating);
    }

    public function test_ランキング_境界値_レビュー1件の書籍も対象になる(): void
    {
        $book = $this->createBookWithRatings([1], '最低評価1件の本');

        $ranked = $this->get(route('ranking.index'))->viewData('rankedBooks');

        $this->assertCount(1, $ranked);
        $this->assertSame($book->id, $ranked->first()->id);
        $this->assertEquals(1, $ranked->first()->reviews_avg_rating);
    }
}
