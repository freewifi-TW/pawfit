<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SetLocale：users.locale → Accept-Language → 預設；回應帶 Content-Language。 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_language_en_returns_english_messages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en')
            ->patchJson('/api/me', ['pawfit_id' => 'ab'])
            ->assertStatus(422)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('errors.pawfit_id.0', trans('messages.me.pawfit_id_format', [], 'en'));
    }

    public function test_default_locale_is_zh_tw_without_header(): void
    {
        $user = User::factory()->create();

        // Symfony 的 Request::create() 在測試裡預設會帶 Accept-Language: en-us,en;q=0.5，要明確清掉才算「沒帶 header」
        $this->actingAs($user)
            ->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => ''])
            ->patchJson('/api/me', ['pawfit_id' => 'ab'])
            ->assertStatus(422)
            ->assertHeader('Content-Language', 'zh-TW')
            ->assertJsonPath('errors.pawfit_id.0', trans('messages.me.pawfit_id_format', [], 'zh_TW'));

        // 不支援的語言也退回預設
        $this->actingAs($user)
            ->withHeader('Accept-Language', 'fr')
            ->patchJson('/api/me', ['pawfit_id' => 'ab'])
            ->assertStatus(422)
            ->assertHeader('Content-Language', 'zh-TW')
            ->assertJsonPath('errors.pawfit_id.0', trans('messages.me.pawfit_id_format', [], 'zh_TW'));

        $this->assertNotSame(
            trans('messages.me.pawfit_id_format', [], 'en'),
            trans('messages.me.pawfit_id_format', [], 'zh_TW'),
        );
    }

    public function test_user_locale_overrides_accept_language(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'zh-TW')
            ->patchJson('/api/me', ['pawfit_id' => 'ab'])
            ->assertStatus(422)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('errors.pawfit_id.0', trans('messages.me.pawfit_id_format', [], 'en'));
    }

    public function test_validation_attribute_names_are_translated(): void
    {
        $user = User::factory()->create();

        $en = $this->actingAs($user)->withHeader('Accept-Language', 'en')
            ->patchJson('/api/me', ['display_name' => str_repeat('a', 41)])
            ->assertStatus(422)->json('errors.display_name.0');
        $this->assertStringContainsString(trans('validation.attributes.display_name', [], 'en'), $en);

        $zh = $this->actingAs($user)->withHeader('Accept-Language', 'zh-TW')
            ->patchJson('/api/me', ['display_name' => str_repeat('a', 41)])
            ->assertStatus(422)->json('errors.display_name.0');
        $this->assertStringContainsString(trans('validation.attributes.display_name', [], 'zh_TW'), $zh);
    }

    public function test_locale_can_be_saved_and_must_be_supported(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/me', ['locale' => 'en'])
            ->assertOk()->assertJsonPath('locale', 'en');
        $this->assertSame('en', $user->fresh()->locale);

        $this->actingAs($user)->patchJson('/api/me', ['locale' => 'fr'])
            ->assertStatus(422)->assertJsonValidationErrors(['locale']);
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_messages_files_have_identical_key_structure(): void
    {
        $zh = require base_path('lang/zh_TW/messages.php');
        $en = require base_path('lang/en/messages.php');

        $this->assertSame($this->flattenKeys($zh), $this->flattenKeys($en));
        foreach ($this->flattenKeys($zh) as $key) {
            $this->assertNotSame('', trans('messages.'.$key, [], 'zh_TW'), "zh_TW messages.{$key} 為空");
            $this->assertNotSame('', trans('messages.'.$key, [], 'en'), "en messages.{$key} 為空");
        }
    }

    /** @return list<string> */
    private function flattenKeys(array $arr, string $prefix = ''): array
    {
        $keys = [];
        foreach ($arr as $k => $v) {
            $full = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
            if (is_array($v)) {
                array_push($keys, ...$this->flattenKeys($v, $full));
            } else {
                $keys[] = $full;
            }
        }
        sort($keys);

        return $keys;
    }
}
