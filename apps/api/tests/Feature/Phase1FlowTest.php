<?php

namespace Tests\Feature;

use App\Jobs\ProcessMedia;
use App\Models\Fursona;
use App\Models\Media;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 1 DoD 的 API 走查：onboarding → 獸設 → 上傳 → 分享連結 → 訪客看不到 NSFW → 檢舉與後台。 */
class Phase1FlowTest extends TestCase
{
    use RefreshDatabase;

    /** actingAs 會延續到同一測試的後續請求；切回訪客要清掉 guard。 */
    private function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    public function test_onboarding_sets_pawfit_id_and_rejects_reserved_or_taken_ids(): void
    {
        $user = User::factory()->fresh()->create();
        User::factory()->create(['pawfit_id' => 'taken_one']);

        $this->actingAs($user)->postJson('/api/fursonas', ['name' => 'x'])->assertStatus(409);

        $this->actingAs($user)->patchJson('/api/me', ['pawfit_id' => 'admin'])->assertStatus(422);
        $this->actingAs($user)->patchJson('/api/me', ['pawfit_id' => 'Taken_One'])->assertStatus(422);
        $this->actingAs($user)->patchJson('/api/me', ['pawfit_id' => 'ab'])->assertStatus(422);

        $this->actingAs($user)->patchJson('/api/me', [
            'pawfit_id' => 'firefox_ash', 'display_name' => 'Ash', 'tos_accepted' => true,
        ])->assertOk()->assertJsonPath('pawfit_id', 'firefox_ash')->assertJsonPath('is_onboarded', true);

        // Phase 1 不開放更名
        $this->actingAs($user)->patchJson('/api/me', ['pawfit_id' => 'another_id'])->assertStatus(422);
    }

    public function test_nsfw_pref_requires_adult_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/me', ['nsfw_pref' => 'blur'])->assertStatus(422);
        $this->actingAs($user)->patchJson('/api/me', ['adult_confirmed' => true, 'nsfw_pref' => 'blur'])
            ->assertOk()->assertJsonPath('nsfw_pref', 'blur');
        $this->assertNotNull($user->fresh()->adult_confirmed_at);
    }

    public function test_fursona_crud_and_single_representative(): void
    {
        $user = User::factory()->create();

        $a = $this->actingAs($user)->postJson('/api/fursonas', [
            'name' => '阿燼 Ember', 'species' => '赤狐',
            'palette' => [['hex' => '#d9642a', 'name' => '主毛色']],
            'tags' => ['犬科', ' 犬科 ', '赤狐'],
        ])->assertCreated()
            ->assertJsonPath('is_representative', true)
            ->assertJsonPath('palette.0.hex', '#D9642A')
            ->assertJsonPath('tags', ['犬科', '赤狐'])
            ->json('id');

        $b = $this->actingAs($user)->postJson('/api/fursonas', ['name' => '雪芽'])->assertCreated()
            ->assertJsonPath('is_representative', false)->json('id');

        $this->actingAs($user)->patchJson("/api/fursonas/{$b}", ['is_representative' => true])->assertOk();
        $this->assertFalse(Fursona::find($a)->is_representative);
        $this->assertTrue(Fursona::find($b)->is_representative);

        $this->actingAs($user)->postJson('/api/fursonas', ['name' => 'x', 'palette' => [['hex' => 'red']]])->assertStatus(422);
        $this->actingAs($user)->patchJson("/api/fursonas/{$a}", ['visibility' => 'friends'])->assertStatus(422);

        $other = User::factory()->create();
        $this->actingAs($other)->getJson("/api/fursonas/{$a}")->assertForbidden();
        $this->actingAs($other)->deleteJson("/api/fursonas/{$a}")->assertForbidden();

        $this->actingAs($user)->deleteJson("/api/fursonas/{$a}")->assertNoContent();
        $this->assertDatabaseMissing('fursonas', ['id' => $a]);
    }

    public function test_upload_presign_confirm_dispatches_processing(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $user = User::factory()->create();
        $fursona = Fursona::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->postJson('/api/media/presign', [
            'fursona_id' => $fursona->id, 'content_type' => 'image/gif', 'bytes' => 100,
        ])->assertStatus(422);

        $presign = $this->actingAs($user)->postJson('/api/media/presign', [
            'fursona_id' => $fursona->id, 'content_type' => 'image/png', 'bytes' => 1000,
        ])->assertOk()->json();
        $this->assertStringStartsWith("media/{$user->id}/{$fursona->id}/", $presign['storage_key']);

        // 尚未直傳 → confirm 失敗
        $this->actingAs($user)->postJson('/api/media/confirm', [
            'fursona_id' => $fursona->id, 'storage_key' => $presign['storage_key'], 'kind' => 'art2d', 'is_nsfw' => false,
        ])->assertStatus(422);

        Storage::disk('s3')->put($presign['storage_key'], str_repeat('x', 1000));

        // 分級與分類皆必填
        $this->actingAs($user)->postJson('/api/media/confirm', [
            'fursona_id' => $fursona->id, 'storage_key' => $presign['storage_key'], 'kind' => 'art2d',
        ])->assertStatus(422)->assertJsonValidationErrors('is_nsfw');

        $media = $this->actingAs($user)->postJson('/api/media/confirm', [
            'fursona_id' => $fursona->id, 'storage_key' => $presign['storage_key'],
            'kind' => 'photo', 'is_nsfw' => true, 'credit_name' => 'FuzzWorks',
        ])->assertCreated()->assertJsonPath('status', 'processing')->json();

        Queue::assertPushed(ProcessMedia::class);
        $this->assertSame(1000, Media::find($media['id'])->bytes);

        // 另一人的獸設路徑不能被塞進來
        $this->actingAs($user)->postJson('/api/media/confirm', [
            'fursona_id' => $fursona->id, 'storage_key' => 'media/other/other/x.png', 'kind' => 'art2d', 'is_nsfw' => false,
        ])->assertStatus(422);
    }

    public function test_share_link_regeneration_revokes_old_and_visitors_never_see_nsfw(): void
    {
        $owner = User::factory()->create();
        $fursona = Fursona::factory()->for($owner, 'owner')->create();
        Media::factory()->for($fursona)->create(['caption' => 'sfw']);
        Media::factory()->for($fursona)->nsfw()->create(['caption' => 'nsfw']);

        $link = $this->actingAs($owner)->postJson("/api/fursonas/{$fursona->id}/share-link")->assertCreated()->json();

        $page = $this->asGuest()->getJson("/api/share/{$link['slug']}")->assertOk()->json();
        $this->assertSame(['sfw'], collect($page['fursona']['media'])->pluck('caption')->all());
        $this->assertSame(1, $page['hidden_nsfw_count']);
        $this->assertStringContainsString("s={$link['slug']}", $page['fursona']['media'][0]['urls']['thumb']);

        // 偏好 blur 的成年用戶看到 blur 狀態
        $adult = User::factory()->adult('blur')->create();
        $page = $this->actingAs($adult)->getJson("/api/share/{$link['slug']}")->assertOk()->json();
        $this->assertSame(['show', 'blur'], collect($page['fursona']['media'])->pluck('state')->all());

        // 重新生成 → 舊 slug 立即失效
        $new = $this->actingAs($owner)->postJson("/api/fursonas/{$fursona->id}/share-link")->assertCreated()->json();
        $this->asGuest()->getJson("/api/share/{$link['slug']}")->assertNotFound();
        $this->getJson("/api/share/{$new['slug']}")->assertOk();

        // 停用
        $this->actingAs($owner)->deleteJson("/api/share-links/{$new['id']}")->assertNoContent();
        $this->asGuest()->getJson("/api/share/{$new['slug']}")->assertNotFound();

        // 私人獸設的分享頁對外 404
        $fresh = $this->actingAs($owner)->postJson("/api/fursonas/{$fursona->id}/share-link")->json();
        $fursona->forceFill(['visibility' => 'private'])->save();
        $this->asGuest()->getJson("/api/share/{$fresh['slug']}")->assertNotFound();
    }

    public function test_image_route_enforces_acl_and_redirects_to_signed_url(): void
    {
        Storage::fake('s3');
        $owner = User::factory()->create();
        $fursona = Fursona::factory()->for($owner, 'owner')->visibility('unlisted')->create();
        $media = Media::factory()->for($fursona)->create();
        $nsfw = Media::factory()->for($fursona)->nsfw()->create();
        $link = $this->actingAs($owner)->postJson("/api/fursonas/{$fursona->id}/share-link")->json();

        // 訪客沒有 slug → 404；帶 slug → 302
        $this->asGuest()->get("/api/img/{$media->id}?v=thumb")->assertNotFound();
        $this->get("/api/img/{$media->id}?v=thumb&s={$link['slug']}")->assertRedirect();
        $this->get("/api/img/{$nsfw->id}?v=thumb&s={$link['slug']}")->assertNotFound();

        // 原檔只有擁有者拿得到
        $this->get("/api/img/{$media->id}?v=original&s={$link['slug']}")->assertForbidden();
        $this->actingAs($owner)->get("/api/img/{$media->id}?v=original")->assertRedirect();
    }

    public function test_public_profile_lists_only_public_visible_fursonas(): void
    {
        $owner = User::factory()->create(['pawfit_id' => 'firefox_ash']);
        Fursona::factory()->for($owner, 'owner')->create(['name' => 'pub', 'is_representative' => true]);
        Fursona::factory()->for($owner, 'owner')->visibility('unlisted')->create(['name' => 'unl']);
        Fursona::factory()->for($owner, 'owner')->visibility('private')->create(['name' => 'prv']);
        Fursona::factory()->for($owner, 'owner')->nsfw()->create(['name' => 'nsfw']);

        $page = $this->asGuest()->getJson('/api/users/FIREFOX_ASH')->assertOk()->json();
        $this->assertSame(['pub'], collect($page['fursonas'])->pluck('name')->all());
        $this->assertSame('pub', $page['representative']['name']);

        $adult = User::factory()->adult('show')->create();
        $page = $this->actingAs($adult)->getJson('/api/users/firefox_ash')->assertOk()->json();
        $this->assertSame(['pub', 'nsfw'], collect($page['fursonas'])->pluck('name')->all());

        $this->asGuest()->getJson('/api/users/nobody_here')->assertNotFound();
    }

    public function test_reports_and_admin_actions(): void
    {
        config(['pawfit.admin_emails' => ['admin@pawfit.local']]);
        $admin = User::factory()->create(['email' => 'admin@pawfit.local']);
        $reporter = User::factory()->create();
        $fursona = Fursona::factory()->create();
        $media = Media::factory()->for($fursona)->create();

        $this->actingAs($reporter)->getJson('/api/admin/reports')->assertForbidden();

        $report = $this->actingAs($reporter)->postJson('/api/reports', [
            'target_type' => 'media', 'target_id' => $media->id, 'reason_code' => 'untagged_nsfw', 'detail' => '明顯成人向',
        ])->assertCreated()->json();

        // 重複檢舉不新增
        $this->actingAs($reporter)->postJson('/api/reports', [
            'target_type' => 'media', 'target_id' => $media->id, 'reason_code' => 'untagged_nsfw',
        ])->assertOk();
        $this->assertSame(1, Report::count());

        $list = $this->actingAs($admin)->getJson('/api/admin/reports')->assertOk()->json('data');
        $this->assertCount(1, $list);
        $this->assertSame('media', $list[0]['target_type']);
        $this->assertNotNull($list[0]['target']);

        $this->actingAs($admin)->postJson('/api/admin/actions', [
            'action' => 'mark_nsfw', 'target_id' => $media->id, 'report_id' => $report['id'],
        ])->assertOk()->assertJsonPath('report.status', 'resolved');
        $this->assertTrue($media->fresh()->is_nsfw);

        $this->actingAs($admin)->postJson('/api/admin/actions', ['action' => 'remove_media', 'target_id' => $media->id])->assertOk();
        $this->assertSame('removed', $media->fresh()->status);
        $this->asGuest()->get("/api/img/{$media->id}?v=thumb")->assertNotFound();

        $this->actingAs($admin)->postJson('/api/admin/actions', ['action' => 'ban_user', 'target_id' => $fursona->owner_id])->assertOk();
        $this->assertTrue($fursona->owner->fresh()->is_banned);
        $this->actingAs($fursona->owner->fresh())->postJson('/api/fursonas', ['name' => 'x'])->assertForbidden();

        $this->assertDatabaseCount('admin_actions', 3);
        $stats = $this->actingAs($admin)->getJson('/api/admin/stats')->assertOk()->json();
        $this->assertSame(1, $stats['banned_users']);
        $this->assertSame(0, $stats['open']);
    }
}
