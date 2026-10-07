<?php

namespace Tests\Feature;

use App\Models\Fursona;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 2 M5：貼圖素材標記規則、素材清單（自己＋好友可見）、工具輸出的 origin。 */
class HeadStickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pawfit.features.friends' => true, 'pawfit.features.head_sticker' => true]);
    }

    public function test_sticker_flag_requires_sfw_art2d_and_feature(): void
    {
        $u = User::factory()->create();
        $f = Fursona::factory()->for($u, 'owner')->create();
        $art = Media::factory()->for($f)->create();
        $photo = Media::factory()->for($f)->create(['kind' => 'photo']);
        $nsfw = Media::factory()->for($f)->nsfw()->create();

        $this->actingAs($u)->patchJson("/api/media/{$art->id}", ['is_head_sticker' => true])->assertOk()->assertJsonPath('is_head_sticker', true);
        $this->actingAs($u)->patchJson("/api/media/{$photo->id}", ['is_head_sticker' => true])->assertStatus(422);
        $this->actingAs($u)->patchJson("/api/media/{$nsfw->id}", ['is_head_sticker' => true])->assertStatus(422);
        // 改成 NSFW 自動取消標記
        $this->actingAs($u)->patchJson("/api/media/{$art->id}", ['is_nsfw' => true])->assertOk()->assertJsonPath('is_head_sticker', false);

        config(['pawfit.features.head_sticker' => false]);
        $this->actingAs($u)->patchJson("/api/media/{$photo->id}", ['is_head_sticker' => true, 'kind' => 'art2d'])->assertStatus(422);
        $this->actingAs($u)->getJson('/api/media/stickers')->assertNotFound();
    }

    public function test_sticker_list_includes_own_and_visible_friends_stickers(): void
    {
        $me = User::factory()->create(['pawfit_id' => 'me']);
        $friend = User::factory()->create(['pawfit_id' => 'friend']);
        $stranger = User::factory()->create(['pawfit_id' => 'stranger']);
        $mine = Media::factory()->for(Fursona::factory()->for($me, 'owner'))->create(['is_head_sticker' => true, 'visibility_override' => 'private']);
        $friendPublic = Media::factory()->for(Fursona::factory()->for($friend, 'owner'))->create(['is_head_sticker' => true]);
        $friendOnly = Media::factory()->for(Fursona::factory()->for($friend, 'owner'))->create(['is_head_sticker' => true, 'visibility_override' => 'friends']);
        $friendPrivate = Media::factory()->for(Fursona::factory()->for($friend, 'owner'))->create(['is_head_sticker' => true, 'visibility_override' => 'private']);
        Media::factory()->for(Fursona::factory()->for($stranger, 'owner'))->create(['is_head_sticker' => true]);

        $req = $this->actingAs($me)->postJson('/api/friends/requests', ['pawfit_id' => 'friend'])->assertCreated()->json();
        $this->actingAs($friend)->postJson("/api/friends/requests/{$req['friendship_id']}/accept")->assertOk();

        $res = $this->actingAs($me)->getJson('/api/media/stickers')->assertOk()->json();
        $this->assertSame([$mine->id], array_column($res['mine'], 'id'));
        $this->assertCount(1, $res['friends']);
        $this->assertSame('friend', $res['friends'][0]['user']['pawfit_id']);
        $ids = array_column($res['friends'][0]['media'], 'id');
        sort($ids);
        $expected = [$friendPublic->id, $friendOnly->id];
        sort($expected);
        $this->assertSame($expected, $ids);
        $this->assertNotContains($friendPrivate->id, $ids);
    }

    public function test_tool_output_confirms_with_origin_head_sticker(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $u = User::factory()->create();
        $f = Fursona::factory()->for($u, 'owner')->create();
        $presign = $this->actingAs($u)->postJson('/api/media/presign', ['fursona_id' => $f->id, 'content_type' => 'image/webp', 'bytes' => 500])->assertOk()->json();
        Storage::disk('s3')->put($presign['storage_key'], str_repeat('x', 500));

        $this->actingAs($u)->postJson('/api/media/confirm', [
            'fursona_id' => $f->id, 'storage_key' => $presign['storage_key'], 'kind' => 'photo', 'is_nsfw' => false, 'origin' => 'head_sticker',
        ])->assertCreated()->assertJsonPath('origin', 'head_sticker')->assertJsonPath('kind', 'photo');

        $this->actingAs($u)->postJson('/api/media/confirm', [
            'fursona_id' => $f->id, 'storage_key' => $presign['storage_key'].'x', 'kind' => 'photo', 'is_nsfw' => false, 'origin' => 'ai_headswap',
        ])->assertStatus(422)->assertJsonValidationErrors('origin');
    }
}
