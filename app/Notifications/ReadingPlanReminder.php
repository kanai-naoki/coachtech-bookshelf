<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
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
     * @param  ReadingPlan  $plan  読書計画モデル
     * @param  string  $timing  タイミング識別子 ('three_days_before', 'on_due_date', 'three_days_after')
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

        // タイトルとボディをタイミングごとにセットで取得
        $reminderData = match ($this->reminderType) {
            'three_days_before' => [
                'title' => '読書計画リマインド — 期限まであと3日',
                'body' => "「{$bookTitle}」の期限まで残り3日です。引き続き読書を進めましょう。",
            ],
            'on_due_date' => [
                'title' => '読書計画 — 本日が期限',
                'body' => "「{$bookTitle}」は本日が期限です。読了済みなら完了登録を、もう少し必要なら期限を変更してください。",
            ],
            'three_days_after' => [
                'title' => '読書計画 — 期限超過3日経過',
                'body' => "「{$bookTitle}」の期限から3日が経過しました。読了済みなら完了登録を、続けるなら期限を変更してください。",
            ],
        };

        return [
            'title' => $reminderData['title'],
            'body' => $reminderData['body'],
            'timing' => $this->reminderType,
            'reading_plan_id' => $this->plan->id,
        ];
    }
}
