<?php

namespace App\Services;

use App\Exceptions\ExternalApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Books API 連携サービス。
 */
class GoogleBooksService
{
    /**
     * ISBNから書籍情報を取得する。
     *
     * @param string $isbn 検索対象のISBN
     * @return array{title: string, author: string, published_date: string|null, description: string|null, image_url: string|null}|null 見つからなければ null
     * @throws ExternalApiException 外部APIとの通信に失敗した場合
     */
    public function findByIsbn(string $isbn): ?array
    {
        try {
            $response = Http::timeout(10)->get(config('services.google_books.endpoint'), [
                'q' => 'isbn:' . $isbn,
                'key' => config('services.google_books.key'),
            ]);
        } catch (ConnectionException $e) {
            throw new ExternalApiException('外部APIとの通信に失敗しました。', 0, $e);
        }

        if ($response->failed()) {
            throw new ExternalApiException('外部APIとの通信に失敗しました。');
        }

        $items = collect($response->json('items', []));

        return $items->isEmpty() ? null : $this->extract($items->first()['volumeInfo'] ?? []);
    }

    /**
     * volumeInfo から画面で必要な項目を抽出する。
     *
     * @param array<string, mixed> $volumeInfo Google Books API の volumeInfo
     * @return array{title: string, author: string, published_date: string|null, description: string|null, image_url: string|null}
     */
    private function extract(array $volumeInfo): array
    {
        return [
            'title' => $volumeInfo['title'] ?? '',
            'author' => collect($volumeInfo['authors'] ?? [])->implode(', '),
            'published_date' => $volumeInfo['publishedDate'] ?? null,
            'description' => $volumeInfo['description'] ?? null,
            'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
        ];
    }
}
