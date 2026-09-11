<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\AdminAction;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['status' => ['sometimes', Rule::in([...Report::STATUSES, 'all'])]]);
        $status = $data['status'] ?? 'open';

        $reports = Report::with(['reporter', 'resolver'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            // 紅線內容優先，其餘照時間
            ->orderByRaw("case when reason_code = 'illegal' then 0 else 1 end")
            ->orderByDesc('created_at')
            ->paginate(50);

        return ReportResource::collection($reports);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'open' => Report::where('status', 'open')->count(),
            'open_illegal' => Report::where('status', 'open')->where('reason_code', 'illegal')->count(),
            'resolved_this_week' => Report::where('status', '!=', 'open')->where('resolved_at', '>=', now()->startOfWeek())->count(),
            'banned_users' => User::where('is_banned', true)->count(),
            'recent_actions' => AdminAction::with('admin')->latest('created_at')->limit(30)->get()->map(fn (AdminAction $a) => [
                'id' => $a->id,
                'admin' => $a->admin?->pawfit_id ?? $a->admin?->email,
                'action' => $a->action,
                'target_type' => $a->target_type,
                'target_id' => $a->target_id,
                'note' => $a->note,
                'created_at' => $a->created_at,
            ]),
        ]);
    }
}
