<?php

namespace App\Jobs;

use App\Models\CommissionKit;
use App\Services\CommissionSheet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** 產生委託需求單合成圖（FR-6.3），完成後把需求單狀態設為 active。 */
class GenerateCommissionSheet implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(public CommissionKit $kit) {}

    public function handle(CommissionSheet $sheet): void
    {
        $kit = $this->kit->fresh(['fursona.owner']);
        if (! $kit || ! $kit->isActive()) {
            return;
        }

        try {
            $key = "commission/{$kit->owner_id}/{$kit->id}/sheet-{$kit->slug}.webp";
            Storage::disk('s3')->put($key, $sheet->render($kit));

            $old = $kit->sheet_key;
            $kit->forceFill(['sheet_key' => $key, 'status' => 'active'])->save();
            if ($old && $old !== $key) {
                Storage::disk('s3')->delete($old);
            }
        } catch (Throwable $e) {
            Log::error('commission sheet failed', ['kit' => $kit->id, 'error' => $e->getMessage()]);
            $kit->forceFill(['status' => 'failed'])->save();
            throw $e;
        }
    }
}
