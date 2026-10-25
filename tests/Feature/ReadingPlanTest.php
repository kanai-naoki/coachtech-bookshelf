<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\ReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 読書計画CRUD・読了処理の機能テスト
 */
class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * ログインユーザーの読書計画を作成する。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = [], ?User $owner = null): ReadingPlan
    {
        return ReadingPlan::factory()->create($attributes + ['user_id' => ($owner ?? $this->user)->id]);
    }

    private function future(int $days = 10): string
    {
        return now()->addDays($days)->toDateString();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ==================== 1. アクセス制御 ====================

    public function test_未認証は全ルートでログイン画面へリダイレクトされる(): void
    {
        $plan = $this->plan();

        $this->get(route('reading-plans.index'))->assertRedirect('/login');
        $this->get(route('reading-plans.create'))->assertRedirect('/login');
        $this->post(route('reading-plans.store'), [])->assertRedirect('/login');
        $this->get(route('reading-plans.edit', $plan))->assertRedirect('/login');
        $this->put(route('reading-plans.update', $plan), [])->assertRedirect('/login');
        $this->delete(route('reading-plans.destroy', $plan))->assertRedirect('/login');
        $this->post(route('reading-plans.complete', $plan))->assertRedirect('/login');

        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id]);
    }

    public function test_他人の計画の編集画面は403(): void
    {
        $plan = $this->plan([], User::factory()->create());

        $this->actingAs($this->user)->get(route('reading-plans.edit', $plan))->assertForbidden();
    }

    public function test_他人の計画の更新は403で変更されない(): void
    {
        $plan = $this->plan(['target_date' => $this->future(5)], User::factory()->create());

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => $this->future(30)])
            ->assertForbidden();

        $this->assertSame($this->future(5), $plan->fresh()->target_date->toDateString());
    }

    public function test_他人の計画の削除は403で残る(): void
    {
        $plan = $this->plan([], User::factory()->create());

        $this->actingAs($this->user)->delete(route('reading-plans.destroy', $plan))->assertForbidden();

        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id]);
    }

    public function test_他人の計画の読了は403で変更されない(): void
    {
        $plan = $this->plan([], User::factory()->create());

        $this->actingAs($this->user)->post(route('reading-plans.complete', $plan))->assertForbidden();

        $this->assertSame(ReadingPlanStatus::InProgress, $plan->fresh()->status);
        $this->assertNull($plan->fresh()->completed_at);
    }

    public function test_存在しない計画は404(): void
    {
        $this->actingAs($this->user)->get(route('reading-plans.edit', 99999))->assertNotFound();
    }

    // ==================== 2. 一覧・絞り込み ====================

    public function test_一覧には自分の計画のみ表示される(): void
    {
        $mine = $this->plan(['book_id' => Book::factory()->create(['title' => '自分の本'])->id]);
        $this->plan(['book_id' => Book::factory()->create(['title' => '他人の本'])->id], User::factory()->create());

        $response = $this->actingAs($this->user)->get(route('reading-plans.index'))->assertOk();

        $response->assertSee('自分の本')->assertDontSee('他人の本');
        $this->assertSame([$mine->id], $response->viewData('readingPlans')->pluck('id')->all());
    }

    public function test_計画がなければ空メッセージが表示される(): void
    {
        $this->actingAs($this->user)
            ->get(route('reading-plans.index'))
            ->assertOk()
            ->assertSee('該当する読書計画はありません。');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statusProvider(): array
    {
        return [
            '進行中' => ['in_progress'],
            '完了' => ['completed'],
            '期限切れ' => ['expired'],
        ];
    }

    /**
     * @dataProvider statusProvider
     */
    public function test_ステータスで絞り込める(string $status): void
    {
        $inProgress = $this->plan();
        $completed = $this->plan([], $this->user);
        $completed->update(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);
        $expired = $this->plan();
        $expired->update(['status' => ReadingPlanStatus::Expired]);
        $this->plan(['status' => ReadingPlanStatus::from($status)], User::factory()->create());

        $expectedId = ['in_progress' => $inProgress, 'completed' => $completed, 'expired' => $expired][$status]->id;

        $response = $this->actingAs($this->user)
            ->get(route('reading-plans.index', ['status' => $status]))
            ->assertOk();

        $this->assertSame([$expectedId], $response->viewData('readingPlans')->pluck('id')->all());
        $this->assertSame($status, $response->viewData('currentStatus'));
    }

    public function test_ステータス指定なしは全ステータスが表示される(): void
    {
        $this->plan();
        $this->plan(['status' => ReadingPlanStatus::Expired]);
        $this->plan(['status' => ReadingPlanStatus::Completed]);

        $response = $this->actingAs($this->user)->get(route('reading-plans.index'));

        $this->assertCount(3, $response->viewData('readingPlans'));
    }

    public function test_存在しないステータス指定は0件になる(): void
    {
        $this->plan();

        $response = $this->actingAs($this->user)
            ->get(route('reading-plans.index', ['status' => 'unknown']))
            ->assertOk()
            ->assertSee('該当する読書計画はありません。');

        $this->assertCount(0, $response->viewData('readingPlans'));
    }

    // ==================== 3. 新規作成 ====================

    public function test_登録画面が表示され書籍が選択肢に出る(): void
    {
        Book::factory()->create(['title' => '選択肢の本']);

        $this->actingAs($this->user)
            ->get(route('reading-plans.create'))
            ->assertOk()
            ->assertSee('新規読書計画作成')
            ->assertSee('選択肢の本');
    }

    public function test_正常データで登録され一覧へリダイレクトしメッセージが表示される(): void
    {
        $book = Book::factory()->create(['title' => '登録する本']);
        $date = $this->future(7);

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => $date])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を作成しました。');

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $this->user->id,
            'book_id' => $book->id,
            'status' => 'in_progress',
        ]);
        $this->assertSame($date, ReadingPlan::first()->target_date->toDateString());
        $this->assertNull(ReadingPlan::first()->completed_at);

        $this->actingAs($this->user)
            ->followingRedirects()
            ->post(route('reading-plans.store'), ['book_id' => Book::factory()->create()->id, 'target_date' => $date])
            ->assertSee('読書計画を作成しました。');
    }

    public function test_期日が今日なら登録できる(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => now()->toDateString()])
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseCount('reading_plans', 1);
    }

    public function test_登録時にuser_idを偽装しても自分の計画になる(): void
    {
        $book = Book::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($this->user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => $this->future(),
            'user_id' => $other->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $this->user->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_書籍未選択はエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => '', 'target_date' => $this->future()])
            ->assertSessionHasErrors(['book_id' => '書籍を選択してください。']);

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_存在しない書籍はエラー(): void
    {
        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => 99999, 'target_date' => $this->future()])
            ->assertSessionHasErrors(['book_id' => '選択された書籍は存在しません。']);
    }

    public function test_目標日未入力はエラー(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $book->id])
            ->assertSessionHasErrors(['target_date' => '期日は必須です。']);

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_目標日が日付形式でなければエラー(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => 'not-a-date'])
            ->assertSessionHasErrors(['target_date' => '期日は有効な日付形式で入力してください。']);
    }

    public function test_過去日付はエラー(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['target_date' => '期日は今日以降の日付を指定してください。']);

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_進行中の同じ書籍の重複登録はエラー(): void
    {
        $book = Book::factory()->create();
        $this->plan(['book_id' => $book->id]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => $this->future()])
            ->assertSessionHasErrors(['book_id' => 'この書籍は既に進行中の読書計画が存在します。']);

        $this->assertDatabaseCount('reading_plans', 1);
    }

    /**
     * @return array<string, array{ReadingPlanStatus, bool}>
     */
    public static function duplicateRuleProvider(): array
    {
        return [
            '進行中は重複として弾かれる' => [ReadingPlanStatus::InProgress, false],
            '読了済みは重複扱いにならない' => [ReadingPlanStatus::Completed, true],
            '期限切れは重複扱いにならない' => [ReadingPlanStatus::Expired, true],
        ];
    }

    /**
     * 重複チェック（status絞り込み）を、保存を伴わずバリデーションルール単体で検証する。
     *
     * @dataProvider duplicateRuleProvider
     */
    public function test_重複チェックは進行中の計画のみを対象にする(ReadingPlanStatus $existing, bool $passes): void
    {
        $book = Book::factory()->create();
        $this->plan(['book_id' => $book->id, 'status' => $existing]);

        $request = ReadingPlanRequest::create('/reading-plans', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $validator = Validator::make(
            ['book_id' => $book->id, 'target_date' => $this->future()],
            $request->rules(),
            $request->messages()
        );

        $this->assertSame($passes, $validator->passes());
        if (! $passes) {
            $this->assertSame(
                'この書籍は既に進行中の読書計画が存在します。',
                $validator->errors()->first('book_id')
            );
        }
    }

    public function test_他ユーザーの進行中計画は重複扱いにならない(): void
    {
        $book = Book::factory()->create();
        $this->plan(['book_id' => $book->id], User::factory()->create());

        $request = ReadingPlanRequest::create('/reading-plans', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue(Validator::make(
            ['book_id' => $book->id, 'target_date' => $this->future()],
            $request->rules()
        )->passes());
    }

    public function test_他ユーザーが登録済みの書籍でも自分は登録できる(): void
    {
        $book = Book::factory()->create();
        $this->plan(['book_id' => $book->id], User::factory()->create());

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => $this->future()])
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseCount('reading_plans', 2);
    }

    // ==================== 4. 更新・編集 ====================

    public function test_編集画面に既存の目標日が初期値として表示される(): void
    {
        $book = Book::factory()->create(['title' => '編集対象の本']);
        $plan = $this->plan(['book_id' => $book->id, 'target_date' => '2099-12-31']);

        $this->actingAs($this->user)
            ->get(route('reading-plans.edit', $plan))
            ->assertOk()
            ->assertSee('読書計画編集')
            ->assertSee('編集対象の本')
            ->assertSee('value="2099-12-31"', false)
            ->assertSee('進行中');
    }

    public function test_進行中の計画の目標日を更新できる(): void
    {
        $plan = $this->plan();
        $date = $this->future(40);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => $date])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を更新しました。');

        $fresh = $plan->fresh();
        $this->assertSame($date, $fresh->target_date->toDateString());
        $this->assertSame(ReadingPlanStatus::InProgress, $fresh->status);
    }

    public function test_期限切れの計画を未来日に更新すると進行中に復帰する(): void
    {
        $plan = $this->plan(['target_date' => now()->subDays(3)->toDateString(), 'status' => ReadingPlanStatus::Expired]);
        $date = $this->future(3);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => $date]);

        $fresh = $plan->fresh();
        $this->assertSame(ReadingPlanStatus::InProgress, $fresh->status);
        $this->assertSame($date, $fresh->target_date->toDateString());
    }

    public function test_期限切れの計画を今日の日付に更新しても期限切れのまま(): void
    {
        $plan = $this->plan(['target_date' => now()->subDays(3)->toDateString(), 'status' => ReadingPlanStatus::Expired]);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => now()->toDateString()])
            ->assertRedirect(route('reading-plans.index'));

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
    }

    public function test_読了済みの計画は目標日を更新してもステータスが変わらない(): void
    {
        $plan = $this->plan(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => $this->future(60)]);

        $this->assertSame(ReadingPlanStatus::Completed, $plan->fresh()->status);
    }

    public function test_更新で書籍は変更されない(): void
    {
        $plan = $this->plan();
        $originalBookId = $plan->book_id;

        $this->actingAs($this->user)->put(route('reading-plans.update', $plan), [
            'book_id' => Book::factory()->create()->id,
            'target_date' => $this->future(20),
        ]);

        $this->assertSame($originalBookId, $plan->fresh()->book_id);
    }

    public function test_更新時の過去日付はエラーで変更されない(): void
    {
        $plan = $this->plan(['target_date' => $this->future(5)]);

        $this->actingAs($this->user)
            ->from(route('reading-plans.edit', $plan))
            ->put(route('reading-plans.update', $plan), ['target_date' => now()->subDay()->toDateString()])
            ->assertRedirect(route('reading-plans.edit', $plan))
            ->assertSessionHasErrors('target_date');

        $this->assertSame($this->future(5), $plan->fresh()->target_date->toDateString());
    }

    public function test_更新時の目標日未入力はエラー(): void
    {
        $plan = $this->plan();

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), [])
            ->assertSessionHasErrors('target_date');
    }

    // ==================== 5. 読了処理 ====================

    public function test_読了するとステータスが完了になり読了日時が記録される(): void
    {
        Carbon::setTestNow('2030-05-01 10:30:00');
        $plan = $this->plan(['target_date' => '2030-05-10']);

        $this->actingAs($this->user)
            ->post(route('reading-plans.complete', $plan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画機能を完了しました。');

        $fresh = $plan->fresh();
        $this->assertSame(ReadingPlanStatus::Completed, $fresh->status);
        $this->assertTrue($fresh->completed_at->equalTo(Carbon::parse('2030-05-01 10:30:00')));
    }

    public function test_期限切れの計画も読了にできる(): void
    {
        $plan = $this->plan(['status' => ReadingPlanStatus::Expired]);

        $this->actingAs($this->user)->post(route('reading-plans.complete', $plan));

        $this->assertSame(ReadingPlanStatus::Completed, $plan->fresh()->status);
        $this->assertNotNull($plan->fresh()->completed_at);
    }

    public function test_読了後の一覧には完了日と完了ステータスが表示され編集リンクは出ない(): void
    {
        $plan = $this->plan(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);

        $this->actingAs($this->user)
            ->get(route('reading-plans.index'))
            ->assertSee('完了')
            ->assertSee(now()->format('Y-m-d'))
            ->assertDontSee('読了する')
            ->assertDontSee(route('reading-plans.edit', $plan), false);
    }

    // ==================== 6. 削除 ====================

    public function test_削除すると_d_bから消え一覧へリダイレクトする(): void
    {
        $plan = $this->plan();
        $other = $this->plan();

        $this->actingAs($this->user)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を削除しました。');

        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
        $this->assertDatabaseHas('reading_plans', ['id' => $other->id]);
    }

    public function test_削除すると関連するリマインダー通知も同時に削除される(): void
    {
        $plan = $this->plan();
        $otherPlan = $this->plan();
        $this->user->notify(new ReadingPlanReminder($plan, 'three_days_before'));
        $this->user->notify(new ReadingPlanReminder($plan, 'on_due_date'));
        $this->user->notify(new ReadingPlanReminder($otherPlan, 'on_due_date'));
        $this->assertSame(3, $this->user->notifications()->count());

        $this->actingAs($this->user)->delete(route('reading-plans.destroy', $plan))->assertRedirect();

        $remaining = $this->user->notifications()->get();
        $this->assertCount(1, $remaining);
        $this->assertSame($otherPlan->id, $remaining->first()->data['reading_plan_id']);
    }

    public function test_削除しても他ユーザーの通知は残る(): void
    {
        $plan = $this->plan();
        $other = User::factory()->create();
        $otherPlan = $this->plan([], $other);
        $other->notify(new ReadingPlanReminder($otherPlan, 'on_due_date'));

        $this->actingAs($this->user)->delete(route('reading-plans.destroy', $plan));

        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_書籍を削除すると読書計画もカスケード削除される(): void
    {
        $plan = $this->plan();

        $plan->book->delete();

        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }
}
