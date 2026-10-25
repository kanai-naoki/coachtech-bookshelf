<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * ログインユーザーの通知一覧を表示する。
     *
     * @param  Request  $request  認証済みユーザーを含むリクエスト
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications;

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 指定された通知を既読にする（自分の通知のみ対象）。
     *
     * @param  Request  $request  認証済みユーザーを含むリクエスト
     * @param  string  $id  通知ID
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return back()->with('success', '通知を既読にしました。');
    }
}
