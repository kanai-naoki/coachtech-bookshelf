<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * 読書計画の期限リマインダー通知（databaseチャネル）。
 */
class ReadingPlanReminder extends Notification
{
    use Queueable;

    protected ReadingPlan $plan;
    protected string $reminderType;

    /**
     * 通知インスタンスを生成する。
     *
     * @param ReadingPlan $plan 読書計画モデル
     * @param string $timing タイミング識別子 ('three_days_before', 'on_due_date', 'three_days_after')
     */
    public function __construct(ReadingPlan $plan, string $timing = 'three_days_before')
    {
        $this->plan = $plan;
        $this->reminderType = $timing;
    }

    /**
     * 通知の配信チャネルを返す。
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * 通知の保存内容（title / body / timing / reading_plan_id）を返す。
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $bookTitle = $this->plan->book->title ?? '書籍';

        // タイミングに応じた文言の出し分け
        $body = match ($this->reminderType) {
            'three_days_before' => "読書計画「{$bookTitle}」の期限は3日後です。",
            'on_due_date' => "読書計画「{$bookTitle}」の期限は本日です。",
            'three_days_after' => "読書計画「{$bookTitle}」の期限が過ぎています。",
            default => "読書計画「{$bookTitle}」の期限が近づいています。",
        };

        return [
            'title' => '読書計画のリマインダー',
            'body' => $body,
            'timing' => $this->reminderType,
            'reading_plan_id' => $this->plan->id,
        ];
    }
}