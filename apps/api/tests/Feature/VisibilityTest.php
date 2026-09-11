<?php

namespace Tests\Feature;

use App\Models\Fursona;
use App\Models\Media;
use App\Models\ShareLink;
use App\Models\User;
use App\Services\Visibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** FR-5.4 NSFW 顯示矩陣 × 三段隱私（SASD §1.5、§2.4）。 */
class VisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Visibility $vis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vis = app(Visibility::class);
    }

    public static function nsfwMatrix(): array
    {
        // [viewer 描述, 內容 NSFW?, 期望 state]
        return [
            'guest / SFW' => ['guest', false, 'show'],
            'guest / NSFW' => ['guest', true, null],
            'no adult confirm / NSFW' => ['unconfirmed', true, null],
            'pref hide / NSFW' => ['hide', true, null],
            'pref blur / NSFW' => ['blur', true, 'blur'],
            'pref show / NSFW' => ['show', true, 'show'],
            'pref blur / SFW' => ['blur', false, 'show'],
        ];
    }

    #[DataProvider('nsfwMatrix')]
    public function test_nsfw_matrix_on_public_media(string $viewerKind, bool $nsfw, ?string $expected): void
    {
        $fursona = Fursona::factory()->create();
        $media = Media::factory()->for($fursona)->create(['is_nsfw' => $nsfw]);

        $viewer = match ($viewerKind) {
            'guest' => null,
            'unconfirmed' => User::factory()->create(['nsfw_pref' => 'show']),
            default => User::factory()->adult($viewerKind)->create(),
        };

        $this->assertSame($expected, $this->vis->mediaState($viewer, $media->fresh('fursona.owner')));
    }

    public function test_owner_always_sees_everything_including_processing(): void
    {
        $fursona = Fursona::factory()->visibility('private')->nsfw()->create();
        $media = Media::factory()->for($fursona)->processing()->nsfw()->create();

        $this->assertSame('show', $this->vis->mediaState($fursona->owner, $media));
        $this->assertSame('show', $this->vis->fursonaState($fursona->owner, $fursona));
    }

    public function test_private_is_invisible_even_with_share_link(): void
    {
        $fursona = Fursona::factory()->visibility('private')->create();
        $media = Media::factory()->for($fursona)->create();
        $link = ShareLink::create(['fursona_id' => $fursona->id, 'slug' => 'privatelnk', 'watermark' => false]);

        $this->assertNull($this->vis->fursonaState(null, $fursona, $link));
        $this->assertNull($this->vis->mediaState(User::factory()->create(), $media->fresh('fursona.owner'), $link));
    }

    public function test_unlisted_requires_valid_share_link(): void
    {
        $fursona = Fursona::factory()->visibility('unlisted')->create();
        $media = Media::factory()->for($fursona)->create();
        $link = ShareLink::create(['fursona_id' => $fursona->id, 'slug' => 'unlisted01', 'watermark' => false]);
        $otherLink = ShareLink::create(['fursona_id' => Fursona::factory()->create()->id, 'slug' => 'otherlink1', 'watermark' => false]);

        $this->assertNull($this->vis->fursonaState(null, $fursona));
        $this->assertNull($this->vis->fursonaState(null, $fursona, $otherLink));
        $this->assertSame('show', $this->vis->fursonaState(null, $fursona, $link));
        $this->assertSame('show', $this->vis->mediaState(null, $media->fresh('fursona.owner'), $link));

        $link->forceFill(['revoked_at' => now()])->save();
        $this->assertNull($this->vis->fursonaState(null, $fursona, $link->fresh()));
    }

    public function test_media_override_can_be_stricter_than_fursona(): void
    {
        $fursona = Fursona::factory()->visibility('public')->create();
        $private = Media::factory()->for($fursona)->override('private')->create();
        $unlisted = Media::factory()->for($fursona)->override('unlisted')->create();
        $link = ShareLink::create(['fursona_id' => $fursona->id, 'slug' => 'publicfurs', 'watermark' => false]);

        $this->assertNull($this->vis->mediaState(null, $private->fresh('fursona.owner')));
        $this->assertNull($this->vis->mediaState(null, $unlisted->fresh('fursona.owner')));
        $this->assertSame('show', $this->vis->mediaState(null, $unlisted->fresh('fursona.owner'), $link));
    }

    public function test_nsfw_fursona_hides_all_media_from_guests(): void
    {
        $fursona = Fursona::factory()->nsfw()->create();
        $sfwMedia = Media::factory()->for($fursona)->create(['is_nsfw' => false]);

        $this->assertNull($this->vis->fursonaState(null, $fursona));
        $this->assertNull($this->vis->mediaState(null, $sfwMedia->fresh('fursona.owner')));
        $this->assertSame('blur', $this->vis->mediaState(User::factory()->adult('blur')->create(), $sfwMedia->fresh('fursona.owner')));
    }

    public function test_removed_or_banned_content_is_hidden_and_nsfw_hidden_count_is_reported(): void
    {
        $fursona = Fursona::factory()->create();
        $sfw = Media::factory()->for($fursona)->create();
        $nsfw = Media::factory()->for($fursona)->nsfw()->create();
        $removed = Media::factory()->for($fursona)->create(['status' => 'removed']);

        $result = $this->vis->filterMedia(null, $fursona->media()->with('fursona.owner')->get());
        $this->assertSame([$sfw->id], $result['visible']->pluck('id')->all());
        $this->assertSame(1, $result['hidden_nsfw']);

        $fursona->forceFill(['removed_at' => now()])->save();
        $this->assertNull($this->vis->fursonaState(null, $fursona->fresh()));

        $fursona->forceFill(['removed_at' => null])->save();
        $fursona->owner->forceFill(['is_banned' => true])->save();
        $this->assertNull($this->vis->fursonaState(null, $fursona->fresh('owner')));
    }
}
