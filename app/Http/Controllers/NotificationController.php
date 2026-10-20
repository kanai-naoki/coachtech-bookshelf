<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * ログインユーザーの通知一覧を表示する
     */
    public function index()
    {
        // 認証中ユーザーの通知一覧を取得
        $notifications = Auth::user()->notifications;

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 指定された通知を既読にする
     */
    public function markAsRead($id)
    {
        // 認証中ユーザーの所有する通知から、指定されたIDのものを取得（不正アクセス防止）
        $notification = Auth::user()->notifications()->findOrFail($id);

        // Laravel標準の既読処理（read_at に現在日時をセット）
        $notification->markAsRead();

        return back()->with('success', '通知を既読にしました。');
    }
}
