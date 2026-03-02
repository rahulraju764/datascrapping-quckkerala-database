<?php

namespace App\Console\Commands;

use App\Models\ScrapeSession;
use App\Services\ScraperService;
use Illuminate\Console\Command;

class ScrapeLeads extends Command
{
    protected $signature = 'scrape:leads
        {url : The QuicKerala listing URL to scrape}
        {--max-scrolls=50 : Maximum number of scroll actions to perform}
        {--delay=500 : Delay between API requests in milliseconds}
        {--skip-extraction : Skip Puppeteer extraction and only process pending leads}
        {--whatsapp-only : Only fetch leads with WhatsApp enabled}';

    protected $description = 'Scrape leads from a QuicKerala listing page (infinite scroll + contact details)';

    public function handle(ScraperService $scraper): int
    {
        $url = $this->argument('url');
        $maxScrolls = (int)$this->option('max-scrolls');
        $delay = (int)$this->option('delay');

        // Create a new scrape session for tracking
        $session = ScrapeSession::create([
            'url' => $url,
            'status' => 'running',
        ]);

        $this->info("🚀 Starting scrape session #{$session->id}");
        $this->info("   URL: {$url}");
        $this->newLine();

        // ── Step 1: Extract cards via Puppeteer ──────────────────────────────
        if (!$this->option('skip-extraction')) {
            $this->info('📜 Step 1: Extracting list cards via Puppeteer (infinite scroll)...');

            try {
                $cards = $scraper->extractCardsViaPuppeteer($url, $maxScrolls);

                if (empty($cards)) {
                    $this->warn('No cards found. Check the URL and selector.');
                    $session->update(['status' => 'failed']);
                    $session->appendLog('No cards found on the page.');
                    return Command::FAILURE;
                }

                $saved = $scraper->persistCardStubs($cards);
                $session->update(['total_found' => $saved]);
                $session->appendLog("Extracted {$saved} unique cards from {$url}");

                $this->info("   ✓ Found and saved {$saved} unique business cards");

            } catch (\Throwable $e) {
                $this->error("Puppeteer extraction failed: {$e->getMessage()}");
                $session->update(['status' => 'failed']);
                $session->appendLog("Extraction error: {$e->getMessage()}");
                return Command::FAILURE;
            }
        } else {
            $this->info('⏭  Skipping extraction (--skip-extraction flag set)');
        }

        // ── Step 2: Fetch contact details for pending leads ───────────────────
        $pendingCount = \App\Models\Lead::pending()->count();
        $this->newLine();
        $this->info("📞 Step 2: Fetching contact details for {$pendingCount} pending leads...");

        $bar = $this->output->createProgressBar($pendingCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% ─ %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        // Process each pending lead with progress
        $processed = 0;
        $failed = 0;

        \App\Models\Lead::pending()->chunk(50, function ($leads) use (
            $scraper, $session, $bar, $delay, &$processed, &$failed
        ) {
            foreach ($leads as $lead) {
                $bar->setMessage($lead->title);
                $success = $scraper->processLead($lead);

                if ($success) {
                    $processed++;
                    $session->increment('total_processed');
                } else {
                    $failed++;
                    $session->increment('total_failed');
                }

                $bar->advance();

                if ($delay > 0) {
                    usleep($delay * 1000);
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        // ── Summary ────────────────────────────────────────────────────────────
        $session->update(['status' => 'completed']);
        $session->appendLog("Completed: {$processed} fetched, {$failed} failed.");

        $this->table(
            ['Metric', 'Count'],
            [
                ['Cards found', $session->total_found],
                ['Contact details fetched', $processed],
                ['Failed', $failed],
                ['Success rate', $pendingCount > 0 ? round(($processed / $pendingCount) * 100, 1) . '%' : 'N/A'],
            ]
        );

        $this->info("✅ Scrape session #{$session->id} completed!");
        $this->info("   View results: php artisan leads:export or open the web dashboard.");

        return Command::SUCCESS;
    }
}
