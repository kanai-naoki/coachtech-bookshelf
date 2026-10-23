<?php

namespace Database\Factories;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlan>
 */
class ReadingPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'target_date' => now()->addDays(14)->toDateString(),
            'completed_at' => null,
            'status' => ReadingPlanStatus::InProgress,
        ];
    }

    /**
     * 期限切れの計画。
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'target_date' => now()->subDays(5)->toDateString(),
            'status' => ReadingPlanStatus::Expired,
        ]);
    }

    /**
     * 読了済みの計画。
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
