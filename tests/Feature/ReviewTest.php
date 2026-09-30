<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * レビューCRUDの機能テスト
 *
 * テスト名の接頭辞: 投稿 / 更新 / 削除、区分は 正常系・異常系・境界値
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->book = Book::factory()->create();
    }

    /**
     * 有効なレビュー入力データ
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'rating' => 4,
            'comment' => 'とても面白い本でした。',
        ], $overrides);
    }

    private function ownReview(): Review
    {
        return Review::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
        ]);
    }

    private function othersReview(): Review
    {
        return Review::factory()->create(['book_id' => $this->book->id]);
    }

    // ------------------------------------------------------------------
    // 投稿 (Store)
    // ------------------------------------------------------------------

    public function test_投稿_正常系_ログインユーザーがレビューを投稿できる(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), $this->validData());

        $response->assertRedirect(route('books.show', $this->book));
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'rating' => 4,
            'comment' => 'とても面白い本でした。',
        ]);
    }

    public function test_投稿_異常系_未ログインはloginへリダイレクトされる(): void
    {
        $this->post(route('reviews.store', $this->book), $this->validData())
            ->assertRedirect('/login');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_投稿_異常系_評価が未入力だとエラーになり保存されない(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), $this->validData(['rating' => '']));

        $response->assertSessionHasErrors(['rating' => '評価は必須です。']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_投稿_異常系_コメントが未入力だとエラーになり保存されない(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), $this->validData(['comment' => '']));

        $response->assertSessionHasErrors(['comment' => 'コメントを入力してください。']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_投稿_異常系_評価が整数でないとエラーになる(): void
    {
        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), $this->validData(['rating' => 'abc']))
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function 有効な評価値(): array
    {
        return ['下限1' => [1], '上限5' => [5]];
    }

    /**
     * @return array<string, array{0: int|string}>
     */
    public static function 無効な評価値(): array
    {
        return ['下限未満0' => [0], '上限超過6' => [6], '小数' => ['3.5']];
    }

    #[DataProvider('有効な評価値')]
    public function test_投稿_境界値_評価の範囲内は保存できる(int $rating): void
    {
        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), $this->validData(['rating' => $rating]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reviews', ['book_id' => $this->book->id, 'rating' => $rating]);
    }

    #[DataProvider('無効な評価値')]
    public function test_投稿_境界値_評価の範囲外は拒否される(int|string $rating): void
    {
        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), $this->validData(['rating' => $rating]))
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_投稿_境界値_コメント1000文字は保存できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), $this->validData(['comment' => str_repeat('あ', 1000)]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_投稿_境界値_コメント1001文字は拒否される(): void
    {
        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), $this->validData(['comment' => str_repeat('あ', 1001)]))
            ->assertSessionHasErrors(['comment' => 'コメントは1000文字以内で入力してください。']);

        $this->assertDatabaseCount('reviews', 0);
    }

    // ------------------------------------------------------------------
    // 更新 (Update)
    // ------------------------------------------------------------------

    public function test_更新_正常系_編集画面が表示される(): void
    {
        $review = $this->ownReview();

        $this->actingAs($this->user)
            ->get(route('reviews.edit', $review))
            ->assertOk()
            ->assertViewIs('reviews.edit');
    }

    public function test_更新_正常系_自分のレビューを更新できる(): void
    {
        $review = $this->ownReview();

        $response = $this->actingAs($this->user)
            ->put(route('reviews.update', $review), $this->validData(['rating' => 2, 'comment' => '更新後']));

        $response->assertRedirect(route('books.show', $this->book))
            ->assertSessionHas('status', 'レビューを更新しました。');
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 2, 'comment' => '更新後']);
    }

    public function test_更新_異常系_未ログインはloginへリダイレクトされる(): void
    {
        $review = $this->ownReview();

        $this->get(route('reviews.edit', $review))->assertRedirect('/login');
        $this->put(route('reviews.update', $review), $this->validData())->assertRedirect('/login');
    }

    public function test_更新_異常系_他人のレビューは編集画面を開けず403(): void
    {
        $this->actingAs($this->user)
            ->get(route('reviews.edit', $this->othersReview()))
            ->assertForbidden();
    }

    public function test_更新_異常系_他人のレビューは更新できず403(): void
    {
        $review = $this->othersReview();

        $this->actingAs($this->user)
            ->put(route('reviews.update', $review), $this->validData(['comment' => '改ざん']))
            ->assertForbidden();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id, 'comment' => '改ざん']);
    }

    public function test_更新_異常系_必須項目が未入力だと更新されない(): void
    {
        $review = $this->ownReview();

        $this->actingAs($this->user)
            ->put(route('reviews.update', $review), ['rating' => '', 'comment' => ''])
            ->assertSessionHasErrors([
                'rating' => '評価は必須です。',
                'comment' => 'コメントを入力してください。',
            ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
        ]);
    }

    #[DataProvider('有効な評価値')]
    public function test_更新_境界値_評価の範囲内は更新できる(int $rating): void
    {
        $review = $this->ownReview();

        $this->actingAs($this->user)
            ->put(route('reviews.update', $review), $this->validData(['rating' => $rating]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => $rating]);
    }

    #[DataProvider('無効な評価値')]
    public function test_更新_境界値_評価の範囲外は拒否される(int|string $rating): void
    {
        $review = $this->ownReview();

        $this->actingAs($this->user)
            ->put(route('reviews.update', $review), $this->validData(['rating' => $rating]))
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => $review->rating]);
    }

    public function test_更新_境界値_コメント1000文字は更新できる(): void
    {
        $review = $this->ownReview();
        $comment = str_repeat('あ', 1000);

        $this->actingAs($this->user)
            ->put(route('reviews.update', $review), $this->validData(['comment' => $comment]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'comment' => $comment]);
    }

    public function test_更新_境界値_コメント1001文字は拒否される(): void
    {
        $review = $this->ownReview();

        $this->actingAs($this->user)
            ->put(route('reviews.update', $review), $this->validData(['comment' => str_repeat('あ', 1001)]))
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'comment' => $review->comment]);
    }

    // ------------------------------------------------------------------
    // 削除 (Destroy)
    // ------------------------------------------------------------------

    public function test_削除_正常系_自分のレビューを削除できる(): void
    {
        $review = $this->ownReview();

        $this->actingAs($this->user)
            ->delete(route('reviews.destroy', $review))
            ->assertRedirect()
            ->assertSessionHas('success', 'レビューを削除しました。');

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_削除_正常系_レビュー削除でいいねも連動削除される(): void
    {
        $review = $this->ownReview();
        $liker = User::factory()->create();
        $review->likedByUsers()->attach($liker->id);
        $this->assertDatabaseHas('review_likes', ['review_id' => $review->id, 'user_id' => $liker->id]);

        $this->actingAs($this->user)->delete(route('reviews.destroy', $review));

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }

    public function test_削除_正常系_書籍を削除するとレビューも連動削除される(): void
    {
        $review = $this->ownReview();

        $this->book->delete();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_削除_異常系_未ログインはloginへリダイレクトされる(): void
    {
        $review = $this->ownReview();

        $this->delete(route('reviews.destroy', $review))->assertRedirect('/login');

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_削除_異常系_他人のレビューは削除できず403(): void
    {
        $review = $this->othersReview();

        $this->actingAs($this->user)
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }
}
