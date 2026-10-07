<?php

namespace Tests\Feature;

use App\Models\Fursona;
use App\Models\Media;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** M6 開放與嵌入（FR-7）：opt-in 開關、公開 JSON API v1、oEmbed、SVG 色票卡、標頭。 */
class EmbedApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Fursona, 2: ShareLink} */
    private function openFursona(array $userAttrs = [], array $fursonaAttrs = []): array
    {
        $user = User::factory()->create(array_merge(['pawfit_id' => 'firefox_ash', 'allow_embed_api' => true], $userAttrs));
        $fursona = Fursona::factory()->for($user, 'owner')->create(array_merge(['name' => '阿燼 Ember', 'is_representative' => true], $fursonaAttrs));
        $link = $fursona->shareLinks()->create(['slug' => ShareLink::generateSlug(), 'watermark' => false]);

        return [$user, $fursona, $link];
    }

    public function test_everything_is_404_until_user_opts_in(): void
    {
        [$user, $fursona, $link] = $this->openFursona(['allow_embed_api' => false]);
        Media::factory()->for($fursona)->create();

        $this->getJson("/api/v1/public/users/{$user->pawfit_id}")->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson("/api/v1/public/fursonas/{$link->slug}")->assertNotFound();
        $this->getJson("/api/v1/public/fursonas/{$link->slug}/media")->assertNotFound();
        $this->get("/api/v1/public/fursonas/{$link->slug}/palette.svg")->assertNotFound();
        $this->getJson('/api/oembed?url='.urlencode($link->url()))->assertNotFound();

        // 分享頁本身不受影響，但不輸出 oEmbed discovery
        $this->getJson("/api/share/{$link->slug}")->assertOk()->assertJsonPath('embed_enabled', false);

        $this->actingAs($user)->patchJson('/api/me', ['allow_embed_api' => true])->assertOk()->assertJsonPath('allow_embed_api', true);
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/public/fursonas/{$link->slug}")->assertOk();
        $this->getJson("/api/share/{$link->slug}")->assertOk()->assertJsonPath('embed_enabled', true);
    }

    public function test_fursona_level_override_can_close_one_fursona(): void
    {
        [$user, $fursona, $link] = $this->openFursona();

        $this->actingAs($user)->patchJson("/api/fursonas/{$fursona->id}", ['allow_embed_api' => false])
            ->assertOk()->assertJsonPath('allow_embed_api', false)->assertJsonPath('embed_enabled', false);
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/public/fursonas/{$link->slug}")->assertNotFound();
        // 用戶端點仍可用，但清單不含這隻
        $this->getJson("/api/v1/public/users/{$user->pawfit_id}")->assertOk()->assertJsonCount(0, 'fursonas');

        $this->actingAs($user)->patchJson("/api/fursonas/{$fursona->id}", ['allow_embed_api' => null])
            ->assertOk()->assertJsonPath('allow_embed_api', null)->assertJsonPath('embed_enabled', true);
    }

    public function test_fursona_endpoint_returns_public_data_and_headers(): void
    {
        [$user, $fursona, $link] = $this->openFursona();
        $sfw = Media::factory()->for($fursona)->create(['credit_name' => 'Nia', 'credit_url' => 'https://nia.example', 'sort_order' => 0]);
        Media::factory()->for($fursona)->create(['credit_name' => 'nia ', 'credit_url' => 'https://nia.example', 'sort_order' => 1]);
        Media::factory()->for($fursona)->nsfw()->create(['credit_name' => 'Hidden', 'sort_order' => 2]);
        Media::factory()->for($fursona)->override('private')->create(['sort_order' => 3]);
        Media::factory()->for($fursona)->processing()->create(['sort_order' => 4]);

        $res = $this->getJson("/api/v1/public/fursonas/{$link->slug}")
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('X-Robots-Tag', 'noai, noimageai')
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonPath('slug', $link->slug)
            ->assertJsonPath('name', '阿燼 Ember')
            ->assertJsonPath('palette.0.hex', '#D9642A')
            ->assertJsonPath('media_count', 2)
            ->assertJsonCount(1, 'credits')
            ->assertJsonPath('owner.pawfit_id', 'firefox_ash')
            ->assertJsonPath('share_url', $link->url())
            ->assertJsonPath('embed.card_url', config('pawfit.frontend_url').'/embed/'.$link->slug);

        $this->assertTrue($res->headers->has('X-Pawfit-Terms'));
        $this->assertStringStartsWith(config('pawfit.frontend_url').'/api/img/'.$sfw->id, $res->json('cover_url'));
        $this->assertStringNotContainsString('media/x/y', $res->getContent(), '不得洩漏 R2 object key');

        $media = $this->getJson("/api/v1/public/fursonas/{$link->slug}/media")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('next_cursor', null);
        $this->assertSame([$sfw->id], array_slice(array_column($media->json('data'), 'id'), 0, 1));
        $this->assertStringContainsString("s={$link->slug}", $media->json('data.0.url'));
    }

    public function test_media_pagination_cursor(): void
    {
        config(['pawfit.embed.media_page_size' => 2]);
        [, $fursona, $link] = $this->openFursona();
        foreach (range(0, 4) as $i) {
            Media::factory()->for($fursona)->create(['sort_order' => $i]);
        }

        $first = $this->getJson("/api/v1/public/fursonas/{$link->slug}/media")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('total', 5);
        $this->assertNotNull($first->json('next_cursor'));
        $second = $this->getJson("/api/v1/public/fursonas/{$link->slug}/media?cursor=".$first->json('next_cursor'))->assertOk()->assertJsonCount(2, 'data');
        $third = $this->getJson("/api/v1/public/fursonas/{$link->slug}/media?cursor=".$second->json('next_cursor'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('next_cursor', null);
        $this->assertCount(5, array_unique(array_merge(
            array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'), array_column($third->json('data'), 'id'),
        )));

        $this->getJson("/api/v1/public/fursonas/{$link->slug}/media?cursor=garbage")->assertStatus(422);
    }

    public function test_private_nsfw_revoked_and_banned_are_all_404(): void
    {
        [, $private, $privateLink] = $this->openFursona(['pawfit_id' => 'p1'], ['visibility' => 'private']);
        $this->getJson("/api/v1/public/fursonas/{$privateLink->slug}")->assertNotFound();

        [, , $nsfwLink] = $this->openFursona(['pawfit_id' => 'p2'], ['is_nsfw' => true]);
        $this->getJson("/api/v1/public/fursonas/{$nsfwLink->slug}")->assertNotFound();

        [, , $unlistedLink] = $this->openFursona(['pawfit_id' => 'p3'], ['visibility' => 'unlisted']);
        $this->getJson("/api/v1/public/fursonas/{$unlistedLink->slug}")->assertOk();
        $unlistedLink->forceFill(['revoked_at' => now()])->save();
        $this->getJson("/api/v1/public/fursonas/{$unlistedLink->slug}")->assertNotFound();

        [$banned, , $bannedLink] = $this->openFursona(['pawfit_id' => 'p4', 'is_banned' => true]);
        $this->getJson("/api/v1/public/fursonas/{$bannedLink->slug}")->assertNotFound();
        $this->getJson("/api/v1/public/users/{$banned->pawfit_id}")->assertNotFound();
    }

    public function test_user_endpoint_lists_only_embeddable_public_fursonas(): void
    {
        [$user, $rep, $link] = $this->openFursona();
        Fursona::factory()->for($user, 'owner')->visibility('unlisted')->create();
        Fursona::factory()->for($user, 'owner')->nsfw()->create();
        $noLink = Fursona::factory()->for($user, 'owner')->create(['name' => 'NoLink']);

        $this->getJson('/api/v1/public/users/FIREFOX_ASH')
            ->assertOk()
            ->assertJsonPath('pawfit_id', 'firefox_ash')
            ->assertJsonPath('representative_slug', $link->slug)
            ->assertJsonCount(2, 'fursonas')
            ->assertJsonPath('fursonas.0.slug', $link->slug)
            ->assertJsonPath('fursonas.1.name', 'NoLink')
            ->assertJsonPath('fursonas.1.slug', null);
    }

    public function test_palette_svg_is_static_and_cached(): void
    {
        [, $fursona, $link] = $this->openFursona(fursonaAttrs: ['palette' => [
            ['hex' => '#D9642A', 'name' => '主毛色 <b>', 'note' => '"耳背"', 'sort' => 0],
            ['hex' => 'javascript:alert(1)', 'name' => 'bad', 'note' => '', 'sort' => 1],
        ]]);

        $res = $this->get("/api/v1/public/fursonas/{$link->slug}/palette.svg?theme=dark&layout=grid")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->assertHeader('Cache-Control', 'max-age=600, public')
            ->assertHeader('X-Robots-Tag', 'noai, noimageai');
        $body = $res->getContent();

        $this->assertStringStartsWith('<svg', $body);
        $this->assertStringContainsString('#D9642A', $body);
        $this->assertStringContainsString('主毛色 &lt;b&gt;', $body);
        $this->assertStringContainsString('&quot;耳背&quot;', $body);
        $this->assertStringContainsString('#CCCCCC', $body, '非法 hex 退回灰色');
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringContainsString('#1d1930', $body, 'dark 主題底色');

        $this->get("/api/v1/public/fursonas/{$link->slug}/palette.svg?theme=neon")->assertOk();
    }

    public function test_oembed_for_share_page_and_profile(): void
    {
        [$user, $fursona, $link] = $this->openFursona();
        $cover = Media::factory()->for($fursona)->create();

        $res = $this->getJson('/api/oembed?format=json&url='.urlencode($link->url()))
            ->assertOk()
            ->assertJsonPath('type', 'rich')
            ->assertJsonPath('provider_name', 'Pawfit')
            ->assertJsonPath('title', '阿燼 Ember · '.$fursona->species)
            ->assertJsonPath('author_url', config('pawfit.frontend_url').'/u/firefox_ash')
            ->assertJsonPath('width', 480)->assertJsonPath('height', 320);
        $this->assertStringContainsString('<iframe src="'.config('pawfit.frontend_url').'/embed/'.$link->slug.'"', $res->json('html'));
        $this->assertStringContainsString('/api/img/'.$cover->id, $res->json('thumbnail_url'));

        // maxwidth 小於 md → sm
        $this->getJson('/api/oembed?maxwidth=300&url='.urlencode($link->url()))->assertOk()->assertJsonPath('width', 320);

        // 個人主頁 → 代表獸設的卡片
        $this->getJson('/api/oembed?url='.urlencode(config('pawfit.frontend_url').'/u/firefox_ash'))
            ->assertOk()->assertJsonPath('title', '阿燼 Ember · '.$fursona->species);

        // 其他網域、不存在的路徑、xml 格式
        $this->getJson('/api/oembed?url='.urlencode('https://evil.example/s/'.$link->slug))->assertNotFound();
        $this->getJson('/api/oembed?url='.urlencode(config('pawfit.frontend_url').'/dashboard'))->assertNotFound();
        $this->getJson('/api/oembed?format=xml&url='.urlencode($link->url()))->assertStatus(422);
    }

    public function test_public_api_ignores_logged_in_viewer(): void
    {
        [$user, $fursona, $link] = $this->openFursona();
        Media::factory()->for($fursona)->nsfw()->create();

        // 擁有者自己（偏好 show）打公開 API，NSFW 圖仍然不出現
        $user->forceFill(['adult_confirmed_at' => now(), 'nsfw_pref' => 'show'])->save();
        $this->actingAs($user)->getJson("/api/v1/public/fursonas/{$link->slug}/media")->assertOk()->assertJsonCount(0, 'data');
    }
}
