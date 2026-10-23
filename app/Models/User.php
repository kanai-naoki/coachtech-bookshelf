<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * ユーザーモデル（Fortify / Sanctum 認証対象）。
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * このユーザーが登録したジャンル一覧。
     *
     * @return HasMany<Genre>
     */
    public function genres(): HasMany
    {
        return $this->hasMany(Genre::class);
    }

    /**
     * このユーザーが登録した書籍一覧。
     *
     * @return HasMany<Book>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * このユーザーが投稿したレビュー一覧。
     *
     * @return HasMany<Review>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * このユーザーがお気に入り登録した書籍（中間テーブル: favorites）。
     *
     * @return BelongsToMany<Book>
     */
    public function favoriteBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'favorites')->using(Favorite::class);
    }

    /**
     * このユーザーが「いいね」したレビュー（中間テーブル: review_likes）。
     *
     * @return BelongsToMany<Review>
     */
    public function likedReviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_likes')->using(ReviewLike::class);
    }

    /**
     * このユーザーの読書計画一覧。
     *
     * @return HasMany<ReadingPlan>
     */
    public function readingPlans(): HasMany
    {
        return $this->hasMany(ReadingPlan::class);
    }
}
