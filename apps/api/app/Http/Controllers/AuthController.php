<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Google OAuth（Authorization Code）＋ Sanctum cookie session。
 * 不用 Socialite：其相依的 Guzzle 版本與 Laravel 13 衝突，且流程只需兩個 HTTP 呼叫。
 */
class AuthController extends Controller
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        abort_if(blank(config('services.google.client_id')), 503, __('messages.auth.google_not_configured'));

        $state = Str::random(40);
        $request->session()->put('oauth_state', $state);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away(self::AUTH_URL.'?'.$query);
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $front = config('pawfit.frontend_url');
        $expected = $request->session()->pull('oauth_state');

        if ($request->filled('error') || ! $request->filled('code') || ! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->away($front.'/login?error=oauth');
        }

        $token = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $request->query('code'),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);
        if ($token->failed() || ! $token->json('access_token')) {
            return redirect()->away($front.'/login?error=oauth');
        }

        $info = Http::withToken($token->json('access_token'))->get(self::USERINFO_URL);
        if ($info->failed() || ! $info->json('sub') || ! $info->json('email')) {
            return redirect()->away($front.'/login?error=oauth');
        }

        $user = $this->upsertGoogleUser($info->json());
        if ($user->is_banned) {
            Auth::logout();

            return redirect()->away($front.'/login?error=banned');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->away($front.($user->isOnboarded() ? '/dashboard' : '/onboarding'));
    }

    /**
     * 本機開發用：沒有 Google 憑證時直接以 email 登入。
     * 只在 APP_ENV=local 且 FEATURE_DEV_LOGIN=true 時存在。
     */
    public function devLogin(Request $request): JsonResponse
    {
        abort_unless(app()->isLocal() && config('pawfit.features.dev_login'), 404);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        $user = User::firstOrCreate(
            ['email' => strtolower($data['email'])],
            ['name' => $data['name'] ?? Str::before($data['email'], '@')],
        );
        $user->forceFill(['last_login_at' => now()])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json(['user' => new UserResource($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    private function upsertGoogleUser(array $info): User
    {
        $user = User::where('google_id', $info['sub'])->first()
            ?? User::where('email', strtolower($info['email']))->first()
            ?? new User;

        $user->forceFill([
            'google_id' => $info['sub'],
            'email' => strtolower($info['email']),
            'name' => $user->name ?? ($info['name'] ?? null),
            'google_avatar_url' => $info['picture'] ?? $user->google_avatar_url,
            'last_login_at' => now(),
        ])->save();

        return $user;
    }
}
