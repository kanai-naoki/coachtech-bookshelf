<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingReportController extends Controller
{
    /**
     * ログインユーザーのマイ読書レポートを表示する
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $reviews = Review::with(['book.genres', 'book'])
            ->where('user_id', $user->id)
            ->get();

        // 1. 基本サマリーの計算
        $totalReviews = $reviews->count();
        $totalBooks = $reviews->unique('book_id')->count();
        $averageRating = $totalReviews > 0 ? round($reviews->avg('rating'), 1) : 0;

        // 2. 評価分布 (1〜5星ごとの件数)
        $ratingDistribution = collect([1, 2, 3, 4, 5])->map(function ($star) use ($reviews) {
            return $reviews->where('rating', $star)->count();
        });

        // 3. 高評価書籍TOP5 (4星以上の書籍を評価の高い順に最大5件)
        $topRatedBooks = $reviews->filter(function ($review) {
            return $review->rating >= 4;
        })
            ->sortByDesc('rating')
            ->take(5)
            ->map(function ($review) {
                return $review->book;
            })
            ->values();

        // 4. ジャンル別評価傾向TOP5
        $genreRatings = $reviews->flatMap(function ($review) {
            return $review->book->genres->map(function ($genre) use ($review) {
                return [
                    'genre' => $genre,
                    'rating' => $review->rating,
                ];
            });
        })
            ->groupBy(function ($item) {
                return $item['genre']->id;
            })
            ->map(function ($group) {
                $genre = $group->first()['genre'];
                return [
                    'id' => $genre->id,
                    'name' => $genre->name,
                    'average_rating' => round($group->avg('rating'), 1),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('average_rating')
            ->take(5)
            ->values();

        $stats = [
            'summary' => [
                'total_reviews' => $totalReviews,
                'books_read' => $totalBooks,
                'average_rating' => $averageRating,
            ],
            'rating_distribution' => $ratingDistribution,
            'top_rated_books' => $topRatedBooks,
            'genre_ratings' => $genreRatings,
        ];

        return view('reports.index', compact('stats'));
    }
}
