<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;


class BookSearchController extends Controller
{
    /**
     * ISBNを元にGoogle Books APIから書籍情報を取得する
     */
    public function show(string $isbn): JsonResponse
    {
        // Google Books API エンドポイント
        $response = Http::get("https://www.googleapis.com/books/v1/volumes", [
            'q' => 'isbn:' . $isbn,
            'key' => env('GOOGLE_BOOKS_API_KEY'),
        ]);

        if ($response->failed()) {
            return response()->json([
                'message' => '外部APIとの通信に失敗しました。',
            ], 500);
        }

        $data = $response->json();

        // 該当する書籍が見つからない場合
        if (empty($data['items'])) {
            return response()->json(['message' => '該当する書籍が見つかりませんでした。'], 404);
        }

        $volumeInfo = $data['items'][0]['volumeInfo'] ?? [];

        // 必要な情報を抽出しフロントへ返す
        $bookInfo = [
            'title' => $volumeInfo['title'] ?? '',
            'author' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : '',
            'published_date' => $volumeInfo['publishedDate'] ?? null,
            'description' => $volumeInfo['description'] ?? null,
            'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
        ];

        return response()->json($bookInfo);
    }
}