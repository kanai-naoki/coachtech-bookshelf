<?php

namespace Tests\Unit\Services;

use App\Exceptions\ExternalApiException;
use App\Services\GoogleBooksService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBooksServiceTest extends TestCase
{
    private const ISBN = '9784798121963';

    public function test_find_by_isbn_は書籍情報の配列を返す(): void
    {
        Http::fake(['*' => Http::response(['items' => [['volumeInfo' => [
            'title' => 'タイトル',
            'authors' => ['A', 'B'],
            'publishedDate' => '2024',
            'description' => '説明',
            'imageLinks' => ['thumbnail' => 'https://example.com/t.jpg'],
        ]]]])]);

        $this->assertSame([
            'title' => 'タイトル',
            'author' => 'A, B',
            'published_date' => '2024',
            'description' => '説明',
            'image_url' => 'https://example.com/t.jpg',
        ], (new GoogleBooksService())->findByIsbn(self::ISBN));
    }

    public function test_find_by_isbn_は該当なしならnullを返す(): void
    {
        Http::fake(['*' => Http::response(['totalItems' => 0])]);

        $this->assertNull((new GoogleBooksService())->findByIsbn(self::ISBN));
    }

    public function test_find_by_isbn_はHTTPエラーでExternalApiExceptionを投げる(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->expectException(ExternalApiException::class);
        $this->expectExceptionMessage('外部APIとの通信に失敗しました。');

        (new GoogleBooksService())->findByIsbn(self::ISBN);
    }

    public function test_find_by_isbn_は接続失敗を元例外付きでExternalApiExceptionに変換する(): void
    {
        $original = new ConnectionException('timeout');
        Http::fake(fn () => throw $original);

        try {
            (new GoogleBooksService())->findByIsbn(self::ISBN);
            $this->fail('ExternalApiException が投げられるべき');
        } catch (ExternalApiException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }
}
