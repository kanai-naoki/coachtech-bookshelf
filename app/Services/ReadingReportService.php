<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * マイ読書レポートの集計サービス。
 */
class ReadingReportService
{
    /**
     * ユーザーのレビューから読書レポートの統計を作成する。
     *
     * @param  User  $user  対象ユーザー
     * @return array{summary: array<string, int|float>, rating_distribution: Collection<int, int>, top_rated_books: Collection<int, array{id: int, title: string, author: string, rating: int}>, genre_ratings: Collection<int, array<string, mixed>>}
     */
    public function build(User $user): array
    {
        /** @var Collection<int, Review> $reviews */
        $reviews = $user->reviews()->with('book.genres')->get();

        return [
            'summary' => [
                'total_reviews' => $reviews->count(),
                'books_read' => $reviews->unique('book_id')->count(),
                'average_rating' => round((float) $reviews->avg('rating'), 1),
            ],
            'rating_distribution' => $this->ratingDistribution($reviews),
            'top_rated_books' => $this->topRatedBooks($reviews),
            'genre_ratings' => $this->genreRatings($reviews),
        ];
    }

    /**
     * 1〜5星ごとのレビュー件数を返す。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, int>
     */
    private function ratingDistribution(Collection $reviews): Collection
    {
        $counts = $reviews->countBy('rating');

        return collect(range(1, 5))->map(fn (int $star): int => $counts->get($star, 0));
    }

    /**
     * 4星以上の書籍を評価の高い順に最大5件返す。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array{id: int, title: string, author: string, rating: int}>
     */
    private function topRatedBooks(Collection $reviews): Collection
    {
        return $reviews->filter(fn (Review $review): bool => $review->rating >= 4)
            ->sortByDesc('rating')
            ->take(5)
            ->map(fn (Review $review): array => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            ->values();
    }

    /**
     * ジャンル別の平均評価と件数を、平均評価の高い順に最大5件返す。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array{id: int, name: string, average_rating: float, count: int}>
     */
    private function genreRatings(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(fn (Review $review) => $review->book->genres->map(fn ($genre): array => [
                'genre' => $genre,
                'rating' => $review->rating,
            ]))
            ->groupBy(fn (array $item): int => $item['genre']->id)
            ->map(fn (Collection $group): array => [
                'id' => $group->first()['genre']->id,
                'name' => $group->first()['genre']->name,
                'average_rating' => round($group->avg('rating'), 1),
                'count' => $group->count(),
            ])
            ->sortByDesc('average_rating')
            ->take(5)
            ->values();
    }
}
