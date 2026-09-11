<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\AdminAction;
use App\Models\Fursona;
use App\Models\Media;
use App\Models\Report;
use App\Models\User;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * 管理員操作（FR-5.2、FR-5.3）。每個動作都寫入 admin_actions 稽核紀錄。
 */
class ActionController extends Controller
{
    public const ACTIONS = [
        'remove_media',     // 下架：隱藏，R2 物件保留供申訴
        'purge_media',      // 紅線內容：直接刪除 R2 物件，DB 留紀錄
        'restore_media',
        'mark_nsfw',        // 未標 NSFW → 改標
        'remove_fursona',
        'restore_fursona',
        'ban_user',
        'unban_user',
        'resolve_report',
        'dismiss_report',
    ];

    public function __construct(private readonly MediaStorage $storage) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(self::ACTIONS)],
            'target_id' => ['required', 'uuid'],
            'report_id' => ['nullable', 'uuid'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $admin = $request->user();
        $report = isset($data['report_id']) ? Report::find($data['report_id']) : null;

        DB::transaction(function () use ($admin, $data, $report) {
            [$type, $note] = $this->apply($admin, $data['action'], $data['target_id'], $data['note'] ?? null);
            AdminAction::log($admin, $data['action'], $type, $data['target_id'], $note);

            // 針對內容的處置若附帶檢舉單，一併結案
            if ($report && $report->status === 'open' && ! in_array($data['action'], ['resolve_report', 'dismiss_report'], true)) {
                $report->forceFill(['status' => 'resolved', 'resolved_by' => $admin->id, 'resolved_at' => now()])->save();
            }
        });

        return response()->json([
            'ok' => true,
            'report' => $report ? new ReportResource($report->fresh(['reporter', 'resolver'])) : null,
        ]);
    }

    /** @return array{0: string, 1: ?string} [target_type, note] */
    private function apply(User $admin, string $action, string $targetId, ?string $note): array
    {
        switch ($action) {
            case 'remove_media':
                $m = $this->media($targetId);
                $m->forceFill(['status' => 'removed', 'status_note' => $note ?? __('messages.admin.removed_by_staff')])->save();

                return ['media', $note];

            case 'purge_media':
                $m = $this->media($targetId);
                $this->storage->deleteAll($m);
                $m->forceFill([
                    'status' => 'removed', 'status_note' => $note ?? __('messages.admin.purged_red_line'),
                    'display_key' => null, 'thumb_key' => null, 'watermarked_key' => null,
                ])->save();

                return ['media', ($note ? $note.'；' : '').__('messages.admin.object_deleted')];

            case 'restore_media':
                $m = $this->media($targetId);
                if (! $m->display_key) {
                    throw ValidationException::withMessages(['action' => __('messages.admin.cannot_restore_purged')]);
                }
                $m->forceFill(['status' => 'active', 'status_note' => null])->save();

                return ['media', $note];

            case 'mark_nsfw':
                $m = $this->media($targetId);
                $m->forceFill(['is_nsfw' => true])->save();

                return ['media', $note ?? __('messages.admin.marked_nsfw')];

            case 'remove_fursona':
                $f = Fursona::findOrFail($targetId);
                $f->forceFill(['removed_at' => now()])->save();

                return ['fursona', $note ?? __('messages.admin.removed_by_staff')];

            case 'restore_fursona':
                $f = Fursona::findOrFail($targetId);
                $f->forceFill(['removed_at' => null])->save();

                return ['fursona', $note];

            case 'ban_user':
                $u = User::findOrFail($targetId);
                if ($u->isAdmin()) {
                    throw ValidationException::withMessages(['action' => __('messages.admin.cannot_ban_admin')]);
                }
                $u->forceFill(['is_banned' => true])->save();

                return ['profile', $note];

            case 'unban_user':
                User::findOrFail($targetId)->forceFill(['is_banned' => false])->save();

                return ['profile', $note];

            case 'resolve_report':
            case 'dismiss_report':
                $r = Report::findOrFail($targetId);
                $r->forceFill([
                    'status' => $action === 'resolve_report' ? 'resolved' : 'dismissed',
                    'resolved_by' => $admin->id,
                    'resolved_at' => now(),
                ])->save();

                return ['report', $note];
        }

        throw ValidationException::withMessages(['action' => __('messages.admin.unknown_action')]);
    }

    private function media(string $id): Media
    {
        return Media::findOrFail($id);
    }
}
