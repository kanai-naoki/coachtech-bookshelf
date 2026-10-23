<?php

namespace Tests\Feature;

use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 通知一覧・既読化の機能テスト
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 指定ユーザー宛てのリマインダー通知を作成する。
     */
    private function notify(User $user, string $timing = 'on_due_date', string $bookTitle = '通知対象の本'): DatabaseNotification
    {
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);
        $plan->book->update(['title' => $bookTitle]);
        $existingIds = $user->notifications()->pluck('id');

        $user->notify(new ReadingPlanReminder($plan->fresh(), $timing));

        return $user->notifications()->whereNotIn('id', $existingIds)->firstOrFail();
    }

    // ==================== 認証 ====================

    public function test_未認証は一覧も既読化もログイン画面へリダイレクトされる(): void
    {
        $notification = $this->notify($this->user);

        $this->get(route('notifications.index'))->assertRedirect('/login');
        $this->post(route('notifications.read', $notification->id))->assertRedirect('/login');

        $this->assertNull($notification->fresh()->read_at);
    }

    // ==================== 一覧 ====================

    public function test_自分宛ての通知がタイトルと本文と未読状態つきで表示される(): void
    {
        $this->notify($this->user, 'three_days_before', '表示確認の本');

        $response = $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewIs('notifications.index')
            ->assertSee('通知一覧')
            ->assertSee('読書計画のリマインダー')
            ->assertSee('読書計画「表示確認の本」の期限は3日後です。')
            ->assertSee('未読')
            ->assertSee('既読にする');

        $notification = $response->viewData('notifications')->first();
        $this->assertSame('three_days_before', $notification->data['timing']);
        $this->assertNull($notification->read_at);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function timingStyleProvider(): array
    {
        return [
            '3日前' => ['three_days_before', 'bg-blue-500', '期限は3日後です'],
            '当日' => ['on_due_date', 'bg-yellow-500', '期限は本日です'],
            '超過' => ['three_days_after', 'bg-red-500', '期限が過ぎています'],
        ];
    }

    /**
     * @dataProvider timingStyleProvider
     */
    public function test_タイミングごとに本文と表示スタイルが切り替わる(string $timing, string $style, string $body): void
    {
        $this->notify($this->user, $timing);

        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertSee($body)
            ->assertSee($style);
    }

    public function test_他人宛ての通知は一覧に混入しない(): void
    {
        $other = User::factory()->create();
        $this->notify($this->user, 'on_due_date', '自分の本');
        $this->notify($other, 'on_due_date', '他人の本');

        $response = $this->actingAs($this->user)->get(route('notifications.index'))->assertOk();

        $response->assertSee('自分の本')->assertDontSee('他人の本');
        $this->assertCount(1, $response->viewData('notifications'));
    }

    public function test_既読の通知は未読表示と既読ボタンが出ない(): void
    {
        $notification = $this->notify($this->user);
        $notification->markAsRead();

        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('読書計画のリマインダー')
            ->assertDontSee('未読')
            ->assertDontSee('既読にする');
    }

    public function test_通知がなければ空メッセージが表示される(): void
    {
        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('通知はありません。');
    }

    public function test_複数の通知は新しい順に並ぶ(): void
    {
        Carbon::setTestNow('2030-01-01 10:00:00');
        $old = $this->notify($this->user, 'on_due_date', '古い通知');
        Carbon::setTestNow('2030-01-02 10:00:00');
        $new = $this->notify($this->user, 'on_due_date', '新しい通知');

        $ids = $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->viewData('notifications')
            ->pluck('id')
            ->all();

        $this->assertSame([$new->id, $old->id], $ids);
    }

    // ==================== 既読化 ====================

    public function test_既読化でread_atに現在日時が記録されリダイレクトとメッセージが返る(): void
    {
        $notification = $this->notify($this->user);
        Carbon::setTestNow('2030-03-04 12:34:56');

        $this->actingAs($this->user)
            ->from(route('notifications.index'))
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', '通知を既読にしました。');

        $fresh = $notification->fresh();
        $this->assertNotNull($fresh->read_at);
        $this->assertTrue($fresh->read_at->equalTo(Carbon::parse('2030-03-04 12:34:56')));
        $this->assertSame(0, $this->user->unreadNotifications()->count());
        $this->assertSame(1, $this->user->readNotifications()->count());
    }

    public function test_既読化後の一覧にフラッシュメッセージが表示され未読表示が消える(): void
    {
        $notification = $this->notify($this->user);

        $this->actingAs($this->user)
            ->from(route('notifications.index'))
            ->followingRedirects()
            ->post(route('notifications.read', $notification->id))
            ->assertOk()
            ->assertSee('通知を既読にしました。')
            ->assertDontSee('既読にする');
    }

    public function test_既読化は対象の通知だけを更新する(): void
    {
        $target = $this->notify($this->user, 'on_due_date', '対象');
        $another = $this->notify($this->user, 'three_days_before', '別の通知');

        $this->actingAs($this->user)->post(route('notifications.read', $target->id));

        $this->assertNotNull($target->fresh()->read_at);
        $this->assertNull($another->fresh()->read_at);
    }

    public function test_既読済みの通知を再度既読化しても日時は変わらない(): void
    {
        $notification = $this->notify($this->user);
        Carbon::setTestNow('2030-01-01 00:00:00');
        $notification->markAsRead();
        Carbon::setTestNow('2030-02-02 00:00:00');

        $this->actingAs($this->user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertTrue($notification->fresh()->read_at->equalTo(Carbon::parse('2030-01-01 00:00:00')));
    }

    public function test_他人の通知は既読化できず404(): void
    {
        $notification = $this->notify(User::factory()->create());

        $this->actingAs($this->user)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_存在しない通知IDは404(): void
    {
        $this->actingAs($this->user)
            ->post(route('notifications.read', (string) Str::uuid()))
            ->assertNotFound();
    }
}
