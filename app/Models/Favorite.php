<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * お気に入りの中間モデル（favorites テーブル）。
 *
 * ユーザーと書籍の組み合わせはユニーク。
 *
 * @property int $id
 * @property int $user_id
 * @property int $book_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Favorite extends Pivot
{
    /**
     * 関連付けるテーブル名。
     *
     * @var string
     */
    protected $table = 'favorites';

    /**
     * 主キー(id)が自動採番されることを示す。
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * お気に入り登録したユーザー。
     *
     * @return BelongsTo<User, Favorite>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * お気に入り登録された書籍。
     *
     * @return BelongsTo<Book, Favorite>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
