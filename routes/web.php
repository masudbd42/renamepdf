<?php

use App\Http\Controllers\PdfRenameController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PdfRenameController::class, 'index'])->name('pdf.index');
Route::post('/upload', [PdfRenameController::class, 'store'])->name('pdf.store');
Route::patch('/pdf/{pdfLog}', [PdfRenameController::class, 'update'])->name('pdf.update');
Route::post('/process', [PdfRenameController::class, 'process'])->name('pdf.process');
Route::get('/download', [PdfRenameController::class, 'download'])->name('pdf.download');
Route::delete('/pdf/{pdfLog}', [PdfRenameController::class, 'destroy'])->name('pdf.destroy');
