<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_はタイトルと著者の部分一致で絞り込む(): void
    {
        $byTitle = Book::factory()->create(['title' => 'Laravel入門', 'author' => 'A']);
        $byAuthor = Book::factory()->create(['title' => 'X', 'author' => 'Laravel太郎']);
        Book::factory()->create(['title' => 'Other', 'author' => 'B']);

        $ids = Book::search('Laravel')->pluck('id');

        $this->assertEqualsCanonicalizing([$byTitle->id, $byAuthor->id], $ids->all());
    }

    public function test_search_はキーワードが空なら絞り込まない(): void
    {
        Book::factory()->count(2)->create();

        $this->assertCount(2, Book::search(null)->get());
        $this->assertCount(2, Book::search('')->get());
    }

    public function test_search_はワイルドカード文字をリテラルとして扱う(): void
    {
        $percent = Book::factory()->create(['title' => '100%', 'author' => 'A']);
        $underscore = Book::factory()->create(['title' => 'a_c', 'author' => 'B']);
        Book::factory()->create(['title' => 'abc', 'author' => 'C']);

        $this->assertSame([$percent->id], Book::search('%')->pluck('id')->all());
        $this->assertSame([$underscore->id], Book::search('a_c')->pluck('id')->all());
    }

    public function test_in_genre_は指定ジャンルの書籍のみ返す(): void
    {
        $genre = Genre::factory()->create();
        $inGenre = Book::factory()->create();
        $inGenre->genres()->attach($genre->id);
        Book::factory()->create();

        $this->assertSame([$inGenre->id], Book::inGenre($genre->id)->pluck('id')->all());
        $this->assertCount(2, Book::inGenre(null)->get());
    }

    public function test_sorted_by_の各並び順(): void
    {
        $b = Book::factory()->create(['title' => 'B', 'created_at' => now()->subDays(2)]);
        $a = Book::factory()->create(['title' => 'A', 'created_at' => now()->subDay()]);
        $c = Book::factory()->create(['title' => 'C', 'created_at' => now()]);

        $this->assertSame([$c->id, $a->id, $b->id], Book::sortedBy('latest')->pluck('id')->all());
        $this->assertSame([$c->id, $a->id, $b->id], Book::sortedBy('unknown')->pluck('id')->all());
        $this->assertSame([$b->id, $a->id, $c->id], Book::sortedBy('oldest')->pluck('id')->all());
        $this->assertSame([$a->id, $b->id, $c->id], Book::sortedBy('title')->pluck('id')->all());
    }

    public function test_sorted_by_rating_は評価の高い順でレビューなしは最後(): void
    {
        $none = Book::factory()->create();
        $low = Book::factory()->create();
        $high = Book::factory()->create();
        Review::factory()->create(['book_id' => $low->id, 'rating' => 2]);
        Review::factory()->create(['book_id' => $high->id, 'rating' => 5]);

        $this->assertSame(
            [$high->id, $low->id, $none->id],
            Book::sortedBy('rating')->pluck('id')->all()
        );
    }

    public function test_ranked_はレビューのある書籍を件数制限付きで返す(): void
    {
        Book::factory()->create();
        $books = Book::factory()->count(3)->create();
        $books->each(fn (Book $book, int $i) => Review::factory()->create([
            'book_id' => $book->id,
            'rating' => $i + 1,
        ]));

        $ranked = Book::ranked(2)->get();

        $this->assertCount(2, $ranked);
        $this->assertSame($books[2]->id, $ranked->first()->id);
    }
}
