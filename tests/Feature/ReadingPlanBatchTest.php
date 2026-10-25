<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 日次バッチ（リマインダー通知・自動失効）のコマンドテスト
 */
class ReadingPlanBatchTest extends TestCase
{
    use RefreshDatabase;

    private const REMINDER = 'reminders:send-reading-plans';

    private const EXPIRE = 'reading-plans:expire';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2030-06-15 20:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 今日から指定日数ずらした日付の読書計画を作成する（負数は過去）。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function plan(int $offsetDays, ReadingPlanStatus $status = ReadingPlanStatus::InProgress, array $attributes = []): ReadingPlan
    {
        return ReadingPlan::factory()->create($attributes + [
            'target_date' => now()->addDays($offsetDays)->toDateString(),
            'status' => $status,
            'completed_at' => $status === ReadingPlanStatus::Completed ? now()->subDay() : null,
        ]);
    }

    private function runReminders(): void
    {
        $this->artisan(self::REMINDER)->assertExitCode(0);
    }

    private function notificationFor(ReadingPlan $plan): ?DatabaseNotification
    {
        return DatabaseNotification::query()
            ->where('notifiable_id', $plan->user_id)
            ->where('data->reading_plan_id', $plan->id)
            ->first();
    }

    // ==================== リマインダー通知 ====================

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function reminderTimingProvider(): array
    {
        return [
            '3日前' => [3, 'three_days_before', '期限は3日後です'],
            '当日' => [0, 'on_due_date', '期限は本日です'],
            '3日超過' => [-3, 'three_days_after', '期限が過ぎています'],
        ];
    }

    /**
     * @dataProvider reminderTimingProvider
     */
    public function test_進行中の計画にタイミングに応じた通知が作成される(int $offset, string $timing, string $bodyPart): void
    {
        $plan = $this->plan($offset);

        $this->runReminders();

        $notification = $this->notificationFor($plan);
        $this->assertNotNull($notification);
        $this->assertSame(ReadingPlanReminder::class, $notification->type);
        $this->assertSame(User::class, $notification->notifiable_type);
        $this->assertNull($notification->read_at);
        $this->assertSame('読書計画のリマインダー', $notification->data['title']);
        $this->assertSame($timing, $notification->data['timing']);
        $this->assertStringContainsString($bodyPart, $notification->data['body']);
        $this->assertStringContainsString($plan->book->title, $notification->data['body']);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_3つのタイミングの計画が同時にあれば所有者へそれぞれ通知される(): void
    {
        $before = $this->plan(3);
        $today = $this->plan(0);
        $after = $this->plan(-3);

        $this->artisan(self::REMINDER)
            ->expectsOutput('読書計画のリマインダー通知を 3 件送信しました。')
            ->assertExitCode(0);

        $this->assertSame('three_days_before', $this->notificationFor($before)->data['timing']);
        $this->assertSame('on_due_date', $this->notificationFor($today)->data['timing']);
        $this->assertSame('three_days_after', $this->notificationFor($after)->data['timing']);
    }

    public function test_期限3日超過の期限切れ計画にも超過通知が送られる(): void
    {
        $plan = $this->plan(-3, ReadingPlanStatus::Expired);

        $this->runReminders();

        $this->assertSame('three_days_after', $this->notificationFor($plan)->data['timing']);
    }

    public function test_通知は計画の所有者にだけ届く(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->plan(3, ReadingPlanStatus::InProgress, ['user_id' => $alice->id]);
        $this->plan(0, ReadingPlanStatus::InProgress, ['user_id' => $bob->id]);
        $this->plan(0, ReadingPlanStatus::InProgress, ['user_id' => $bob->id, 'book_id' => Book::factory()->create()->id]);

        $this->runReminders();

        $this->assertSame(1, $alice->notifications()->count());
        $this->assertSame(2, $bob->notifications()->count());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonTargetOffsetProvider(): array
    {
        return [
            '7日後' => [7],
            '4日後' => [4],
            '2日後' => [2],
            '1日後' => [1],
            '1日前' => [-1],
            '2日前' => [-2],
            '4日前' => [-4],
            '7日前' => [-7],
        ];
    }

    /**
     * @dataProvider nonTargetOffsetProvider
     */
    public function test_対象日以外の進行中計画には通知されない(int $offset): void
    {
        $this->plan($offset);

        $this->artisan(self::REMINDER)
            ->expectsOutput('読書計画のリマインダー通知を 0 件送信しました。')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 0);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function targetOffsetProvider(): array
    {
        return ['3日前' => [3], '当日' => [0], '3日超過' => [-3]];
    }

    /**
     * @dataProvider targetOffsetProvider
     */
    public function test_完了済みの計画には一切通知されない(int $offset): void
    {
        $this->plan($offset, ReadingPlanStatus::Completed);

        $this->runReminders();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_期限切れの計画は3日前や当日の通知対象にならない(): void
    {
        $this->plan(3, ReadingPlanStatus::Expired);
        $this->plan(0, ReadingPlanStatus::Expired);

        $this->runReminders();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_計画が1件もなくても正常終了する(): void
    {
        $this->artisan(self::REMINDER)
            ->expectsOutput('読書計画のリマインダー通知を 0 件送信しました。')
            ->assertExitCode(0);
    }

    public function test_リマインダーは通知のみで計画のステータスを変更しない(): void
    {
        $plan = $this->plan(-3);

        $this->runReminders();

        $this->assertSame(ReadingPlanStatus::InProgress, $plan->fresh()->status);
    }

    // ==================== 自動失効 ====================

    public function test_期限を過ぎた進行中の計画が期限切れに更新される(): void
    {
        $yesterday = $this->plan(-1);
        $threeDays = $this->plan(-3);
        $tenDays = $this->plan(-10);

        $this->artisan(self::EXPIRE)
            ->expectsOutput('3 件の読書計画を期限切れにしました。')
            ->assertExitCode(0);

        foreach ([$yesterday, $threeDays, $tenDays] as $plan) {
            $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
        }
    }

    public function test_期限が今日と未来の進行中計画は変更されない(): void
    {
        $today = $this->plan(0);
        $tomorrow = $this->plan(1);
        $later = $this->plan(30);

        $this->artisan(self::EXPIRE)
            ->expectsOutput('0 件の読書計画を期限切れにしました。')
            ->assertExitCode(0);

        foreach ([$today, $tomorrow, $later] as $plan) {
            $this->assertSame(ReadingPlanStatus::InProgress, $plan->fresh()->status);
        }
    }

    public function test_期限を過ぎていても完了済みの計画は変更されない(): void
    {
        $completed = $this->plan(-5, ReadingPlanStatus::Completed);
        $completedAt = $completed->completed_at;

        $this->artisan(self::EXPIRE)->assertExitCode(0);

        $fresh = $completed->fresh();
        $this->assertSame(ReadingPlanStatus::Completed, $fresh->status);
        $this->assertTrue($fresh->completed_at->equalTo($completedAt));
    }

    public function test_すでに期限切れの計画はそのまま(): void
    {
        $expired = $this->plan(-5, ReadingPlanStatus::Expired);

        $this->artisan(self::EXPIRE)
            ->expectsOutput('0 件の読書計画を期限切れにしました。')
            ->assertExitCode(0);

        $this->assertSame(ReadingPlanStatus::Expired, $expired->fresh()->status);
    }

    public function test_自動失効は通知を作成しない(): void
    {
        $this->plan(-3);

        $this->artisan(self::EXPIRE)->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_失効後に再実行しても結果は変わらない(): void
    {
        $plan = $this->plan(-2);

        $this->artisan(self::EXPIRE)->assertExitCode(0);
        $this->artisan(self::EXPIRE)->expectsOutput('0 件の読書計画を期限切れにしました。')->assertExitCode(0);

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
    }

    // ==================== 一連の日次処理 ====================

    public function test_3日超過の進行中計画は失効後に超過通知も1件だけ送られる(): void
    {
        $plan = $this->plan(-3);

        $this->artisan(self::EXPIRE)->assertExitCode(0);
        $this->runReminders();

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
        $this->assertSame(1, DatabaseNotification::where('data->reading_plan_id', $plan->id)->count());
        $this->assertSame('three_days_after', $this->notificationFor($plan)->data['timing']);
    }

    public function test_失効と通知を一括で行っても完了済みと対象外は変わらない(): void
    {
        $completed = $this->plan(-3, ReadingPlanStatus::Completed);
        $far = $this->plan(7);

        $this->artisan(self::EXPIRE)->assertExitCode(0);
        $this->runReminders();

        $this->assertSame(ReadingPlanStatus::Completed, $completed->fresh()->status);
        $this->assertSame(ReadingPlanStatus::InProgress, $far->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    // ==================== スケジュール ====================

    public function test_スケジューラに両バッチが登録されている(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain(self::REMINDER)
            ->expectsOutputToContain(self::EXPIRE)
            ->assertExitCode(0);
    }
}
