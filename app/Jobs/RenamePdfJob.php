<?php

namespace App\Jobs;

use App\Models\PdfLog;
use App\Services\PdfProcessorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RenamePdfJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $pdfLogId,
    ) {}

    public function handle(PdfProcessorService $processorService): void
    {
        $log = PdfLog::findOrFail($this->pdfLogId);

        $processorService->process($log);

        $freshLog = $log->fresh();

        if ($freshLog->status === 'completed') {
            $processorService->rename($freshLog);
        }
    }

    public function failed(\Throwable $exception): void
    {
        PdfLog::where('id', $this->pdfLogId)
            ->whereNotIn('status', ['completed'])
            ->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
    }
}
