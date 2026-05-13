<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfLog extends Model
{
    protected $fillable = [
        'original_name',
        'stored_path',
        'extracted_text',
        'detected_title',
        'detected_year',
        'clean_title',
        'renamed_path',
        'status',
        'error_message',
    ];

    public function getRenamedFilenameAttribute(): string
    {
        $year = $this->detected_year ?? 'Unknown';
        $title = $this->clean_title ?? $this->detected_title ?? 'Untitled';

        return "{$year} - {$title}.pdf";
    }
}
