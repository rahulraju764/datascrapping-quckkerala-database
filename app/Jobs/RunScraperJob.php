<?php

namespace App\Jobs;

use App\Models\ScrapeSession;
use App\Services\ScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunScraperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10-minute job timeout
    public int $tries = 1;

    public function __construct(
        private string $url,
        private int $maxScrolls = 50,
        private int $delayMs = 500,
    ) {}

    public function handle(ScraperService $scraper): void
    {
        $session = ScrapeSession::create([
            'url' => $this->url,
            'status' => 'running',
        ]);

        Log::info("ScraperJob started: session #{$session->id}, url={$this->url}");

        try {
            // ── Step 1: Extract cards ─────────────────────────────────────
            $session->appendLog("Starting Puppeteer extraction for: {$this->url}");
            $cards = $scraper->extractCardsViaPuppeteer($this->url, $this->maxScrolls);

            if (empty($cards)) {
                $session->update(['status' => 'failed']);
                $session->appendLog('No cards found on the page.');
                return;
            }

            $saved = $scraper->persistCardStubs($cards);
            $session->update(['total_found' => $saved]);
            $session->appendLog("Saved {$saved} card stubs to database.");

            // ── Step 2: Fetch contacts ────────────────────────────────────
            $session->appendLog("Fetching contact details with {$this->delayMs}ms delay...");
            $scraper->processAllPending($session, $this->delayMs);

            $session->update(['status' => 'completed']);
            $session->appendLog('✅ Session completed successfully.');

        } catch (\Throwable $e) {
            Log::error("ScraperJob failed: {$e->getMessage()}", ['exception' => $e]);
            $session->update(['status' => 'failed']);
            $session->appendLog("❌ Job failed: {$e->getMessage()}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("RunScraperJob critically failed: {$exception->getMessage()}");
    }
}
