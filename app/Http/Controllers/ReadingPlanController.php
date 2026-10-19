<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Enums\ReadingPlanStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * 読書計画一覧を表示（ステータスによる絞り込み対応）
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $currentStatus = $request->query('status');

        $readingPlans = ReadingPlan::with('book')
            ->where('user_id', $request->user()->id)
            ->when($currentStatus, function ($query, $currentStatus) {
                return $query->where('status', $currentStatus);
            })
            ->latest()
            ->get();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * 読書計画の新規作成画面を表示
     *
     * @param Request $request
     * @return View
     */
    public function create(Request $request): View
    {
        // 登録用に対象となる書籍一覧を取得
        $books = Book::all();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画を新規保存
     *
     * @param ReadingPlanRequest $request
     * @return RedirectResponse
     */
    public function store(ReadingPlanRequest $request): RedirectResponse
    {

        ReadingPlan::create([
            'user_id' => $request->user()->id,
            'book_id' => $request->validated('book_id'),
            'target_date' => $request->validated('target_date'),
            'status' => ReadingPlanStatus::Unread,
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を作成しました。');
    }

    /**
     * 読書計画の編集画面を表示
     *
     * @param ReadingPlan $readingPlan
     * @return View
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 読書計画を更新
     *
     * @param ReadingPlanRequest $request
     * @param ReadingPlan $readingPlan
     * @return RedirectResponse
     */
    public function update(ReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->update([
            'target_date' => $request->validated('target_date'),
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 読書計画を削除
     *
     * @param ReadingPlan $readingPlan
     * @return RedirectResponse
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->delete();

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * 読書計画を「読了」に変更
     *
     * @param ReadingPlan $readingPlan
     * @return RedirectResponse
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読了に設定しました。');
    }
}
