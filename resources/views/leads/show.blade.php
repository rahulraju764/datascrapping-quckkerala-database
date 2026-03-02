<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Details - {{ $lead->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;700&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0a0a0f;
            --surface: #111118;
            --surface2: #18181f;
            --border: #2a2a38;
            --accent: #00e5ff;
            --accent2: #7c3aed;
            --green: #00ff88;
            --yellow: #ffd600;
            --red: #ff4466;
            --text: #e8e8f0;
            --muted: #6b6b80;
            --font-mono: 'JetBrains Mono', monospace;
            --font-sans: 'Space Grotesk', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-sans);
            min-height: 100vh;
            overflow-x: hidden;
            padding: 2rem;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(0,229,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,229,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
            text-decoration: none;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            margin-bottom: 2rem;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--accent);
        }

        .details-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .card-header {
            padding: 2.5rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(to bottom right, rgba(0,229,255,0.05), transparent);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-family: var(--font-mono);
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }

        .badge-fetched { background: rgba(0,255,136,0.1); color: var(--green); border: 1px solid rgba(0,255,136,0.2); }
        .badge-pending { background: rgba(255,214,0,0.1); color: var(--yellow); border: 1px solid rgba(255,214,0,0.2); }
        .badge-failed { background: rgba(255,68,102,0.1); color: var(--red); border: 1px solid rgba(255,68,102,0.2); }

        .business-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.1;
            margin-bottom: 0.5rem;
        }

        .business-sub {
            color: var(--muted);
            font-size: 1.1rem;
        }

        .card-body {
            padding: 2.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .info-label {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .info-value {
            font-size: 1.25rem;
            font-weight: 500;
            color: var(--text);
        }

        .mobile-value {
            font-family: var(--font-mono);
            color: var(--accent);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .wa-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(37, 211, 102, 0.1);
            color: #25D366;
            border: 1px solid rgba(37, 211, 102, 0.2);
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-top: 0.5rem;
        }

        .raw-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }

        .raw-title {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--muted);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .raw-content {
            background: var(--surface2);
            padding: 1.5rem;
            border-radius: 8px;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            color: var(--muted);
            overflow-x: auto;
            white-space: pre-wrap;
            border: 1px solid var(--border);
        }

        .actions-footer {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-family: var(--font-sans);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--bg);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,229,255,0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            background: var(--surface2);
            border-color: var(--muted);
        }
    </style>
</head>
<body>

<div class="container">
    <a href="{{ route('leads.index') }}" class="back-link">
        ← BACK TO DATABASE
    </a>

    <div class="details-card">
        <div class="card-header">
            <span class="badge badge-{{ $lead->status }}">
                {{ $lead->status }}
            </span>
            <h1 class="business-title">{{ $lead->business_name ?? $lead->title }}</h1>
            @if($lead->business_name && $lead->business_name !== $lead->title)
                <div class="business-sub">{{ $lead->title }}</div>
            @endif
        </div>

        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Mobile Number</div>
                    <div class="info-value mobile-value">
                        {{ $lead->mobile_formatted ?? 'NOT FETCHED' }}
                    </div>
                    @if($lead->whatsapp_enabled)
                        <div><span class="wa-badge">✓ WhatsApp Available</span></div>
                    @endif
                </div>

                <div class="info-item">
                    <div class="info-label">Internal IDs</div>
                    <div class="info-value" style="font-family: var(--font-mono); font-size: 0.9rem;">
                        View: {{ $lead->view_number }}<br>
                        Address: {{ $lead->address_id ?? 'N/A' }}
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Scrapped Date</div>
                    <div class="info-value">
                        {{ $lead->created_at->format('d M Y') }}
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">WhatsApp Status</div>
                    <div class="info-value">
                        @if($lead->whatsapp_enabled)
                            <span style="color: #25D366;">WhatsApp Available</span>
                        @else
                            <span style="color: var(--muted);">No WhatsApp</span>
                        @endif
                    </div>
                </div>
            </div>

            @php
                $rawData = json_decode($lead->raw_response, true);
                $apiData = $rawData['phone_details'] ?? null;
            @endphp

            @if($apiData)
                <div class="raw-section" style="border-color: var(--accent); background: rgba(0,229,255,0.02); padding: 1.5rem; border-radius: 12px; margin-top: 2rem;">
                    <div class="raw-title" style="color: var(--accent);">
                        <span>✦</span> LIVE API RESPONSE DATA
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-top: 1rem;">
                        <div class="info-item">
                            <div class="info-label">API Business Name</div>
                            <div class="info-value" style="font-size: 1rem;">{{ $apiData['businessName'] ?? 'N/A' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Package ID</div>
                            <div class="info-value" style="font-size: 1rem;">{{ $apiData['packageId'] ?? 'N/A' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Verify Status</div>
                            <div class="info-value" style="font-size: 1rem;">{{ $apiData['phoneVerificationStatus'] ?? 'N/A' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Mobile (Raw)</div>
                            <div class="info-value" style="font-size: 1rem;">{{ $apiData['data']['mobile']['value'] ?? 'N/A' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">WhatsApp (API)</div>
                            <div class="info-value" style="font-size: 1rem; color: #25D366;">
                                {{ ($apiData['whatsAppEnabled'] ?? "0") == "1" ? 'Available' : 'No' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <div class="actions-footer">
        <form action="{{ route('leads.sync', $lead) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary" style="background: var(--accent); color: var(--bg);">
                ✦ Sync with Quickerala API
            </button>
        </form>

        @if($lead->status === 'failed')
            <form action="{{ route('leads.retry', $lead) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary">
                    ↺ Retry Fetch
                </button>
            </form>
        @endif
        
        <form action="{{ route('leads.destroy', $lead) }}" method="POST" onsubmit="return confirm('Delete this lead forever?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline" style="color: var(--red);">
                ✕ Delete Lead
            </button>
        </form>
    </div>
</div>

</body>
</html>
