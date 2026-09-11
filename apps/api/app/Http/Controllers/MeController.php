<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeController extends Controller
{
    /** 保留字：不能當 Pawfit ID，避免與頁面路由衝突。 */
    public const RESERVED_IDS = [
        'admin', 'api', 'login', 'logout', 'onboarding', 'dashboard', 'settings', 'terms', 'privacy',
        'guidelines', 'help', 'about', 'pawfit', 'official', 'support', 'me', 'u', 's', 'img', 'fursona',
    ];

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'pawfit_id' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9_]{3,20}$/', Rule::notIn(self::RESERVED_IDS)],
            'display_name' => ['sometimes', 'string', 'min:1', 'max:40'],
            'nsfw_pref' => ['sometimes', Rule::in(User::NSFW_PREFS)],
            'adult_confirmed' => ['sometimes', 'boolean'],
            'tos_accepted' => ['sometimes', 'accepted'],
            'avatar_media_id' => ['sometimes', 'nullable', 'uuid'],
            'locale' => ['sometimes', 'nullable', Rule::in(config('pawfit.locales.supported'))],
        ], [
            'pawfit_id.regex' => __('messages.me.pawfit_id_format'),
            'pawfit_id.not_in' => __('messages.me.pawfit_id_reserved'),
        ]);

        if (array_key_exists('locale', $data)) {
            $user->locale = $data['locale'];
        }

        if (array_key_exists('pawfit_id', $data)) {
            $this->assignPawfitId($user, $data['pawfit_id']);
        }
        if (array_key_exists('display_name', $data)) {
            $user->display_name = trim($data['display_name']);
        }
        if (array_key_exists('adult_confirmed', $data)) {
            if ($data['adult_confirmed']) {
                $user->adult_confirmed_at ??= now();
            } else {
                $user->adult_confirmed_at = null;
                $user->nsfw_pref = 'hide';
            }
        }
        if (array_key_exists('nsfw_pref', $data)) {
            if ($data['nsfw_pref'] !== 'hide' && ! $user->hasConfirmedAdult()) {
                throw ValidationException::withMessages(['nsfw_pref' => __('messages.me.nsfw_pref_requires_adult')]);
            }
            $user->nsfw_pref = $data['nsfw_pref'];
        }
        if (! empty($data['tos_accepted'])) {
            $user->tos_accepted_at ??= now();
        }
        if (array_key_exists('avatar_media_id', $data)) {
            $this->assignAvatar($user, $data['avatar_media_id']);
        }

        $user->save();

        return new UserResource($user->fresh());
    }

    public function pawfitIdAvailable(Request $request): JsonResponse
    {
        $id = (string) $request->query('pawfit_id', '');
        $valid = (bool) preg_match('/^[A-Za-z0-9_]{3,20}$/', $id) && ! in_array(strtolower($id), self::RESERVED_IDS, true);
        $taken = $valid && User::whereRaw('lower(pawfit_id) = ?', [strtolower($id)])
            ->where('id', '!=', $request->user()->id)->exists();

        return response()->json(['available' => $valid && ! $taken, 'valid' => $valid]);
    }

    private function assignPawfitId(User $user, string $pawfitId): void
    {
        if ($user->pawfit_id !== null && strcasecmp($user->pawfit_id, $pawfitId) !== 0) {
            // R-2 更名政策尚未拍板：Phase 1 先鎖定不可更名
            throw ValidationException::withMessages(['pawfit_id' => __('messages.me.pawfit_id_locked')]);
        }
        $taken = User::whereRaw('lower(pawfit_id) = ?', [strtolower($pawfitId)])->where('id', '!=', $user->id)->exists();
        if ($taken) {
            throw ValidationException::withMessages(['pawfit_id' => __('messages.me.pawfit_id_taken')]);
        }
        $user->pawfit_id = $pawfitId;
    }

    private function assignAvatar(User $user, ?string $mediaId): void
    {
        if ($mediaId === null) {
            $user->avatar_media_id = null;

            return;
        }
        $media = Media::where('owner_id', $user->id)->where('status', 'active')->find($mediaId);
        if (! $media) {
            throw ValidationException::withMessages(['avatar_media_id' => __('messages.me.avatar_not_found')]);
        }
        $user->avatar_media_id = $media->id;
    }
}
