<?php

namespace App\Services;

use App\Models\PdfLog;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfProcessorService
{
    public function __construct(
        private readonly PdfMetadataService $metadataService,
        private readonly Parser $parser,
    ) {}

    public function process(PdfLog $log): void
    {
        $log->update(['status' => 'processing']);

        try {
            $text = $this->extractText($log->stored_path);

            if (empty(trim($text))) {
                throw new \RuntimeException('No extractable text found (possibly a scanned image PDF).');
            }

            $log->update(['extracted_text' => mb_substr($text, 0, 5000)]);

            $metadata = $this->metadataService->extract($text);

            $detectedTitle = $metadata['title'] ?? null;
            $detectedYear = $metadata['year'] ?? null;
            $cleanTitle = $detectedTitle ? $this->metadataService->cleanTitle($detectedTitle) : 'Untitled';

            $log->update([
                'detected_title' => $detectedTitle,
                'detected_year' => $detectedYear,
                'clean_title' => $cleanTitle,
                'status' => 'completed',
            ]);
        } catch (\Throwable $e) {
            Log::error('PDF processing failed.', ['pdf_log_id' => $log->id, 'error' => $e->getMessage()]);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function extractText(string $storedPath): string
    {
        $absolutePath = Storage::path($storedPath);

        $pdf = $this->parser->parseFile($absolutePath);
        $pages = $pdf->getPages();

        $text = '';
        $limit = min(2, count($pages));

        for ($i = 0; $i < $limit; $i++) {
            $text .= $pages[$i]->getText() . "\n";
        }

        return $text;
    }

    public function rename(PdfLog $log): string
    {
        $year = $log->detected_year ?? 'Unknown';
        $title = $log->clean_title ?? $log->detected_title ?? 'Untitled';
        $filename = "{$year} - {$title}.pdf";

        $renamedPath = 'pdf_renamed/' . $filename;

        Storage::copy($log->stored_path, $renamedPath);
        $log->update(['renamed_path' => $renamedPath]);

        return $filename;
    }

    public function buildZip(iterable $logs): string
    {
        $zipPath = storage_path('app/private/pdf_renamed/renamed_files.zip');
        $dir = dirname($zipPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive.');
        }

        foreach ($logs as $log) {
            if ($log->renamed_path && Storage::exists($log->renamed_path)) {
                $zip->addFile(Storage::path($log->renamed_path), basename($log->renamed_path));
            }
        }

        $zip->close();

        return $zipPath;
    }
}
