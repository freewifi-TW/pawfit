<?php

namespace Tests\Feature;

use App\Models\Fursona;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 2 M2–M4：發文、NSFW 鎖定、雙軌河道與 cursor、讚與留言、檢舉自動降能見度與後台操作。 */
class FeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pawfit.features.friends' => true, 'pawfit.features.feed' => true]);
    }

    private function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function befriend(User $a, User $b): void
    {
        $req = $this->actingAs($a)->postJson('/api/friends/requests', ['pawfit_id' => $b->pawfit_id])->assertCreated()->json();
        $this->actingAs($b)->postJson("/api/friends/requests/{$req['friendship_id']}/accept")->assertOk();
    }

    /** @return array{0: User, 1: Fursona, 2: Media} */
    private function author(string $id, array $fursona = []): array
    {
        $u = User::factory()->create(['pawfit_id' => $id]);
        $f = Fursona::factory()->for($u, 'owner')->create($fursona + ['tags' => ['犬科', '赤狐']]);
        $m = Media::factory()->for($f)->create();

        return [$u, $f, $m];
    }

    private function createPost(User $u, array $body): array
    {
        return $this->actingAs($u)->postJson('/api/posts', $body)->assertCreated()->json();
    }

    public function test_feature_flag_gates_posts(): void
    {
        config(['pawfit.features.feed' => false]);
        [$u, , $m] = $this->author('alpha');
        $this->actingAs($u)->postJson('/api/posts', ['body' => 'x', 'media_ids' => [$m->id], 'visibility' => 'public', 'is_nsfw' => false])->assertNotFound();
        $this->asGuest()->getJson('/api/feed/explore')->assertNotFound();
    }

    public function test_create_post_inherits_tags_locks_nsfw_and_validates_media(): void
    {
        [$u, $f, $m] = $this->author('alpha');
        $other = Media::factory()->create();
        $processing = Media::factory()->for($f)->processing()->create();

        $this->actingAs($u)->postJson('/api/posts', ['body' => 'x', 'media_ids' => [], 'visibility' => 'public', 'is_nsfw' => false])->assertStatus(422);
        $this->actingAs($u)->postJson('/api/posts', ['body' => 'x', 'media_ids' => [$other->id], 'visibility' => 'public', 'is_nsfw' => false])->assertStatus(422)->assertJsonValidationErrors('media_ids');
        $this->actingAs($u)->postJson('/api/posts', ['body' => 'x', 'media_ids' => [$processing->id], 'visibility' => 'public', 'is_nsfw' => false])->assertStatus(422);
        $this->actingAs($u)->postJson('/api/posts', ['body' => 'x', 'media_ids' => [$m->id], 'visibility' => 'unlisted', 'is_nsfw' => false])->assertStatus(422);
        $this->actingAs($u)->postJson('/api/posts', ['body' => 'x', 'media_ids' => [$m->id], 'visibility' => 'public'])->assertStatus(422)->assertJsonValidationErrors('is_nsfw');

        $post = $this->createPost($u, [
            'body' => '  今天的篝火  ', 'media_ids' => [$m->id], 'fursona_id' => $f->id,
            'tags' => ['犬科', '赤狐', '篝火', ' 篝火 '], 'visibility' => 'public', 'is_nsfw' => false,
        ]);
        $this->assertSame('今天的篝火', $post['body']);
        $this->assertSame(['犬科', '赤狐', '篝火'], $post['tags']);
        $this->assertSame('alpha', $post['author']['pawfit_id']);
        $this->assertSame($f->name, $post['fursona']['name']);
        $this->assertCount(1, $post['media']);
        $this->assertStringContainsString("p={$post['id']}", $post['media'][0]['urls']['display']);
        $this->assertTrue($post['can_edit']);
        $this->assertFalse($post['is_nsfw']);

        // 來源圖 NSFW → 鎖定 NSFW，不可改回
        $nsfw = Media::factory()->for($f)->nsfw()->create();
        $locked = $this->createPost($u, ['body' => 'n', 'media_ids' => [$nsfw->id], 'visibility' => 'public', 'is_nsfw' => false]);
        $this->assertTrue($locked['is_nsfw']);
        $this->actingAs($u)->patchJson("/api/posts/{$locked['id']}", ['is_nsfw' => false])->assertStatus(422);
        $this->actingAs($u)->patchJson("/api/posts/{$locked['id']}", ['body' => 'edited', 'tags' => ['a']])->assertOk()->assertJsonPath('body', 'edited')->assertJsonPath('tags', ['a']);
        $this->actingAs($u)->patchJson("/api/posts/{$locked['id']}", ['media_ids' => [$m->id]])->assertStatus(422);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->patchJson("/api/posts/{$post['id']}", ['body' => 'hack'])->assertForbidden();
        $this->actingAs($stranger)->deleteJson("/api/posts/{$post['id']}")->assertForbidden();
        $this->actingAs($u)->deleteJson("/api/posts/{$post['id']}")->assertNoContent();
        $this->asGuest()->getJson("/api/posts/{$post['id']}")->assertNotFound();
    }

    public function test_feeds_visibility_nsfw_and_blocking(): void
    {
        [$a, $fa, $ma] = $this->author('alpha');
        [$b, $fb, $mb] = $this->author('bravo');
        [$c, , $mc] = $this->author('charlie');
        $this->befriend($a, $b);

        $pubA = $this->createPost($a, ['body' => 'A public', 'media_ids' => [$ma->id], 'visibility' => 'public', 'is_nsfw' => false, 'tags' => ['犬科']]);
        $friA = $this->createPost($a, ['body' => 'A friends', 'media_ids' => [$ma->id], 'visibility' => 'friends', 'is_nsfw' => false]);
        $nsfwA = $this->createPost($a, ['body' => 'A nsfw', 'media_ids' => [$ma->id], 'visibility' => 'public', 'is_nsfw' => true, 'tags' => ['犬科']]);
        $pubC = $this->createPost($c, ['body' => 'C public', 'media_ids' => [$mc->id], 'visibility' => 'public', 'is_nsfw' => false, 'tags' => ['龍']]);

        // 探索河道：訪客看不到 NSFW 與限好友；標籤 AND 篩選
        $explore = $this->asGuest()->getJson('/api/feed/explore')->assertOk()->json();
        $this->assertSame(['C public', 'A public'], array_column($explore['data'], 'body'));
        $this->assertSame(['A public'], array_column($this->asGuest()->getJson('/api/feed/explore?tags=犬科')->json('data'), 'body'));
        $this->assertSame([], $this->asGuest()->getJson('/api/feed/explore?tags=犬科,龍')->json('data'));

        // 偏好 blur 的登入者看到 NSFW（state=blur）
        $blur = User::factory()->adult('blur')->create();
        $rows = $this->actingAs($blur)->getJson('/api/feed/explore')->assertOk()->json('data');
        $this->assertSame(['C public', 'A nsfw', 'A public'], array_column($rows, 'body'));
        $this->assertSame('blur', $rows[1]['state']);
        $this->assertSame('show', $rows[2]['state']);

        // 好友河道：B 看到 A 的公開與限好友（含自己的貼文）；C 不是好友只看到自己的
        $this->assertSame(['A friends', 'A public'], array_column($this->actingAs($b)->getJson('/api/feed/friends')->assertOk()->json('data'), 'body'));
        $this->assertSame(['C public'], array_column($this->actingAs($c)->getJson('/api/feed/friends')->json('data'), 'body'));
        $this->asGuest()->getJson('/api/feed/friends')->assertUnauthorized();

        // 單篇：限好友對陌生人 404；圖片經 p= 出口
        $this->actingAs($b)->getJson("/api/posts/{$friA['id']}")->assertOk();
        $this->actingAs($c)->getJson("/api/posts/{$friA['id']}")->assertNotFound();
        $this->asGuest()->getJson("/api/posts/{$nsfwA['id']}")->assertNotFound();
        $this->actingAs($c)->get("/api/img/{$ma->id}?v=thumb&p={$friA['id']}")->assertNotFound();
        $this->actingAs($b)->get("/api/img/{$ma->id}?v=thumb&p={$friA['id']}")->assertRedirect();
        // 圖設為私人仍可經公開貼文看（作者發文即公開給觀眾）；不在貼文裡的圖不行
        $ma->forceFill(['visibility_override' => 'private'])->save();
        $this->asGuest()->get("/api/img/{$ma->id}?v=thumb&p={$pubA['id']}")->assertRedirect();
        $this->asGuest()->get("/api/img/{$mc->id}?v=thumb&p={$pubA['id']}")->assertNotFound();

        // 個人主頁貼文：好友多看到限好友；作者看到全部
        $this->assertCount(1, $this->asGuest()->getJson('/api/users/alpha/posts')->assertOk()->json('data'));
        $this->assertCount(2, $this->actingAs($b)->getJson('/api/users/alpha/posts')->json('data'));
        $this->assertCount(3, $this->actingAs($a)->getJson('/api/users/alpha/posts')->json('data'));

        // 封鎖：C 封鎖 A 後，雙方河道互不見
        $this->actingAs($c)->postJson("/api/blocks/{$a->id}")->assertCreated();
        $this->assertSame(['C public'], array_column($this->actingAs($c)->getJson('/api/feed/explore')->json('data'), 'body'));
        // 自己的貼文（含 NSFW）一律保留
        $this->assertSame(['A nsfw', 'A public'], array_column($this->actingAs($a)->getJson('/api/feed/explore')->json('data'), 'body'));
        $this->actingAs($a)->getJson("/api/posts/{$pubC['id']}")->assertNotFound();
    }

    public function test_cursor_pagination(): void
    {
        [$a, , $ma] = $this->author('alpha');
        foreach (range(1, 25) as $i) {
            $p = Post::create(['author_id' => $a->id, 'body' => "p{$i}", 'tags' => [], 'visibility' => 'public', 'is_nsfw' => false, 'status' => 'active']);
            $p->media()->attach($ma->id, ['sort' => 0]);
            $p->forceFill(['created_at' => now()->subMinutes(100 - $i)])->save();
        }
        $first = $this->asGuest()->getJson('/api/feed/explore')->assertOk()->json();
        $this->assertCount(20, $first['data']);
        $this->assertSame('p25', $first['data'][0]['body']);
        $this->assertNotNull($first['next_cursor']);
        $second = $this->asGuest()->getJson('/api/feed/explore?cursor='.$first['next_cursor'])->assertOk()->json();
        $this->assertCount(5, $second['data']);
        $this->assertSame('p5', $second['data'][0]['body']);
        $this->assertNull($second['next_cursor']);
        $this->asGuest()->getJson('/api/feed/explore?cursor=zzz')->assertStatus(422);
    }

    public function test_likes_and_comments(): void
    {
        [$a, , $ma] = $this->author('alpha');
        $b = User::factory()->create(['pawfit_id' => 'bravo']);
        $post = $this->createPost($a, ['body' => 'hi', 'media_ids' => [$ma->id], 'visibility' => 'public', 'is_nsfw' => false]);

        $this->actingAs($b)->postJson("/api/posts/{$post['id']}/like")->assertOk()->assertJsonPath('like_count', 1);
        $this->actingAs($b)->postJson("/api/posts/{$post['id']}/like")->assertOk()->assertJsonPath('like_count', 1);
        $this->actingAs($b)->getJson("/api/posts/{$post['id']}")->assertOk()->assertJsonPath('liked_by_me', true);
        $this->assertTrue($this->actingAs($b)->getJson('/api/feed/explore')->json('data.0.liked_by_me'));
        $this->actingAs($b)->deleteJson("/api/posts/{$post['id']}/like")->assertOk()->assertJsonPath('like_count', 0);
        $this->actingAs($b)->deleteJson("/api/posts/{$post['id']}/like")->assertOk()->assertJsonPath('like_count', 0);

        $this->actingAs($b)->postJson("/api/posts/{$post['id']}/comments", ['body' => str_repeat('x', 501)])->assertStatus(422);
        $c1 = $this->actingAs($b)->postJson("/api/posts/{$post['id']}/comments", ['body' => ' first '])->assertCreated()->assertJsonPath('body', 'first')->json();
        $c2 = $this->actingAs($a)->postJson("/api/posts/{$post['id']}/comments", ['body' => 'second'])->assertCreated()->json();
        $this->asGuest()->getJson("/api/posts/{$post['id']}/comments")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.body', 'first')->assertJsonPath('total', 2);
        $this->assertSame(2, $this->asGuest()->getJson("/api/posts/{$post['id']}")->json('comment_count'));

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->deleteJson("/api/comments/{$c1['id']}")->assertForbidden();
        // 貼文作者可刪別人的留言；留言者可刪自己的
        $this->actingAs($a)->deleteJson("/api/comments/{$c1['id']}")->assertNoContent();
        $this->actingAs($a)->deleteJson("/api/comments/{$c2['id']}")->assertNoContent();
        $this->asGuest()->getJson("/api/posts/{$post['id']}/comments")->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, Post::find($post['id'])->comment_count);

        // 看不到的貼文不能互動
        $friendsOnly = $this->createPost($a, ['body' => 'f', 'media_ids' => [$ma->id], 'visibility' => 'friends', 'is_nsfw' => false]);
        $this->actingAs($b)->postJson("/api/posts/{$friendsOnly['id']}/like")->assertNotFound();
        $this->actingAs($b)->postJson("/api/posts/{$friendsOnly['id']}/comments", ['body' => 'x'])->assertNotFound();
    }

    public function test_reports_auto_suppress_and_admin_restores(): void
    {
        config(['pawfit.admin_emails' => ['admin@pawfit.local']]);
        [$a, , $ma] = $this->author('alpha');
        $post = $this->createPost($a, ['body' => 'spam?', 'media_ids' => [$ma->id], 'visibility' => 'public', 'is_nsfw' => false]);
        $c = $this->actingAs($a)->postJson("/api/posts/{$post['id']}/comments", ['body' => 'c'])->assertCreated()->json();

        $reporters = User::factory()->count(3)->create();
        foreach ($reporters->take(2) as $r) {
            $this->actingAs($r)->postJson('/api/reports', ['target_type' => 'post', 'target_id' => $post['id'], 'reason_code' => 'other'])->assertCreated();
            // 同一人重複檢舉不計
            $this->actingAs($r)->postJson('/api/reports', ['target_type' => 'post', 'target_id' => $post['id'], 'reason_code' => 'other'])->assertOk();
        }
        $this->assertSame('active', Post::find($post['id'])->status);
        $this->asGuest()->getJson('/api/feed/explore')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($reporters[2])->postJson('/api/reports', ['target_type' => 'post', 'target_id' => $post['id'], 'reason_code' => 'harassment'])->assertCreated();
        $this->assertSame('suppressed', Post::find($post['id'])->status);
        // 降能見度：河道與訪客看不到，作者仍看得到（含 status）
        $this->asGuest()->getJson('/api/feed/explore')->assertOk()->assertJsonCount(0, 'data');
        $this->asGuest()->getJson("/api/posts/{$post['id']}")->assertNotFound();
        $this->actingAs($a)->getJson("/api/posts/{$post['id']}")->assertOk()->assertJsonPath('status', 'suppressed');

        $this->actingAs($reporters[0])->postJson('/api/reports', ['target_type' => 'comment', 'target_id' => $c['id'], 'reason_code' => 'harassment'])->assertCreated();

        $admin = User::factory()->create(['email' => 'admin@pawfit.local']);
        $reports = $this->actingAs($admin)->getJson('/api/admin/reports')->assertOk()->json('data');
        $types = array_unique(array_column($reports, 'target_type'));
        sort($types);
        $this->assertSame(['comment', 'post'], $types);
        $postReport = collect($reports)->firstWhere('target_type', 'post');
        $this->assertSame('suppressed', $postReport['target']['status']);

        $this->actingAs($admin)->postJson('/api/admin/actions', ['action' => 'restore_post', 'target_id' => $post['id'], 'report_id' => $postReport['id']])->assertOk();
        $this->assertSame('active', Post::find($post['id'])->status);
        $this->asGuest()->getJson('/api/feed/explore')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($admin)->postJson('/api/admin/actions', ['action' => 'remove_comment', 'target_id' => $c['id']])->assertOk();
        $this->asGuest()->getJson("/api/posts/{$post['id']}/comments")->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, Post::find($post['id'])->comment_count);
        $this->actingAs($admin)->postJson('/api/admin/actions', ['action' => 'remove_post', 'target_id' => $post['id']])->assertOk();
        $this->asGuest()->getJson("/api/posts/{$post['id']}")->assertNotFound();
        $this->assertDatabaseHas('admin_actions', ['action' => 'remove_post', 'target_type' => 'post']);
    }
}
