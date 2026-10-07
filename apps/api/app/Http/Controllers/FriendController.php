<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicUserResource;
use App\Models\Block;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 好友與封鎖（Phase 2 FR-B1）。
 * 邀請以 Pawfit ID 送出、接受制；封鎖會解除既有好友與待處理邀請，且雙方互不可見（可見性在 Visibility）。
 */
class FriendController extends Controller
{
    /** GET /api/friends：好友、待我處理、我送出的、我封鎖的。 */
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();
        $rows = Friendship::involving($me->id)->with(['userA.avatarMedia', 'userB.avatarMedia'])->orderByDesc('created_at')->get();

        $friends = [];
        $incoming = [];
        $outgoing = [];
        foreach ($rows as $f) {
            $other = $f->user_a === $me->id ? $f->userB : $f->userA;
            if ($other->is_banned) {
                continue;
            }
            $entry = ['friendship_id' => $f->id, 'user' => new PublicUserResource($other), 'created_at' => $f->created_at];
            if ($f->isAccepted()) {
                $friends[] = $entry + ['accepted_at' => $f->accepted_at];
            } elseif ($f->requested_by === $me->id) {
                $outgoing[] = $entry;
            } else {
                $incoming[] = $entry;
            }
        }

        $blocked = Block::where('blocker_id', $me->id)->with('blocked.avatarMedia')->orderByDesc('created_at')->get()
            ->map(fn (Block $b) => ['user' => new PublicUserResource($b->blocked), 'created_at' => $b->created_at]);

        return response()->json([
            'friends' => $friends,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'blocked' => $blocked,
        ]);
    }

    /** POST /api/friends/requests {pawfit_id}：送出邀請；對方已先邀請我則直接成為好友。 */
    public function request(Request $request): JsonResponse
    {
        $me = $request->user();
        $data = $request->validate(['pawfit_id' => ['required', 'string', 'max:20']]);

        $target = User::whereRaw('lower(pawfit_id) = ?', [strtolower($data['pawfit_id'])])->first();
        if (! $target || $target->is_banned || ! $target->isOnboarded()) {
            throw ValidationException::withMessages(['pawfit_id' => __('messages.friends.user_not_found')]);
        }
        if ($target->id === $me->id) {
            throw ValidationException::withMessages(['pawfit_id' => __('messages.friends.self')]);
        }
        // 任一方封鎖：對送出者一律回「找不到」，不洩漏封鎖狀態
        if (Block::eitherWay($me->id, $target->id)) {
            throw ValidationException::withMessages(['pawfit_id' => __('messages.friends.user_not_found')]);
        }

        $existing = Friendship::between($me->id, $target->id);
        if ($existing?->isAccepted()) {
            throw ValidationException::withMessages(['pawfit_id' => __('messages.friends.already_friends')]);
        }
        if ($existing && $existing->requested_by === $me->id) {
            throw ValidationException::withMessages(['pawfit_id' => __('messages.friends.already_requested')]);
        }
        if ($existing) {
            // 對方先邀請我 → 視為接受
            $existing->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();

            return response()->json(['status' => 'friends', 'friendship_id' => $existing->id, 'user' => new PublicUserResource($target)]);
        }

        [$a, $b] = Friendship::pair($me->id, $target->id);
        $f = Friendship::create(['user_a' => $a, 'user_b' => $b, 'status' => 'pending', 'requested_by' => $me->id, 'created_at' => now()]);

        return response()->json(['status' => 'pending_out', 'friendship_id' => $f->id, 'user' => new PublicUserResource($target)], 201);
    }

    /** POST /api/friends/requests/{friendship}/accept：只有被邀請方能接受。 */
    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        $me = $request->user();
        abort_unless(in_array($me->id, [$friendship->user_a, $friendship->user_b], true), 404);
        abort_if($friendship->isAccepted(), 409, __('messages.friends.already_friends'));
        abort_if($friendship->requested_by === $me->id, 403, __('messages.friends.cannot_accept_own'));

        $friendship->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();

        return response()->json(['status' => 'friends', 'friendship_id' => $friendship->id]);
    }

    /** DELETE /api/friends/requests/{friendship}：取消我送出的或拒絕收到的邀請。 */
    public function cancel(Request $request, Friendship $friendship): JsonResponse
    {
        $me = $request->user();
        abort_unless(in_array($me->id, [$friendship->user_a, $friendship->user_b], true), 404);
        abort_if($friendship->isAccepted(), 409, __('messages.friends.use_remove'));
        $friendship->delete();

        return response()->json(null, 204);
    }

    /** DELETE /api/friends/{user}：解除好友（雙方皆可）。 */
    public function remove(Request $request, User $user): JsonResponse
    {
        $f = Friendship::between($request->user()->id, $user->id);
        abort_if(! $f || ! $f->isAccepted(), 404);
        $f->delete();

        return response()->json(null, 204);
    }

    /** POST /api/blocks/{user}：封鎖，並清掉既有好友關係與邀請。 */
    public function block(Request $request, User $user): JsonResponse
    {
        $me = $request->user();
        abort_if($user->id === $me->id, 422, __('messages.friends.self'));

        DB::transaction(function () use ($me, $user) {
            Block::firstOrCreate(['blocker_id' => $me->id, 'blocked_id' => $user->id], ['created_at' => now()]);
            Friendship::between($me->id, $user->id)?->delete();
        });

        return response()->json(['status' => 'blocked'], 201);
    }

    /** DELETE /api/blocks/{user}：解除封鎖（不會自動恢復好友）。 */
    public function unblock(Request $request, User $user): JsonResponse
    {
        Block::where('blocker_id', $request->user()->id)->where('blocked_id', $user->id)->delete();

        return response()->json(null, 204);
    }
}
