<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 書籍モデル。
 *
 * @property int $id
 * @property int $user_id 登録者のユーザーID
 * @property string $title
 * @property string $author
 * @property string $isbn 13桁のISBN（ユニーク）
 * @property Carbon $published_date
 * @property string|null $description
 * @property string|null $image_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Book extends Model
{
    use HasFactory;

    /**
     * 一括代入を許可する属性。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];

    /**
     * 属性のキャスト定義。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'published_date' => 'date',
    ];

    /**
     * この書籍を登録したユーザー。
     *
     * @return BelongsTo<User, Book>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この書籍に投稿されたレビュー一覧。
     *
     * @return HasMany<Review>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * この書籍に紐づくジャンル（中間テーブル: book_genre）。
     *
     * @return BelongsToMany<Genre>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'book_genre');
    }

    /**
     * この書籍をお気に入り登録しているユーザー（中間テーブル: favorites）。
     *
     * @return BelongsToMany<User>
     */
    public function favoriteUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->using(Favorite::class);
    }

    /**
     * この書籍に対する読書計画一覧。
     *
     * @return HasMany<ReadingPlan>
     */
    public function readingPlans(): HasMany
    {
        return $this->hasMany(ReadingPlan::class);
    }

    /**
     * タイトルまたは著者名の部分一致で絞り込む。キーワードが空なら何もしない。
     * LIKE のワイルドカード（% / _）はリテラルとして扱う（エスケープ文字は `!`）。
     *
     * @param  Builder<Book>  $query
     * @param  string|null  $keyword  検索キーワード
     * @return Builder<Book>
     */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        return $query->when(filled($keyword), function (Builder $q) use ($keyword): void {
            $pattern = '%'.preg_replace('/([!%_])/', '!$1', $keyword).'%';

            $q->where(function (Builder $q) use ($pattern): void {
                $q->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("author LIKE ? ESCAPE '!'", [$pattern]);
            });
        });
    }

    /**
     * 指定ジャンルに属する書籍に絞り込む。ジャンルIDが空なら何もしない。
     *
     * @param  Builder<Book>  $query
     * @param  int|string|null  $genreId  ジャンルID
     * @return Builder<Book>
     */
    public function scopeInGenre(Builder $query, int|string|null $genreId): Builder
    {
        return $query->when(filled($genreId), function (Builder $q) use ($genreId): void {
            $q->whereHas('genres', fn (Builder $g) => $g->where('genres.id', $genreId));
        });
    }

    /**
     * 並び順を適用する（latest / oldest / title / rating）。未知の値は latest 扱い。
     * rating は平均評価の高い順で、レビューのない書籍は最後に並べる。
     *
     * @param  Builder<Book>  $query
     * @param  string  $sort  並び順の指定
     * @return Builder<Book>
     */
    public function scopeSortedBy(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'title' => $query->orderBy('title', 'asc'),
            'rating' => $query->withAvg('reviews', 'rating')
                ->orderByRaw('reviews_avg_rating IS NULL ASC')
                ->orderBy('reviews_avg_rating', 'desc'),
            default => $query->latest(),
        };
    }

    /**
     * レビュー平均評価の高い順に、レビューのある書籍を上位から取得するためのスコープ。
     *
     * @param  Builder<Book>  $query
     * @param  int  $limit  取得件数
     * @return Builder<Book>
     */
    public function scopeRanked(Builder $query, int $limit = 10): Builder
    {
        return $query->withAvg('reviews', 'rating')
            ->has('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->limit($limit);
    }
}
