<?php

namespace Database\Seeders;

use App\Jobs\ProcessMedia;
use App\Models\Fursona;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Imagick as ImagickAlias;

/**
 * 示範資料：一位用戶、一隻獸設、四張以 Imagick 產生的漸層圖（含一張 NSFW）與固定 slug 的分享連結。
 * 首頁「看示範分享頁」會連到 /s/demoEmber1。
 *
 * 執行：docker compose exec api php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public const SLUG = 'demoEmber1';

    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@pawfit.local'],
            [
                'name' => 'Ash 灰灰',
                'pawfit_id' => 'firefox_ash',
                'display_name' => 'Ash 灰灰',
                'tos_accepted_at' => now(),
                'adult_confirmed_at' => now(),
                'nsfw_pref' => 'blur',
                // 示範嵌入與公開 API（M6）：/embed/demoEmber1、/api/v1/public/users/firefox_ash
                'allow_embed_api' => true,
            ],
        );

        $fursona = Fursona::updateOrCreate(
            ['owner_id' => $user->id, 'name' => '阿燼 Ember'],
            [
                'species' => '赤狐',
                'bio' => "山腳小鎮的篝火看守人。尾尖那撮白毛是小時候被雪埋過的紀念，畫的時候請保留。\n\n性格外冷內熱，慣用左手；左耳有一道舊傷缺口，正面圖較明顯。",
                'tags' => ['犬科', '赤狐', '標準體型', '成年', '篝火'],
                'palette' => [
                    ['hex' => '#D9642A', 'name' => '主毛色', 'note' => '背部、頭頂、四肢外側', 'sort' => 0],
                    ['hex' => '#F4E7D3', 'name' => '腹毛', 'note' => '胸腹、下顎、尾腹側', 'sort' => 1],
                    ['hex' => '#2B2420', 'name' => '耳尖 / 手足', 'note' => '四肢末端漸層至此', 'sort' => 2],
                    ['hex' => '#7FB7A3', 'name' => '眼睛', 'note' => '虹膜；瞳孔為圓形', 'sort' => 3],
                    ['hex' => '#4A2E2A', 'name' => '爪墊 / 鼻', 'note' => '', 'sort' => 4],
                    ['hex' => '#F1EEE8', 'name' => '尾尖', 'note' => '約尾長 1/5', 'sort' => 5],
                ],
                'visibility' => 'public',
                'is_representative' => true,
            ],
        );

        if ($fursona->media()->count() === 0) {
            $pictures = [
                ['正面設定圖', 'art2d', false, '@kuro_lines', ['#D9642A', '#F4E7D3']],
                ['背面設定圖', 'art2d', false, '@kuro_lines', ['#2B2420', '#D9642A']],
                ['VRM 模型截圖', 'model3d', false, '@poly_den', ['#F4E7D3', '#7FB7A3']],
                ['毛裝頭部試戴', 'photo', false, 'FuzzWorks', ['#8fa3b8', '#4A2E2A']],
                ['委託成品（成人向）', 'art2d', true, '@late_night_fox', ['#D9642A', '#4A2E2A']],
            ];
            foreach ($pictures as $i => [$caption, $kind, $nsfw, $credit, $colors]) {
                $key = "media/{$user->id}/{$fursona->id}/demo-{$i}.png";
                Storage::disk('s3')->put($key, $this->gradientPng($colors[0], $colors[1], $caption));

                $media = $fursona->media()->create([
                    'owner_id' => $user->id,
                    'kind' => $kind,
                    'storage_key' => $key,
                    'bytes' => Storage::disk('s3')->size($key),
                    'is_nsfw' => $nsfw,
                    'caption' => $caption,
                    'credit_name' => $credit,
                    'sort_order' => $i,
                    'status' => 'processing',
                ]);
                ProcessMedia::dispatchSync($media);
            }
            $fursona->forceFill(['avatar_media_id' => $fursona->media()->first()->id])->save();
        }

        ShareLink::updateOrCreate(
            ['slug' => self::SLUG],
            ['fursona_id' => $fursona->id, 'watermark' => false, 'revoked_at' => null],
        );

        $this->command?->info('示範分享頁：'.config('pawfit.frontend_url').'/s/'.self::SLUG);
    }

    private function gradientPng(string $from, string $to, string $label): string
    {
        $im = new Imagick;
        $im->newPseudoImage(1600, 1200, "gradient:{$from}-{$to}");
        $im->setImageFormat('png');

        $draw = new \ImagickDraw;
        $draw->setFillColor('rgba(255,255,255,0.85)');
        $draw->setFontSize(72);
        $draw->setGravity(ImagickAlias::GRAVITY_CENTER);
        $font = config('pawfit.media.watermark_font');
        if (is_file($font)) {
            $draw->setFont($font);
        }
        // 只畫 ASCII 標籤，避免示範字型缺中文字元
        $im->annotateImage($draw, 0, 0, 0, 'Pawfit demo #'.substr(md5($label), 0, 4));

        return $im->getImageBlob();
    }
}
