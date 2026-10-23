<?php

namespace App\Http\Controllers;

use App\Exceptions\ExternalApiException;
use App\Http\Requests\IsbnSearchRequest;
use App\Services\GoogleBooksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BookSearchController extends Controller
{
    /**
     * ISBNを元にGoogle Books APIから書籍情報を取得する。
     *
     * @param IsbnSearchRequest $request ISBN（13桁の数字）を含むリクエスト
     * @param GoogleBooksService $service Google Books API 連携サービス
     * @return JsonResponse 書籍情報 / ISBN不正は400 / 見つからない場合は404 / 通信失敗は500
     */
    public function show(IsbnSearchRequest $request, GoogleBooksService $service): JsonResponse
    {
        try {
            $bookInfo = $service->findByIsbn($request->validated('isbn'));
        } catch (ExternalApiException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $bookInfo === null
            ? response()->json(['message' => '該当する書籍が見つかりませんでした。'], Response::HTTP_NOT_FOUND)
            : response()->json($bookInfo);
    }
}
