<?php

namespace Tests\Feature;

use App\Jobs\GenerateCommissionSheet;
use App\Models\CommissionKit;
use App\Models\Fursona;
use App\Models\Media;
use App\Models\User;
use App\Services\CommissionSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/** M7 委託需求單（FR-6）：建立／快照／模板文字／公開頁與 NSFW／停用與重新生成／合成圖 job。 */
class CommissionKitTest extends TestCase
{
    use RefreshDatabase;

    private function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    /** @return array{0: User, 1: Fursona, 2: Media, 3: Media} */
    private function setupFursona(bool $withLink = true): array
    {
        $user = User::factory()->create(['pawfit_id' => 'firefox_ash', 'display_name' => 'Ash']);
        $fursona = Fursona::factory()->for($user, 'owner')->create([
            'name' => '阿燼 Ember', 'species' => '赤狐', 'bio' => "山腳小鎮的篝火看守人。\n左耳有舊傷缺口。",
            'tags' => ['犬科', '赤狐'],
            'palette' => [
                ['hex' => '#D9642A', 'name' => '主毛色', 'note' => '', 'sort' => 0],
                ['hex' => '#F4E7D3', 'name' => '腹毛', 'note' => '內耳同色', 'sort' => 1],
            ],
        ]);
        if ($withLink) {
            $fursona->shareLinks()->create(['slug' => 'shareslug1', 'watermark' => false]);
        }
        $a = Media::factory()->for($fursona)->create(['caption' => '正面設定圖', 'credit_name' => '@kuro_lines', 'credit_url' => 'https://x.example/kuro', 'sort_order' => 0]);
        $b = Media::factory()->for($fursona)->create(['caption' => null, 'credit_name' => null, 'sort_order' => 1]);

        return [$user, $fursona, $a, $b];
    }

    public function test_create_kit_builds_snapshot_and_bilingual_brief(): void
    {
        Queue::fake();
        [$user, $fursona, $a, $b] = $this->setupFursona();

        $res = $this->actingAs($user)->postJson('/api/commission-kits', [
            'fursona_id' => $fursona->id,
            'media_ids' => [$b->id, $a->id],
            'request' => ['composition' => '半身，看向鏡頭', 'budget' => 'USD 80–120', 'notes' => "尾尖白毛請保留\n請勿畫成九尾"],
        ])->assertCreated()
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('is_nsfw', false)
            ->assertJsonPath('brief_source', 'template')
            ->assertJsonPath('media_count', 2)
            ->assertJsonPath('snapshot.fursona.name', '阿燼 Ember')
            ->assertJsonPath('snapshot.media.0.id', $b->id)
            ->assertJsonPath('snapshot.media.1.credit_name', '@kuro_lines')
            ->assertJsonPath('snapshot.share_url', 'http://localhost:8080/s/shareslug1')
            ->assertJsonPath('sheet_url', null);

        Queue::assertPushed(GenerateCommissionSheet::class);

        $slug = $res->json('slug');
        $zh = $res->json('brief_text.zh-TW');
        $en = $res->json('brief_text.en');
        $this->assertStringContainsString('【委託需求單】阿燼 Ember（赤狐）', $zh);
        $this->assertStringContainsString("/c/{$slug}", $zh);
        $this->assertStringContainsString('1. 主毛色 #D9642A', $zh);
        $this->assertStringContainsString('2. 腹毛 #F4E7D3（內耳同色）', $zh);
        $this->assertStringContainsString('1. 參考圖 1', $zh);
        $this->assertStringContainsString('2. 正面設定圖 — 繪師：@kuro_lines https://x.example/kuro', $zh);
        $this->assertStringContainsString('構圖／姿勢：半身，看向鏡頭', $zh);
        $this->assertStringContainsString("補充：\n  尾尖白毛請保留\n  請勿畫成九尾", $zh);
        $this->assertStringContainsString('[Commission Brief] 阿燼 Ember (赤狐)', $en);
        $this->assertStringContainsString('2. 正面設定圖 — art by @kuro_lines', $en);
        $this->assertStringContainsString('Budget: USD 80–120', $en);
        $this->assertStringNotContainsString('Scene', $en, '沒填的欄位不輸出');

        // 之後改獸設不影響快照
        $fursona->forceFill(['name' => '改名了'])->save();
        $this->actingAs($user)->getJson("/api/commission-kits/{$res->json('id')}")->assertOk()->assertJsonPath('snapshot.fursona.name', '阿燼 Ember');
        $this->actingAs($user)->getJson("/api/commission-kits?fursona_id={$fursona->id}")->assertOk()->assertJsonCount(1);
    }

    public function test_validation_rejects_foreign_or_processing_media_and_limits(): void
    {
        Queue::fake();
        [$user, $fursona, $a] = $this->setupFursona();
        $other = Media::factory()->create();
        $processing = Media::factory()->for($fursona)->processing()->create();

        $this->actingAs($user)->postJson('/api/commission-kits', ['fursona_id' => $fursona->id, 'media_ids' => []])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/commission-kits', ['fursona_id' => $fursona->id, 'media_ids' => [$other->id]])->assertStatus(422)->assertJsonValidationErrors('media_ids');
        $this->actingAs($user)->postJson('/api/commission-kits', ['fursona_id' => $fursona->id, 'media_ids' => [$processing->id]])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/commission-kits', ['fursona_id' => $other->fursona_id, 'media_ids' => [$other->id]])->assertStatus(422)->assertJsonValidationErrors('fursona_id');

        $many = Media::factory()->for($fursona)->count(11)->create()->pluck('id')->all();
        $this->actingAs($user)->postJson('/api/commission-kits', ['fursona_id' => $fursona->id, 'media_ids' => $many])->assertStatus(422);

        $stranger = User::factory()->create();
        $kit = $this->actingAs($user)->postJson('/api/commission-kits', ['fursona_id' => $fursona->id, 'media_ids' => [$a->id]])->assertCreated()->json();
        $this->actingAs($stranger)->getJson("/api/commission-kits/{$kit['id']}")->assertForbidden();
        $this->actingAs($stranger)->deleteJson("/api/commission-kits/{$kit['id']}")->assertForbidden();
        Queue::assertPushed(GenerateCommissionSheet::class, 1);
    }

    public function test_public_page_is_unlisted_and_images_resolve_via_kit_slug(): void
    {
        Queue::fake();
        Storage::fake('s3');
        [$user, $fursona, $a, $b] = $this->setupFursona();
        // 參考圖本身設為私人：需求單是擁有者挑的快照，仍可經需求單看到
        $b->forceFill(['visibility_override' => 'private'])->save();
        Storage::disk('s3')->put($a->display_key, 'x');
        Storage::disk('s3')->put($b->display_key, 'x');

        $kit = $this->actingAs($user)->postJson('/api/commission-kits', ['fursona_id' => $fursona->id, 'media_ids' => [$a->id, $b->id]])->assertCreated()->json();
        $slug = $kit['slug'];

        $page = $this->asGuest()->getJson("/api/commission/{$slug}")
            ->assertOk()
            ->assertJsonPath('state', 'show')
            ->assertJsonPath('is_owner', false)
            ->assertJsonPath('kit.slug', $slug)
            ->assertJsonPath('owner.pawfit_id', 'firefox_ash')
            ->assertJsonPath('fursona.share_url', 'http://localhost:8080/s/shareslug1')
            ->assertJsonCount(2, 'media')
            ->assertJsonMissingPath('kit.media_ids');
        $this->assertStringContainsString("k={$slug}", $page->json('media.1.urls.display'));
        $this->assertStringStartsWith("/api/img/{$a->id}", $page->json('og_image_url'));

        // 經 k= 可拿到私人參考圖；沒有 k= 則 404；不在需求單裡的圖用 k= 也 404
        $this->asGuest()->get("/api/img/{$b->id}?v=display&k={$slug}")->assertRedirect();
        $this->asGuest()->get("/api/img/{$b->id}?v=display")->assertNotFound();
        $outside = Media::factory()->for($fursona)->override('private')->create();
        $this->asGuest()->get("/api/img/{$outside->id}?v=display&k={$slug}")->assertNotFound();

        $this->asGuest()->getJson('/api/commission/nope')->assertNotFound();
        $this->asGuest()->get("/api/commission/{$slug}/sheet")->assertNotFound(); // 合成圖尚未產生
    }

    public function test_nsfw_kit_hidden_from_guests_but_visible_to_adult_viewer_and_owner(): void
    {
        Queue::fake();
        [$user, $fursona, $a] = $this->setupFursona();
        $nsfw = Media::factory()->for($fursona)->nsfw()->create();

        $kit = $this->actingAs($user)->postJson('/api/commission-kits', ['fursona_id' => $fursona->id, 'media_ids' => [$a->id, $nsfw->id]])
            ->assertCreated()->assertJsonPath('is_nsfw', true)->json();
        $slug = $kit['slug'];
        $this->assertStringContainsString('（NSFW）', $kit['brief_text']['zh-TW']);

        $this->asGuest()->getJson("/api/commission/{$slug}")->assertNotFound();
        $this->asGuest()->get("/api/img/{$a->id}?v=display&k={$slug}")->assertNotFound();

        $hider = User::factory()->adult('hide')->create();
        $this->actingAs($hider)->getJson("/api/commission/{$slug}")->assertNotFound();

        $blur = User::factory()->adult('blur')->create();
        $this->actingAs($blur)->getJson("/api/commission/{$slug}")->assertOk()->assertJsonPath('state', 'blur')
            ->assertJsonPath('media.0.state', 'show')->assertJsonPath('media.1.state', 'blur');

        $this->actingAs($user)->getJson("/api/commission/{$slug}")->assertOk()->assertJsonPath('state', 'show')->assertJsonPath('is_owner', true);
    }

    public function test_regenerate_rotates_slug_and_revoke_disables(): void
    {
        Queue::fake();
        [$user, $fursona, $a] = $this->setupFursona();
        $kit = $this->actingAs($user)->postJson('/api/commission-kits', ['fursona_id' => $fursona->id, 'media_ids' => [$a->id]])->assertCreated()->json();
        $old = $kit['slug'];

        $new = $this->actingAs($user)->postJson("/api/commission-kits/{$kit['id']}/regenerate")->assertOk()->json();
        $this->assertNotSame($old, $new['slug']);
        $this->assertStringContainsString("/c/{$new['slug']}", $new['brief_text']['en']);
        $this->asGuest()->getJson("/api/commission/{$old}")->assertNotFound();
        $this->asGuest()->getJson("/api/commission/{$new['slug']}")->assertOk();
        Queue::assertPushed(GenerateCommissionSheet::class, 2);

        $this->actingAs($user)->deleteJson("/api/commission-kits/{$kit['id']}")->assertNoContent();
        $this->asGuest()->getJson("/api/commission/{$new['slug']}")->assertNotFound();
        $this->actingAs($user)->postJson("/api/commission-kits/{$kit['id']}/regenerate")->assertStatus(409);
        // 擁有者仍可在管理介面看到（資料保留）
        $this->actingAs($user)->getJson("/api/commission-kits/{$kit['id']}")->assertOk()->assertJsonPath('status', 'revoked');
    }

    public function test_sheet_job_renders_webp_and_activates_kit(): void
    {
        Storage::fake('s3');
        [$user, $fursona, $a, $b] = $this->setupFursona();
        $manager = ImageManager::usingDriver(ImagickDriver::class);
        Storage::disk('s3')->put($a->display_key, $manager->createImage(300, 200)->fill('#d9642a')->encode(new PngEncoder)->toString());
        Storage::disk('s3')->put($b->display_key, $manager->createImage(200, 300)->fill('#3f9d78')->encode(new PngEncoder)->toString());

        Queue::fake();
        $id = $this->actingAs($user)->postJson('/api/commission-kits', ['fursona_id' => $fursona->id, 'media_ids' => [$a->id, $b->id]])->assertCreated()->json('id');
        $kit = CommissionKit::findOrFail($id);

        (new GenerateCommissionSheet($kit))->handle(app(CommissionSheet::class));

        $kit->refresh();
        $this->assertSame('active', $kit->status);
        $this->assertNotNull($kit->sheet_key);
        Storage::disk('s3')->assertExists($kit->sheet_key);
        $bin = Storage::disk('s3')->get($kit->sheet_key);
        $this->assertSame('RIFF', substr($bin, 0, 4), '輸出應為 webp');
        $this->assertGreaterThan(2000, strlen($bin));
        $img = $manager->decodeBinary($bin);
        $this->assertSame(2048, $img->width());

        $this->asGuest()->getJson("/api/commission/{$kit->slug}")->assertOk()
            ->assertJsonPath('kit.sheet_url', "/api/commission/{$kit->slug}/sheet")
            ->assertJsonPath('og_image_url', "/api/commission/{$kit->slug}/sheet");
        $this->asGuest()->get("/api/commission/{$kit->slug}/sheet")->assertRedirect();
    }
}
