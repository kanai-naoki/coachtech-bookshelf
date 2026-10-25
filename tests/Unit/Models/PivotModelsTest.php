<?php

namespace Tests\Unit\Models;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivotModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorite_はuserとbookにbelongs_toする(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book->id);

        $favorite = Favorite::query()->firstOrFail();

        $this->assertInstanceOf(BelongsTo::class, $favorite->user());
        $this->assertInstanceOf(BelongsTo::class, $favorite->book());
        $this->assertTrue($favorite->user->is($user));
        $this->assertTrue($favorite->book->is($book));
    }

    public function test_review_like_はuserとreviewにbelongs_toする(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $user->likedReviews()->attach($review->id);

        $like = ReviewLike::query()->firstOrFail();

        $this->assertInstanceOf(BelongsTo::class, $like->user());
        $this->assertInstanceOf(BelongsTo::class, $like->review());
        $this->assertTrue($like->user->is($user));
        $this->assertTrue($like->review->is($review));
    }

    public function test_toggleで中間モデル経由でも登録と解除ができる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->favoriteBooks()->toggle($book->id);
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);

        $user->favoriteBooks()->toggle($book->id);
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);
    }

    public function test_reading_plans_はuser_idとbook_idで関連付く(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->addWeek(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->assertInstanceOf(HasMany::class, $user->readingPlans());
        $this->assertInstanceOf(HasMany::class, $book->readingPlans());
        $this->assertTrue($user->readingPlans->first()->is($plan));
        $this->assertTrue($book->readingPlans->first()->is($plan));
        $this->assertTrue($plan->user->is($user));
        $this->assertTrue($plan->book->is($book));
    }

    public function test_genre_books_はbook_genre経由で取得できる(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre->id);

        $this->assertSame('book_genre', $genre->books()->getTable());
        $this->assertTrue($genre->books->first()->is($book));
    }
}
