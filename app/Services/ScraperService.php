<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\ScrapeSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ScraperService
{
    /**
     * Base URL pattern for fetching phone details.
     */
    private const PHONE_URL = 'https://www.quickerala.com/business/{viewNumber}/phone';

    /**
     * Millisecond timestamp for cache-busting (matches the format in the spec).
     */
    private function getTimestamp(): string
    {
        return (string)(time() * 1000);
    }

    /**
     * Run the Puppeteer scraper to extract list cards from the target URL.
     * Returns an array of ['viewNumber', 'addressId', 'title'].
     */
    public function extractCardsViaPuppeteer(string $targetUrl, int $maxScrolls = 50): array
    {
        $scraperPath = base_path('scraper/scraper.js');

        if (!file_exists($scraperPath)) {
            throw new \RuntimeException("Puppeteer scraper not found at: {$scraperPath}");
        }

        $process = new Process([
            'node',
            $scraperPath,
            $targetUrl,
            (string)$maxScrolls,
        ]);

        $process->setTimeout(300); // 5-minute timeout
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Puppeteer scraper failed', [
                'stderr' => $process->getErrorOutput(),
            ]);
            throw new ProcessFailedException($process);
        }

        $output = trim($process->getOutput());
        $cards = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON from scraper: ' . json_last_error_msg());
        }

        return $cards ?? [];
    }

    /**
     * Fetch contact details for a single card from the QuicKerala API.
     */
    public function fetchContactDetails(string $viewNumber, string $addressId): array
    {
        $url = str_replace('{viewNumber}', $viewNumber, self::PHONE_URL);

        $response = Http::timeout(15)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer' => 'https://www.quickerala.com/',
            ])
            ->get($url, [
                'addressId' => $addressId,
                '_' => $this->getTimestamp(),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "HTTP {$response->status()} for viewNumber={$viewNumber}"
            );
        }

        return $response->json();
    }

    /**
     * Parse the contact details JSON response into a normalized array.
     */
    public function parseContactResponse(array $data): array
    {
        return [
            'business_name' => $data['businessName'] ?? null,
            'whatsapp_enabled' => (bool)($data['whatsAppEnabled'] ?? false),
            'mobile' => $data['data']['mobile']['label'] ?? null,
            'mobile_formatted' => $data['data']['mobile']['valueFormatted'] ?? null,
            'raw_response' => json_encode($data),
        ];
    }

    /**
     * Persist a batch of extracted card stubs (without contact info yet).
     * Uses upsert to avoid duplicates.
     */
    public function persistCardStubs(array $cards): int
    {
        $saved = 0;
        foreach ($cards as $card) {
            Lead::updateOrCreate(
                ['view_number' => $card['viewNumber']],
                [
                    'address_id' => $card['addressId'] ?? null,
                    'title' => $card['title'] ?? 'Unknown',
                    'status' => 'pending',
                ]
            );
            $saved++;
        }
        return $saved;
    }

    /**
     * Process a single lead: fetch contact info and update the record.
     */
    public function processLead(Lead $lead): bool
    {
        try {
            $data = $this->fetchContactDetails(
                $lead->view_number,
                $lead->address_id ?? ''
            );

            if (($data['status'] ?? '') !== 'success') {
                $lead->update(['status' => 'failed']);
                return false;
            }

            $parsed = $this->parseContactResponse($data);
            $lead->update(array_merge($parsed, ['status' => 'fetched']));

            return true;
        } catch (\Throwable $e) {
            Log::warning("Failed to fetch lead {$lead->view_number}: {$e->getMessage()}");
            $lead->update(['status' => 'failed']);
            return false;
        }
    }

    /**
     * Process all pending leads with rate limiting.
     *
     * @param  int  $delayMs  Delay between requests in milliseconds (default 500ms)
     */
    public function processAllPending(ScrapeSession $session, int $delayMs = 500): void
    {
        $pending = Lead::pending()->get();

        foreach ($pending as $lead) {
            $success = $this->processLead($lead);

            if ($success) {
                $session->increment('total_processed');
                $session->appendLog("✓ {$lead->title} → {$lead->mobile_formatted}");
            } else {
                $session->increment('total_failed');
                $session->appendLog("✗ {$lead->title} (viewNumber={$lead->view_number}) — failed");
            }

            // Polite rate limiting
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }
    }

    /**
     * Export leads to CSV string.
     */
    public function exportToCsv(iterable $leads): string
    {
        $handle = fopen('php://temp', 'r+');

        // Headers
        fputcsv($handle, [
            'ID', 'View Number', 'Address ID', 'Title',
            'Business Name', 'Mobile', 'Mobile Formatted',
            'WhatsApp Enabled', 'Status', 'Created At',
        ]);

        foreach ($leads as $lead) {
            fputcsv($handle, [
                $lead->id,
                $lead->view_number,
                $lead->address_id,
                $lead->title,
                $lead->business_name,
                $lead->mobile,
                $lead->mobile_formatted,
                $lead->whatsapp_enabled ? 'Yes' : 'No',
                $lead->status,
                $lead->created_at,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
