<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * ジャンルモデル。
 *
 * @property int $id
 * @property int $user_id 登録者のユーザーID
 * @property string $name ジャンル名（ユニーク）
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Genre extends Model
{
    use HasFactory;

    /**
     * 一括代入を許可する属性。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
    ];

    /**
     * このジャンルを登録したユーザー。
     *
     * @return BelongsTo<User, Genre>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * このジャンルに属する書籍（中間テーブル: book_genre）。
     *
     * @return BelongsToMany<Book>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_genre');
    }
}
