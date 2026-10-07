<?php

namespace Tests\Feature;

use App\Models\Fursona;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 2 M1：好友邀請／接受／解除、封鎖、limited-to-friends 隱私與主頁可見性。 */
class FriendsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pawfit.features.friends' => true]);
    }

    private function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    public function test_feature_flag_gates_everything(): void
    {
        config(['pawfit.features.friends' => false]);
        $u = User::factory()->create();
        $this->actingAs($u)->getJson('/api/friends')->assertNotFound();
        $this->actingAs($u)->postJson('/api/friends/requests', ['pawfit_id' => 'x'])->assertNotFound();
        $f = Fursona::factory()->for($u, 'owner')->create();
        $this->actingAs($u)->patchJson("/api/fursonas/{$f->id}", ['visibility' => 'friends'])->assertStatus(422);
        $this->asGuest()->getJson('/api/features')->assertOk()->assertJsonPath('friends', false);
    }

    public function test_request_accept_and_remove_flow(): void
    {
        $a = User::factory()->create(['pawfit_id' => 'alpha']);
        $b = User::factory()->create(['pawfit_id' => 'bravo']);

        $this->actingAs($a)->postJson('/api/friends/requests', ['pawfit_id' => 'alpha'])->assertStatus(422);
        $this->actingAs($a)->postJson('/api/friends/requests', ['pawfit_id' => 'nobody'])->assertStatus(422);

        $req = $this->actingAs($a)->postJson('/api/friends/requests', ['pawfit_id' => 'BRAVO'])
            ->assertCreated()->assertJsonPath('status', 'pending_out')->assertJsonPath('user.pawfit_id', 'bravo')->json();
        $this->actingAs($a)->postJson('/api/friends/requests', ['pawfit_id' => 'bravo'])->assertStatus(422);

        $this->actingAs($a)->getJson('/api/friends')->assertOk()->assertJsonCount(1, 'outgoing')->assertJsonCount(0, 'friends');
        $this->actingAs($b)->getJson('/api/friends')->assertOk()->assertJsonCount(1, 'incoming')->assertJsonPath('incoming.0.user.pawfit_id', 'alpha');

        // 送出者不能自己接受；第三人不能動
        $this->actingAs($a)->postJson("/api/friends/requests/{$req['friendship_id']}/accept")->assertForbidden();
        $c = User::factory()->create();
        $this->actingAs($c)->postJson("/api/friends/requests/{$req['friendship_id']}/accept")->assertNotFound();

        $this->actingAs($b)->postJson("/api/friends/requests/{$req['friendship_id']}/accept")->assertOk()->assertJsonPath('status', 'friends');
        $this->actingAs($a)->getJson('/api/friends')->assertOk()->assertJsonCount(1, 'friends')->assertJsonPath('friends.0.user.pawfit_id', 'bravo');
        $this->actingAs($a)->getJson('/api/users/bravo')->assertOk()->assertJsonPath('relation.status', 'friends');

        $this->actingAs($a)->postJson('/api/friends/requests', ['pawfit_id' => 'bravo'])->assertStatus(422);
        $this->actingAs($a)->deleteJson("/api/friends/requests/{$req['friendship_id']}")->assertStatus(409);

        $this->actingAs($b)->deleteJson("/api/friends/{$a->id}")->assertNoContent();
        $this->actingAs($a)->getJson('/api/friends')->assertOk()->assertJsonCount(0, 'friends');
        $this->actingAs($a)->getJson('/api/users/bravo')->assertOk()->assertJsonPath('relation.status', 'none');
    }

    public function test_mutual_requests_auto_accept_and_decline_deletes(): void
    {
        $a = User::factory()->create(['pawfit_id' => 'alpha']);
        $b = User::factory()->create(['pawfit_id' => 'bravo']);

        $req = $this->actingAs($a)->postJson('/api/friends/requests', ['pawfit_id' => 'bravo'])->assertCreated()->json();
        $this->actingAs($b)->getJson('/api/users/alpha')->assertOk()->assertJsonPath('relation.status', 'pending_in')->assertJsonPath('relation.friendship_id', $req['friendship_id']);
        $this->actingAs($b)->postJson('/api/friends/requests', ['pawfit_id' => 'alpha'])->assertOk()->assertJsonPath('status', 'friends');

        $this->actingAs($b)->deleteJson("/api/friends/{$a->id}")->assertNoContent();
        $req2 = $this->actingAs($a)->postJson('/api/friends/requests', ['pawfit_id' => 'bravo'])->assertCreated()->json();
        $this->actingAs($b)->deleteJson("/api/friends/requests/{$req2['friendship_id']}")->assertNoContent();
        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_friends_only_fursona_visible_to_friends_not_guests_or_links(): void
    {
        $owner = User::factory()->create(['pawfit_id' => 'owner']);
        $friend = User::factory()->create(['pawfit_id' => 'friend']);
        $stranger = User::factory()->create(['pawfit_id' => 'stranger']);
        $fursona = Fursona::factory()->for($owner, 'owner')->create(['visibility' => 'friends', 'name' => 'Secret']);
        $media = Media::factory()->for($fursona)->create();
        $link = $fursona->shareLinks()->create(['slug' => 'friendsonly', 'watermark' => false]);

        $req = $this->actingAs($friend)->postJson('/api/friends/requests', ['pawfit_id' => 'owner'])->assertCreated()->json();
        $this->actingAs($owner)->postJson("/api/friends/requests/{$req['friendship_id']}/accept")->assertOk();

        // 擁有者可設 friends
        $this->actingAs($owner)->patchJson("/api/fursonas/{$fursona->id}", ['visibility' => 'friends'])->assertOk()->assertJsonPath('visibility', 'friends');

        $this->asGuest()->getJson('/api/share/friendsonly')->assertNotFound();
        $this->asGuest()->get("/api/img/{$media->id}?v=display&s=friendsonly")->assertNotFound();
        $this->actingAs($stranger)->getJson('/api/share/friendsonly')->assertNotFound();
        $this->actingAs($friend)->getJson('/api/share/friendsonly')->assertOk()->assertJsonPath('fursona.name', 'Secret');
        $this->actingAs($friend)->get("/api/img/{$media->id}?v=display")->assertRedirect();

        // 主頁：好友看得到限好友獸設，陌生人與訪客看不到
        $this->actingAs($friend)->getJson('/api/users/owner')->assertOk()->assertJsonCount(1, 'fursonas');
        $this->actingAs($stranger)->getJson('/api/users/owner')->assertOk()->assertJsonCount(0, 'fursonas');
        $this->asGuest()->getJson('/api/users/owner')->assertOk()->assertJsonCount(0, 'fursonas')->assertJsonPath('relation', null);

        // 單圖覆寫 friends：公開獸設裡只有好友看得到那張
        $public = Fursona::factory()->for($owner, 'owner')->create();
        $onlyFriends = Media::factory()->for($public)->override('friends')->create();
        $this->asGuest()->get("/api/img/{$onlyFriends->id}?v=thumb")->assertNotFound();
        $this->actingAs($friend)->get("/api/img/{$onlyFriends->id}?v=thumb")->assertRedirect();

        // 解除好友後立即失效
        $this->actingAs($owner)->deleteJson("/api/friends/{$friend->id}")->assertNoContent();
        $this->actingAs($friend)->getJson('/api/share/friendsonly')->assertNotFound();
    }

    public function test_block_hides_both_ways_and_clears_friendship(): void
    {
        $a = User::factory()->create(['pawfit_id' => 'alpha']);
        $b = User::factory()->create(['pawfit_id' => 'bravo']);
        $fa = Fursona::factory()->for($a, 'owner')->create();
        $fb = Fursona::factory()->for($b, 'owner')->create();
        $la = $fa->shareLinks()->create(['slug' => 'alphalink1', 'watermark' => false]);
        $mb = Media::factory()->for($fb)->create();

        $req = $this->actingAs($a)->postJson('/api/friends/requests', ['pawfit_id' => 'bravo'])->assertCreated()->json();
        $this->actingAs($b)->postJson("/api/friends/requests/{$req['friendship_id']}/accept")->assertOk();

        $this->actingAs($a)->postJson("/api/blocks/{$a->id}")->assertStatus(422);
        $this->actingAs($a)->postJson("/api/blocks/{$b->id}")->assertCreated();
        $this->assertDatabaseCount('friendships', 0);
        $this->actingAs($a)->getJson('/api/friends')->assertOk()->assertJsonCount(1, 'blocked')->assertJsonPath('blocked.0.user.pawfit_id', 'bravo');

        // 雙向互不可見：主頁、分享頁、圖片
        $this->actingAs($a)->getJson('/api/users/bravo')->assertNotFound();
        $this->actingAs($b)->getJson('/api/users/alpha')->assertNotFound();
        $this->actingAs($b)->getJson("/api/share/{$la->slug}")->assertNotFound();
        $this->actingAs($a)->get("/api/img/{$mb->id}?v=thumb")->assertNotFound();
        // 其他人與訪客不受影響
        $this->asGuest()->getJson('/api/users/bravo')->assertOk();
        $this->asGuest()->getJson("/api/share/{$la->slug}")->assertOk();

        // 被封鎖者送邀請：回「找不到」，不洩漏封鎖
        $this->actingAs($b)->postJson('/api/friends/requests', ['pawfit_id' => 'alpha'])->assertStatus(422)
            ->assertJsonPath('errors.pawfit_id.0', __('messages.friends.user_not_found'));
        // 封鎖者自己也不能邀請
        $this->actingAs($a)->postJson('/api/friends/requests', ['pawfit_id' => 'bravo'])->assertStatus(422);

        $this->actingAs($a)->deleteJson("/api/blocks/{$b->id}")->assertNoContent();
        $this->actingAs($b)->getJson('/api/users/alpha')->assertOk()->assertJsonPath('relation.status', 'none');
    }
}
