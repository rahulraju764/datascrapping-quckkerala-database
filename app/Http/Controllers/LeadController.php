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
     * Retry fetching contact details for a failed lead.
     */
    public function retry(Lead $lead)
    {
        $lead->update(['status' => 'pending']);
        $this->scraper->processLead($lead);

        return redirect()->back()
            ->with('success', "Retried lead: {$lead->title}");
    }
}
