<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReportResource;
use App\Models\Fursona;
use App\Models\Media;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(Report::TARGET_TYPES)],
            'target_id' => ['required', 'uuid'],
            'reason_code' => ['required', Rule::in(Report::REASONS)],
            'detail' => ['nullable', 'string', 'max:2000'],
        ]);

        $exists = match ($data['target_type']) {
            'media' => Media::whereKey($data['target_id'])->exists(),
            'fursona' => Fursona::whereKey($data['target_id'])->exists(),
            'profile' => User::whereKey($data['target_id'])->exists(),
        };
        if (! $exists) {
            throw ValidationException::withMessages(['target_id' => __('messages.report.target_not_found')]);
        }

        // 同一人對同一目標的未處理檢舉只留一筆
        $existing = Report::where('reporter_id', $request->user()->id)
            ->where('target_type', $data['target_type'])
            ->where('target_id', $data['target_id'])
            ->where('status', 'open')->first();
        if ($existing) {
            return (new ReportResource($existing))->response();
        }

        $report = Report::create($data + ['reporter_id' => $request->user()->id]);

        return (new ReportResource($report->load('reporter')))->response()->setStatusCode(201);
    }
}
