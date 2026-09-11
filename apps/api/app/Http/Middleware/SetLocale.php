<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 決定這次請求的語言（回應訊息、驗證錯誤用）：
 *   1. 登入者的 users.locale
 *   2. Accept-Language（前端 useApi 會帶目前 UI 語言）
 *   3. config('pawfit.locales.default')
 *
 * 前端用 BCP 47（zh-TW），Laravel lang 目錄用底線（zh_TW），這裡負責轉換。
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('pawfit.locales.supported', ['zh-TW']);
        $default = config('pawfit.locales.default', 'zh-TW');

        $locale = $request->user()?->locale;
        if (! in_array($locale, $supported, true)) {
            // Symfony 會把結果轉成底線形式（zh_TW），轉回 BCP 47 再比對
            $preferred = $request->getPreferredLanguage($supported);
            $locale = $preferred !== null ? self::toBcp47($preferred) : null;
            if (! in_array($locale, $supported, true)) {
                $locale = $default;
            }
        }

        app()->setLocale(self::toLaravel($locale));

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    /** zh-TW → zh_TW（lang/ 目錄命名） */
    public static function toLaravel(string $bcp47): string
    {
        return str_replace('-', '_', $bcp47);
    }

    /** zh_TW → zh-TW（給前端 / Content-Language） */
    public static function toBcp47(string $laravel): string
    {
        return str_replace('_', '-', $laravel);
    }
}
