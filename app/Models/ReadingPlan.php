<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Notifications\ReadingPlanReminder;

/**
 * 読書計画モデル（1ユーザー1書籍につき1件）。
 *
 * @property int $id
 * @property int $user_id
 * @property int $book_id
 * @property \Illuminate\Support\Carbon $target_date 読了目標日
 * @property \Illuminate\Support\Carbon|null $completed_at 読了日時
 * @property ReadingPlanStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ReadingPlan extends Model
{
    use HasFactory;

    /**
     * 一括代入を許可する属性。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'target_date',
        'completed_at',
        'status',
    ];

    /**
     * 属性のキャスト定義。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'target_date' => 'date',
        'completed_at' => 'datetime',
        'status' => ReadingPlanStatus::class,
    ];

    /**
     * 計画の所有ユーザー。
     *
     * @return BelongsTo<User, ReadingPlan>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 計画の対象書籍。
     *
     * @return BelongsTo<Book, ReadingPlan>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * この計画に関するリマインダー通知（所有者宛て）。
     *
     * @return MorphMany<\Illuminate\Notifications\DatabaseNotification>
     */
    public function reminderNotifications(): MorphMany
    {
        return $this->user->notifications()
            ->where('type', ReadingPlanReminder::class)
            ->where('data->reading_plan_id', $this->id);
    }

    /**
     * ステータスで絞り込む。空なら何もしない。
     *
     * @param Builder<ReadingPlan> $query
     * @param string|null $status ステータス値（in_progress / completed / expired）
     * @return Builder<ReadingPlan>
     */
    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        return $query->when(filled($status), fn (Builder $q) => $q->where('status', $status));
    }

    /**
     * 期限日が指定日の計画に絞り込む。
     *
     * @param Builder<ReadingPlan> $query
     * @param string $date Y-m-d 形式の日付
     * @return Builder<ReadingPlan>
     */
    public function scopeDueOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('target_date', $date);
    }

    /**
     * 期限を過ぎても進行中のままの計画（自動失効の対象）に絞り込む。
     *
     * @param Builder<ReadingPlan> $query
     * @return Builder<ReadingPlan>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::InProgress->value)
            ->whereDate('target_date', '<', now()->toDateString());
    }
}
