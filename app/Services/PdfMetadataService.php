<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PdfMetadataService
{
    public function extract(string $text): array
    {
        $text = mb_substr($text, 0, 3000);

        if (config('services.openai.api_key')) {
            try {
                return $this->extractViaOpenAI($text);
            } catch (\Throwable $e) {
                Log::warning('OpenAI metadata extraction failed, falling back to regex.', ['error' => $e->getMessage()]);
            }
        }

        if (config('services.gemini.api_key')) {
            try {
                return $this->extractViaGemini($text);
            } catch (\Throwable $e) {
                Log::warning('Gemini metadata extraction failed, falling back to regex.', ['error' => $e->getMessage()]);
            }
        }

        return $this->extractViaRegex($text);
    }

    private function extractViaOpenAI(string $text): array
    {
        $prompt = $this->buildPrompt($text);

        $response = Http::withToken(config('services.openai.api_key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You extract metadata from academic PDFs. Respond ONLY with JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0,
                'max_tokens' => 200,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->status());
        }

        $content = $response->json('choices.0.message.content', '');

        return $this->parseJsonResponse($content);
    }

    private function extractViaGemini(string $text): array
    {
        $prompt = $this->buildPrompt($text);
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-1.5-flash');

        $response = Http::timeout(30)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => ['maxOutputTokens' => 200, 'temperature' => 0],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: ' . $response->status());
        }

        $content = $response->json('candidates.0.content.parts.0.text', '');

        return $this->parseJsonResponse($content);
    }

    private function buildPrompt(string $text): string
    {
        return <<<PROMPT
            Extract the document title and publication year from the following text from the first pages of an academic PDF.
            Return ONLY valid JSON in this format: {"title": "...", "year": "YYYY"}
            If you cannot determine a value, use null.

            Text:
            {$text}
        PROMPT;
    }

    private function parseJsonResponse(string $content): array
    {
        $content = trim($content);

        // Strip markdown code fences if present
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw new \RuntimeException('Could not parse LLM JSON response.');
        }

        return [
            'title' => isset($data['title']) && $data['title'] !== null ? (string) $data['title'] : null,
            'year' => isset($data['year']) && $data['year'] !== null ? (string) $data['year'] : null,
        ];
    }

    public function extractViaRegex(string $text): array
    {
        return [
            'title' => $this->detectTitleByRegex($text),
            'year' => $this->detectYearByRegex($text),
        ];
    }

    private function detectYearByRegex(string $text): ?string
    {
        // Look for a 4-digit year in range 1900–2099
        if (preg_match('/\b(19\d{2}|20\d{2})\b/', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    private function detectTitleByRegex(string $text): ?string
    {
        $lines = preg_split('/\r?\n/', $text);
        $candidates = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Skip very short, very long, or lines that look like boilerplate
            if (mb_strlen($line) < 10 || mb_strlen($line) > 200) {
                continue;
            }
            if (preg_match('/^\d+$|^abstract$|^introduction$|^\s*\d+\s*$/i', $line)) {
                continue;
            }
            $candidates[] = $line;
        }

        // Return the first reasonable candidate
        return $candidates[0] ?? null;
    }

    public function cleanTitle(string $title): string
    {
        // Remove characters that are unsafe for filenames
        $clean = preg_replace('/[\/\\\\:*?"<>|]/', '', $title);
        // Replace multiple spaces/underscores with a single space
        $clean = preg_replace('/\s+/', ' ', $clean);
        // Trim and limit to 120 characters
        $clean = mb_substr(trim($clean), 0, 120);

        return $clean ?: 'Untitled';
    }
}
