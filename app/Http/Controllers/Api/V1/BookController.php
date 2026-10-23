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
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * 書籍一覧取得 (GET /api/v1/books)。
     * キーワード検索・ジャンル絞り込み・ページネーションに対応する。
     *
     * @param BookIndexRequest $request keyword / genre_id / per_page を含む検証済みリクエスト
     * @return AnonymousResourceCollection
     */
    public function index(BookIndexRequest $request): AnonymousResourceCollection
    {
        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->search($request->input('keyword'))
            ->inGenre($request->input('genre_id'))
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return BookResource::collection($books);
    }

    /**
     * 書籍新規登録 (POST /api/v1/books)。書籍とジャンル紐付けを1トランザクションで行う。
     *
     * @param BookStoreRequest $request 検証済みの書籍入力
     * @return JsonResponse 201 Created
     */
    public function store(BookStoreRequest $request): JsonResponse
    {
        $book = DB::transaction(function () use ($request): Book {
            $book = Book::create(
                $request->safe()->except('genre_ids') + ['user_id' => $request->user()->id]
            );
            $book->genres()->sync($request->validated('genre_ids'));

            return $book;
        });

        return (new BookResource($this->loadDetails($book)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * 書籍詳細取得 (GET /api/v1/books/{book})。
     *
     * @param Book $book ルートモデルバインディングされた書籍
     * @return BookResource
     */
    public function show(Book $book): BookResource
    {
        return new BookResource($this->loadDetails($book));
    }

    /**
     * 書籍更新 (PUT/PATCH /api/v1/books/{book})（登録者本人のみ）。
     * 書籍とジャンル紐付けの更新を1トランザクションで行う。
     *
     * @param BookUpdateRequest $request 検証済みの書籍入力
     * @param Book $book 更新対象の書籍
     * @return BookResource 200 OK
     */
    public function update(BookUpdateRequest $request, Book $book): BookResource
    {
        $this->authorize('update', $book);

        DB::transaction(function () use ($request, $book): void {
            $book->update($request->safe()->except('genre_ids'));
            $book->genres()->sync($request->validated('genre_ids'));
        });

        return new BookResource($this->loadDetails($book));
    }

    /**
     * 書籍削除 (DELETE /api/v1/books/{book})（登録者本人のみ）。
     *
     * @param Book $book 削除対象の書籍
     * @return Response 204 No Content
     */
    public function destroy(Book $book): Response
    {
        $this->authorize('delete', $book);

        DB::transaction(function () use ($book): void {
            $book->genres()->detach();
            $book->delete();
        });

        return response()->noContent();
    }

    /**
     * レスポンス用にジャンルと評価の集計値（平均・件数）を読み込む。
     *
     * @param Book $book 対象の書籍
     * @return Book 読み込み済みの同じインスタンス
     */
    private function loadDetails(Book $book): Book
    {
        return $book->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');
    }
}
