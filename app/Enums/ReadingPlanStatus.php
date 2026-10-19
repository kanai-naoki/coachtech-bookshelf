<?php

namespace App\Enums;

enum ReadingPlanStatus: string
{
    case Unread = 'unread';
    case Completed = 'completed';

    /**
     * 日本語ラベルを返す（ビュー表示用）
     */
    public function label(): string
    {
        return match ($this) {
            self::Unread => '未読',
            self::Completed => '読了',
        };
    }

    /**
     * バッジ用のTailwind CSSクラスを返す（ビュー表示用）
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Unread => 'bg-yellow-100 text-yellow-800',
            self::Completed => 'bg-green-100 text-green-800',
        };
    }
}