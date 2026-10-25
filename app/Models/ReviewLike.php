<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * レビューへの「いいね」の中間モデル（review_likes テーブル）。
 *
 * ユーザーとレビューの組み合わせはユニーク。
 *
 * @property int $id
 * @property int $user_id
 * @property int $review_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReviewLike extends Pivot
{
    /**
     * 関連付けるテーブル名。
     *
     * @var string
     */
    protected $table = 'review_likes';

    /**
     * 主キー(id)が自動採番されることを示す。
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * 「いいね」したユーザー。
     *
     * @return BelongsTo<User, ReviewLike>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 「いいね」された対象レビュー。
     *
     * @return BelongsTo<Review, ReviewLike>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
