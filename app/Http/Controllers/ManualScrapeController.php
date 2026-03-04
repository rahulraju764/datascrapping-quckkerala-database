<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

use App\Models\Lead;

class ManualScrapeController extends Controller
{
    public function index()
    {
        return view('manual-scrape');
    }

    public function process(Request $request)
    {
        set_time_limit(600); // Allow 10 minutes for this request
        $url = $request->input('url', 'https://www.quickerala.com/hotels-restaurants/ct-412');
        $maxScrolls = $request->input('maxScrolls', 10);

        $scraperPath = base_path('scraper/manual_scraper.js');

        if (!file_exists($scraperPath)) {
            return response()->json(['error' => 'Scraper script not found'], 500);
        }

        $process = new Process([
            'node',
            $scraperPath,
            $url,
            (string)$maxScrolls,
        ]);
        
        // Essential on Windows when running Node from PHP to avoid crypto assertion failures
        $process->setEnv([
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\Windows',
        ]);

        $process->setTimeout(600); // 10-minute timeout
        $process->run();

        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();
            $stdOutput = $process->getOutput();
            Log::error('Manual Puppeteer scraper failed', [
                'stderr' => $errorOutput, 
                'stdout' => $stdOutput,
                'exit_code' => $process->getExitCode()
            ]);
            return response()->json([
                'error' => 'Scraper failed', 
                'details' => $errorOutput ?: "Exit code: " . $process->getExitCode(),
                'log' => $stdOutput
            ], 500);
        }

        $output = $process->getOutput();
        
        // Extract JSON using a more robust regex (looking for the last JSON array)
        if (preg_match('/\[\{.*\}\]$/s', trim($output), $matches)) {
            $jsonContent = $matches[0];
            $results = json_decode($jsonContent, true);
        } else {
            // Fallback for empty results or error format
            $results = json_decode(trim($output), true) ?? [];
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'error' => 'Invalid JSON from scraper', 
                'msg' => json_last_error_msg(),
                'raw' => $output
            ], 500);
        }

        // Save to Database
        foreach ($results as $item) {
            if ($item['view_number'] === 'N/A') continue;

            $phoneData = $item['phone_details'] ?? [];
            
            Lead::updateOrCreate(
                ['view_number' => $item['view_number']],
                [
                    'address_id' => $item['address_id'] !== 'N/A' ? $item['address_id'] : null,
                    'title' => $item['title'],
                    'category' => $item['category'] !== 'N/A' ? $item['category'] : null,
                    'subcategories' => $item['subcategories'] !== 'N/A' ? $item['subcategories'] : null,
                    'district' => $item['district'] !== 'N/A' ? $item['district'] : null,
                    'locality' => $item['locality'] !== 'N/A' ? $item['locality'] : null,
                    'business_name' => $item['business_name_api'] ?? $item['title'],
                    'mobile' => $item['phone'] !== 'N/A' ? (string)$item['phone'] : null,
                    'mobile_formatted' => $item['phone'] !== 'N/A' ? (string)$item['phone'] : null,
                    'whatsapp_enabled' => (bool)($item['whatsapp_api'] ?? false),
                    'status' => ($item['phone'] !== 'N/A') ? 'fetched' : 'pending',
                    'raw_response' => json_encode($item)
                ]
            );
        }

        return response()->json([
            'success' => true,
            'count' => count($results),
            'results' => $results,
            'raw_logs' => $output
        ]);
    }
}
