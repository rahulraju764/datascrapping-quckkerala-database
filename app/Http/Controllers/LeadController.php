<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\ScrapeSession;
use App\Services\ScraperService;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(private ScraperService $scraper) {}

    /**
     * Dashboard — overview of all leads.
     */
    public function index(Request $request)
    {
        $query = Lead::query();

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('whatsapp')) {
            $query->where('whatsapp_enabled', true);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('mobile_formatted', 'like', "%{$search}%");
            });
        }

        $leads = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        $stats = [
            'total' => Lead::count(),
            'fetched' => Lead::where('status', 'fetched')->count(),
            'pending' => Lead::where('status', 'pending')->count(),
            'failed' => Lead::where('status', 'failed')->count(),
            'whatsapp' => Lead::where('whatsapp_enabled', true)->count(),
        ];

        $sessions = ScrapeSession::orderByDesc('created_at')->take(5)->get();

        return view('leads.index', compact('leads', 'stats', 'sessions'));
    }

    /**
     * Trigger a new scrape session via the web UI.
     */
    public function scrape(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'max_scrolls' => 'nullable|integer|min:1|max:200',
            'delay' => 'nullable|integer|min:100|max:5000',
        ]);

        // Dispatch as a queued job so the HTTP response returns immediately
        \App\Jobs\RunScraperJob::dispatch(
            $request->url,
            (int)($request->max_scrolls ?? 50),
            (int)($request->delay ?? 500)
        );

        return redirect()->route('leads.index')
            ->with('success', 'Scrape job queued! Check back shortly for results.');
    }

    /**
     * Export leads as CSV download.
     */
    public function export(Request $request)
    {
        $query = Lead::query();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->boolean('whatsapp')) {
            $query->where('whatsapp_enabled', true);
        }

        $leads = $query->orderByDesc('created_at')->get();

        $csv = $this->scraper->exportToCsv($leads);

        $filename = 'leads_' . now()->format('Ymd_His') . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Show a single lead detail.
     */
    public function show(Lead $lead)
    {
        return view('leads.show', compact('lead'));
    }

    /**
     * Delete a lead.
     */
    public function destroy(Lead $lead)
    {
        $lead->delete();
        return redirect()->route('leads.index')->with('success', 'Lead deleted.');
    }

    /**
     * Sync lead details from Quickerala API.
     */
    public function sync(Lead $lead)
    {
        $viewNumber = $lead->view_number;
        $addressId = $lead->address_id;
        $timestamp = now()->getTimestamp() . '000'; // JS-like timestamp

        $url = "https://www.quickerala.com/business/{$viewNumber}/phone?addressId={$addressId}&_={$timestamp}";

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'
            ])->get($url);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] === 'success') {
                    // Extract phone
                    $phone = null;
                    if (isset($data['data']['mobile']['value'])) {
                        $phone = $data['data']['mobile']['value'];
                    }

                    // Update Lead
                    $lead->update([
                        'business_name' => $data['businessName'] ?? $lead->business_name,
                        'mobile' => $phone ?? $lead->mobile,
                        'mobile_formatted' => $phone ?? $lead->mobile,
                        'whatsapp_enabled' => ($data['whatsAppEnabled'] ?? "0") == "1",
                        'status' => $phone ? 'fetched' : 'failed',
                        'raw_response' => json_encode(array_merge(
                            json_decode($lead->raw_response, true) ?? [],
                            ['phone_details' => $data]
                        ))
                    ]);

                    return redirect()->back()->with('success', 'Lead synced successfully from API.');
                }
            }
            
            return redirect()->back()->with('error', 'API returned an unsuccessful response.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to sync: ' . $e->getMessage());
        }
    }
}
