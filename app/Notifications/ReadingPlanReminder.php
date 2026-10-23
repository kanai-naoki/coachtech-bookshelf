<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    use Queueable;

    protected $plan;
    protected string $reminderType;

    /**
     * Create a new notification instance.
     *
     * @param mixed $plan 読書計画モデル
     * @param string $timing タイミング識別子 ('three_days_before', 'due_date', etc.)
     */
    public function __construct($plan, string $timing = 'three_days_before')
    {
        $this->plan = $plan;
        $this->reminderType = $timing;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // データベース（notifications テーブル）に保存する設定
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $bookTitle = $this->plan->book->title ?? '書籍';

        // タイミングに応じた文言の出し分け
        $body = match ($this->reminderType) { // プロパティ名は実際のコードに合わせて調整してください
            'three_days_before' => "読書計画「{$bookTitle}」の期限は3日後です。",
            'on_due_date' => "読書計画「{$bookTitle}」の期限は本日です。",
            'three_days_after' => "読書計画「{$bookTitle}」の期限が過ぎています。",
            default => "読書計画「{$bookTitle}」の期限が近づいています。",
        };

        return [
            'title' => '読書計画のリマインダー',
            'body' => $body,
            'timing' => $this->reminderType,
        ];
    }
}