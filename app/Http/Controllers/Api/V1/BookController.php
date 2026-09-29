<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookIndexRequest;
use App\Http\Requests\Api\V1\BookStoreRequest;
use App\Http\Requests\Api\V1\BookUpdateRequest;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BookController extends Controller
{
    /**
     * 書籍一覧取得 (GET /api/v1/books)
     */
    public function index(BookIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 20);

        $query = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        // キーワード検索（タイトル・著者名）
        if ($keyword = $request->input('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        // ジャンル絞り込み
        if ($genreId = $request->input('genre_id')) {
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        $books = $query->latest()->paginate($perPage);

        return BookResource::collection($books);
    }

    /**
     * 書籍新規登録 (POST /api/v1/books)
     */
    public function store(BookStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $genreIds = $validated['genre_ids'];
        unset($validated['genre_ids']);

        // 書籍レコード作成
        $book = Book::create($validated);

        // ジャンル（中間テーブル）の同期
        $book->genres()->sync($genreIds);

        // レスポンス用のリレーションおよび集計値のロード
        $book->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED); // 201 Created
    }

    /**
     * 書籍詳細取得 (GET /api/v1/books/{book})
     */
    public function show(Book $book): BookResource
    {
        $book->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * 書籍更新 (PUT/PATCH /api/v1/books/{book})
     */
    public function update(BookUpdateRequest $request, Book $book): BookResource
    {
        $validated = $request->validated();
        $genreIds = $validated['genre_ids'];
        unset($validated['genre_ids']);

        // 書籍レコード更新
        $book->update($validated);

        // ジャンル（中間テーブル）の同期
        $book->genres()->sync($genreIds);

        // レスポンス用のリレーションおよび集計値のロード
        $book->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book); // 200 OK
    }

    /**
     * 書籍削除 (DELETE /api/v1/books/{book})
     */
    public function destroy(Book $book): Response
    {
        $book->delete();

        return response()->noContent(); // 204 No Content
    }
}