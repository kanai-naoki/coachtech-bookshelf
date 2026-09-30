<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * レビューライク（いいね追加・解除）の機能テスト
 *
 * テスト名の接頭辞: いいね、区分は 正常系・異常系・境界値
 */
class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Review $review;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->review = Review::factory()->create();
    }

    public function test_いいね_正常系_ログインユーザーがいいねを追加できる(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/books')
            ->post(route('reviews.like', $this->review));

        $response->assertRedirect('/books');
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $this->user->id,
            'review_id' => $this->review->id,
        ]);
    }

    public function test_いいね_正常系_いいね済みの場合は解除される(): void
    {
        $this->review->likedByUsers()->attach($this->user->id);

        $this->actingAs($this->user)->post(route('reviews.like', $this->review));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $this->user->id,
            'review_id' => $this->review->id,
        ]);
    }

    public function test_いいね_正常系_他ユーザーのいいねには影響しない(): void
    {
        $other = User::factory()->create();
        $this->review->likedByUsers()->attach($other->id);

        $this->actingAs($this->user)->post(route('reviews.like', $this->review));

        $this->assertDatabaseCount('review_likes', 2);
        $this->actingAs($this->user)->post(route('reviews.like', $this->review));
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $other->id,
            'review_id' => $this->review->id,
        ]);
        $this->assertDatabaseCount('review_likes', 1);
    }

    public function test_いいね_異常系_未ログインはログイン画面へリダイレクトされ保存されない(): void
    {
        $this->post(route('reviews.like', $this->review))
            ->assertRedirect('/login');

        $this->assertDatabaseCount('review_likes', 0);
    }

    public function test_いいね_異常系_存在しないレビューは404(): void
    {
        $this->actingAs($this->user)
            ->post(route('reviews.like', 999999))
            ->assertNotFound();

        $this->assertDatabaseCount('review_likes', 0);
    }

    public function test_いいね_境界値_連続トグルでも重複せず追加と解除を繰り返す(): void
    {
        $this->actingAs($this->user);

        $this->post(route('reviews.like', $this->review));
        $this->assertSame(1, $this->review->likedByUsers()->count());

        $this->post(route('reviews.like', $this->review));
        $this->assertSame(0, $this->review->likedByUsers()->count());

        $this->post(route('reviews.like', $this->review));
        $this->assertSame(1, $this->review->likedByUsers()->count());
    }
}
