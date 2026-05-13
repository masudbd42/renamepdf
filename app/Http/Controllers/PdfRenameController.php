<?php

namespace App\Http\Controllers;

use App\Jobs\RenamePdfJob;
use App\Models\PdfLog;
use App\Services\PdfProcessorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PdfRenameController extends Controller
{
    public function __construct(
        private readonly PdfProcessorService $processorService,
    ) {}

    public function index(): View
    {
        $logs = PdfLog::latest()->get();

        return view('pdf.index', compact('logs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'pdfs' => ['required', 'array', 'min:1'],
            'pdfs.*' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        foreach ($request->file('pdfs') as $file) {
            if ($file->getMimeType() !== 'application/pdf') {
                continue;
            }

            $storedPath = $file->store('pdf_uploads', 'local');

            $log = PdfLog::create([
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'status' => 'pending',
            ]);

            RenamePdfJob::dispatch($log->id);
        }

        return redirect()->route('pdf.index')
            ->with('success', 'PDFs uploaded and queued for processing.');
    }

    public function update(Request $request, PdfLog $pdfLog): RedirectResponse
    {
        $request->validate([
            'detected_title' => ['nullable', 'string', 'max:255'],
            'detected_year' => ['nullable', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'clean_title' => ['nullable', 'string', 'max:200'],
        ]);

        $pdfLog->update($request->only(['detected_title', 'detected_year', 'clean_title']));

        return redirect()->route('pdf.index')
            ->with('success', 'Metadata updated successfully.');
    }

    public function process(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);

        $query = PdfLog::query();

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $logs = $query->where('status', 'completed')->get();

        foreach ($logs as $log) {
            $this->processorService->rename($log);
        }

        // Re-queue any pending/failed items
        PdfLog::whereIn('status', ['pending', 'failed'])->each(function (PdfLog $log) {
            $log->update(['status' => 'pending', 'error_message' => null]);
            RenamePdfJob::dispatch($log->id);
        });

        return redirect()->route('pdf.index')
            ->with('success', 'Processing triggered. ZIP will be available shortly.');
    }

    public function download(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $logs = PdfLog::where('status', 'completed')
            ->whereNotNull('renamed_path')
            ->get();

        if ($logs->isEmpty()) {
            abort(404, 'No renamed files available for download.');
        }

        $zipPath = $this->processorService->buildZip($logs);

        return response()->download($zipPath, 'renamed_pdfs.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function destroy(PdfLog $pdfLog): RedirectResponse
    {
        if ($pdfLog->stored_path) {
            Storage::disk('local')->delete($pdfLog->stored_path);
        }
        if ($pdfLog->renamed_path) {
            Storage::disk('local')->delete($pdfLog->renamed_path);
        }

        $pdfLog->delete();

        return redirect()->route('pdf.index')
            ->with('success', 'Entry deleted.');
    }
}
