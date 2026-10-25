<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\ReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * 読書計画一覧を表示（ステータスによる絞り込み対応）。
     *
     * @param  Request  $request  status クエリを受け取るリクエスト
     */
    public function index(Request $request): View
    {
        $currentStatus = $request->query('status');

        $readingPlans = $request->user()->readingPlans()
            ->with('book')
            ->ofStatus($currentStatus)
            ->latest()
            ->get();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * 読書計画の新規作成画面を表示。
     */
    public function create(): View
    {
        $books = Book::all();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画を新規保存（進行中ステータスで登録）。
     *
     * @param  ReadingPlanRequest  $request  検証済みの読書計画入力
     */
    public function store(ReadingPlanRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $request->user()->readingPlans()->create(
                $request->safe()->only(['book_id', 'target_date']) + ['status' => ReadingPlanStatus::InProgress]
            );
        });

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を作成しました。');
    }

    /**
     * 読書計画の編集画面を表示（作成者本人のみ）。
     *
     * @param  ReadingPlan  $readingPlan  編集対象の読書計画
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 読書計画の期限を更新（作成者本人のみ）。
     * 期限切れの計画の期限を未来日に変更した場合は、進行中に戻す。
     *
     * @param  ReadingPlanRequest  $request  検証済みの読書計画入力
     * @param  ReadingPlan  $readingPlan  更新対象の読書計画
     */
    public function update(ReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $targetDate = $request->validated('target_date');
        $revives = $readingPlan->status === ReadingPlanStatus::Expired
            && Carbon::parse($targetDate)->isFuture();

        DB::transaction(function () use ($readingPlan, $targetDate, $revives): void {
            $readingPlan->update(
                ['target_date' => $targetDate] + ($revives ? ['status' => ReadingPlanStatus::InProgress] : [])
            );
        });

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 読書計画を削除（作成者本人のみ）。関連するリマインダー通知も同時に削除する。
     *
     * @param  ReadingPlan  $readingPlan  削除対象の読書計画
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        DB::transaction(function () use ($readingPlan): void {
            $readingPlan->reminderNotifications()->delete();
            $readingPlan->delete();
        });

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * 読書計画を「読了」に変更し、読了日時を記録する（作成者本人のみ）。
     *
     * @param  ReadingPlan  $readingPlan  読了にする読書計画
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        DB::transaction(function () use ($readingPlan): void {
            $readingPlan->update([
                'status' => ReadingPlanStatus::Completed,
                'completed_at' => now(),
            ]);
        });

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画機能を完了しました。');
    }
}
