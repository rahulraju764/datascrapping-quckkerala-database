<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\ScraperService;
use Illuminate\Console\Command;

class ExportLeads extends Command
{
    protected $signature = 'leads:export
        {--status=fetched : Filter by status (fetched, pending, failed, all)}
        {--whatsapp : Only export WhatsApp-enabled leads}
        {--output= : Output file path (defaults to storage/app/exports/leads_TIMESTAMP.csv)}';

    protected $description = 'Export scraped leads to a CSV file';

    public function handle(ScraperService $scraper): int
    {
        $status = $this->option('status');
        $whatsappOnly = $this->option('whatsapp');

        $query = Lead::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($whatsappOnly) {
            $query->where('whatsapp_enabled', true);
        }

        $leads = $query->orderBy('created_at')->get();

        if ($leads->isEmpty()) {
            $this->warn('No leads found with the specified filters.');
            return Command::FAILURE;
        }

        $csv = $scraper->exportToCsv($leads);

        $filename = $this->option('output')
            ?? storage_path('app/exports/leads_' . now()->format('Ymd_His') . '.csv');

        // Ensure directory exists
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filename, $csv);

        $this->info("✅ Exported {$leads->count()} leads to:");
        $this->line("   {$filename}");

        return Command::SUCCESS;
    }
}
