<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

class ManualScrapeController extends Controller
{
    public function index()
    {
        return view('manual-scrape');
    }

    public function process(Request $request)
    {
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

        return response()->json([
            'success' => true,
            'count' => count($results),
            'results' => $results,
            'raw_logs' => $output
        ]);
    }
}
