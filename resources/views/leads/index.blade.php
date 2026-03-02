<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuicKerala Lead Scraper</title>
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
        }

        /* ── Grid background ── */
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

        /* ── Header ── */
        header {
            position: relative;
            z-index: 10;
            border-bottom: 1px solid var(--border);
            background: rgba(10,10,15,0.95);
            backdrop-filter: blur(10px);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(0.96); }
        }

        .logo-text {
            font-family: var(--font-mono);
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            color: var(--accent);
            text-transform: uppercase;
        }

        .logo-text span { color: var(--muted); }

        .header-meta {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--muted);
            letter-spacing: 0.1em;
        }

        /* ── Layout ── */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        /* ── Stats bar ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            padding: 1.25rem 1.5rem;
            position: relative;
            overflow: hidden;
            transition: background 0.2s;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--accent-color, var(--border));
            transform: scaleX(0);
            transition: transform 0.3s;
            transform-origin: left;
        }

        .stat-card:hover::after { transform: scaleX(1); }

        .stat-label {
            font-family: var(--font-mono);
            font-size: 0.65rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-family: var(--font-mono);
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .stat-card.total .stat-value { color: var(--text); --accent-color: var(--accent); }
        .stat-card.fetched .stat-value { color: var(--green); --accent-color: var(--green); }
        .stat-card.pending .stat-value { color: var(--yellow); --accent-color: var(--yellow); }
        .stat-card.failed .stat-value { color: var(--red); --accent-color: var(--red); }
        .stat-card.whatsapp .stat-value { color: #25D366; --accent-color: #25D366; }

        /* ── Two-column layout ── */
        .main-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        /* ── Panel ── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }

        .panel-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface2);
        }

        .panel-title {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--accent);
        }

        .panel-body { padding: 1.5rem; }

        /* ── Scrape form ── */
        .form-group { margin-bottom: 1.25rem; }

        .form-label {
            display: block;
            font-family: var(--font-mono);
            font-size: 0.65rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 0.6rem 0.8rem;
            color: var(--text);
            font-family: var(--font-mono);
            font-size: 0.78rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(0,229,255,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.7rem 1.2rem;
            border: none;
            border-radius: 4px;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--accent);
            color: #000;
            width: 100%;
        }

        .btn-primary:hover {
            background: #33eeff;
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(0,229,255,0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-sm {
            padding: 0.35rem 0.7rem;
            font-size: 0.65rem;
        }

        .btn-danger {
            background: transparent;
            border: 1px solid var(--red);
            color: var(--red);
        }

        .btn-danger:hover { background: var(--red); color: #000; }

        /* ── Filter bar ── */
        .filter-bar {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface2);
        }

        .filter-bar input, .filter-bar select {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 0.45rem 0.75rem;
            color: var(--text);
            font-family: var(--font-mono);
            font-size: 0.72rem;
            outline: none;
        }

        .filter-bar input:focus, .filter-bar select:focus {
            border-color: var(--accent);
        }

        .filter-bar input { min-width: 220px; }

        /* ── Table ── */
        .table-wrapper { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        thead th {
            background: var(--surface2);
            padding: 0.75rem 1rem;
            text-align: left;
            font-family: var(--font-mono);
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }

        tbody tr:hover { background: rgba(0,229,255,0.03); }

        tbody td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }

        .cell-mono {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            color: var(--muted);
        }

        .cell-title {
            font-weight: 500;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ── Badges ── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.55rem;
            border-radius: 3px;
            font-family: var(--font-mono);
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .badge-fetched { background: rgba(0,255,136,0.12); color: var(--green); }
        .badge-pending { background: rgba(255,214,0,0.12); color: var(--yellow); }
        .badge-failed { background: rgba(255,68,102,0.12); color: var(--red); }

        .badge-whatsapp {
            background: rgba(37,211,102,0.12);
            color: #25D366;
        }

        /* ── Phone display ── */
        .phone-num {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--green);
            letter-spacing: 0.05em;
        }

        /* ── Action buttons ── */
        .actions { display: flex; gap: 0.4rem; align-items: center; }

        /* ── Session log ── */
        .session-item {
            padding: 0.85rem 0;
            border-bottom: 1px solid var(--border);
        }

        .session-item:last-child { border-bottom: none; }

        .session-url {
            font-family: var(--font-mono);
            font-size: 0.68rem;
            color: var(--accent);
            margin-bottom: 0.35rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .session-meta {
            display: flex;
            gap: 1rem;
            font-family: var(--font-mono);
            font-size: 0.65rem;
            color: var(--muted);
        }

        /* ── Pagination ── */
        .pagination {
            display: flex;
            gap: 0.4rem;
            align-items: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            justify-content: center;
        }

        .pagination a, .pagination span {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            padding: 0.35rem 0.65rem;
            border-radius: 3px;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--muted);
            transition: all 0.2s;
        }

        .pagination a:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .pagination .active span {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }

        /* ── Alert ── */
        .alert {
            padding: 0.85rem 1.25rem;
            border-radius: 4px;
            font-size: 0.82rem;
            margin-bottom: 1.5rem;
            border-left: 3px solid;
        }

        .alert-success {
            background: rgba(0,255,136,0.08);
            border-color: var(--green);
            color: var(--green);
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--muted);
        }

        .empty-state .empty-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.4;
        }

        .empty-state p {
            font-family: var(--font-mono);
            font-size: 0.78rem;
        }

        /* ── Export strip ── */
        .export-strip {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-left: auto;
        }

        /* ── Responsive ── */
        @media (max-width: 1100px) {
            .main-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .container { padding: 1rem; }
        }
    </style>
</head>
<body>

<header>
    <div class="header-logo">
        <div class="logo-icon"></div>
        <div class="logo-text">Lead<span>_</span>Scraper</div>
    </div>
    <div class="header-meta">QuicKerala.com ∕ v1.0 ∕ {{ now()->format('Y-m-d') }}</div>
</header>

<div class="container">

    {{-- Flash message --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Stats row --}}
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-label">Total Leads</div>
            <div class="stat-value">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="stat-card fetched">
            <div class="stat-label">Fetched</div>
            <div class="stat-value">{{ number_format($stats['fetched']) }}</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-label">Pending</div>
            <div class="stat-value">{{ number_format($stats['pending']) }}</div>
        </div>
        <div class="stat-card failed">
            <div class="stat-label">Failed</div>
            <div class="stat-value">{{ number_format($stats['failed']) }}</div>
        </div>
        <div class="stat-card whatsapp">
            <div class="stat-label">WhatsApp</div>
            <div class="stat-value">{{ number_format($stats['whatsapp']) }}</div>
        </div>
    </div>

    <div class="main-grid">

        {{-- ── Sidebar ── --}}
        <div>

            {{-- Scrape form --}}
            <div class="panel" style="margin-bottom: 1.5rem;">
                <div class="panel-header">
                    <div class="panel-title">⚡ New Scrape</div>
                </div>
                <div class="panel-body">
                    <form action="{{ route('leads.scrape') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="form-label">Target URL</label>
                            <input
                                type="url"
                                name="url"
                                class="form-input"
                                placeholder="https://www.quickerala.com/restaurants/..."
                                required
                                value="{{ old('url') }}"
                            >
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Max Scrolls</label>
                                <input
                                    type="number"
                                    name="max_scrolls"
                                    class="form-input"
                                    value="50"
                                    min="1"
                                    max="200"
                                >
                            </div>
                            <div class="form-group">
                                <label class="form-label">Delay (ms)</label>
                                <input
                                    type="number"
                                    name="delay"
                                    class="form-input"
                                    value="500"
                                    min="100"
                                    max="5000"
                                >
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            ▶ &nbsp;Run Scraper
                        </button>
                    </form>
                </div>
            </div>

            {{-- Recent sessions --}}
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">🗂 Recent Sessions</div>
                </div>
                <div class="panel-body" style="padding: 0 1.5rem;">
                    @forelse($sessions as $session)
                        <div class="session-item">
                            <div class="session-url" title="{{ $session->url }}">{{ $session->url }}</div>
                            <div class="session-meta">
                                <span>{{ $session->total_found }} found</span>
                                <span>{{ $session->total_processed }} ok</span>
                                <span>{{ $session->total_failed }} err</span>
                                <span class="badge badge-{{ $session->status === 'completed' ? 'fetched' : ($session->status === 'running' ? 'pending' : 'failed') }}">
                                    {{ $session->status }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div style="padding: 1.5rem 0; text-align: center; font-family: var(--font-mono); font-size: 0.72rem; color: var(--muted);">
                            No sessions yet
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Main table ── --}}
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">📋 Leads Database</div>
                <div class="export-strip">
                    <a href="{{ route('manual.index') }}" class="btn btn-outline btn-sm" style="color: #3b82f6; border-color: #3b82f6;">
                        ⚡ Manual Scraper
                    </a>
                    <a href="{{ route('leads.export', request()->only('status', 'whatsapp', 'search')) }}"
                       class="btn btn-outline btn-sm">
                        ↓ CSV
                    </a>
                    <a href="{{ route('leads.export', array_merge(request()->only('status', 'search'), ['whatsapp' => 1])) }}"
                       class="btn btn-outline btn-sm" style="color: #25D366; border-color: #25D366;">
                        ↓ WhatsApp
                    </a>
                </div>
            </div>

            {{-- Filter bar --}}
            <form method="GET" action="{{ route('leads.index') }}">
                <div class="filter-bar">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search name, mobile..."
                        value="{{ request('search') }}"
                    >
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="fetched" @selected(request('status') === 'fetched')>Fetched</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                        <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                    </select>
                    <label style="display: flex; align-items: center; gap: 0.4rem; font-family: var(--font-mono); font-size: 0.7rem; color: var(--muted); cursor: pointer;">
                        <input type="checkbox" name="whatsapp" value="1" {{ request('whatsapp') ? 'checked' : '' }} style="accent-color: #25D366;">
                        WhatsApp only
                    </label>
                    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
                    @if(request()->hasAny(['search', 'status', 'whatsapp']))
                        <a href="{{ route('leads.index') }}" class="btn btn-outline btn-sm">Clear</a>
                    @endif
                </div>
            </form>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Business</th>
                            <th>Mobile</th>
                            <th>WA</th>
                            <th>View No.</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leads as $lead)
                            <tr>
                                <td class="cell-mono">{{ $lead->id }}</td>
                                <td>
                                    <div class="cell-title" title="{{ $lead->business_name ?? $lead->title }}">
                                        {{ $lead->business_name ?? $lead->title }}
                                    </div>
                                    @if($lead->business_name && $lead->business_name !== $lead->title)
                                        <div style="font-size: 0.7rem; color: var(--muted); font-family: var(--font-mono);">
                                            {{ Str::limit($lead->title, 30) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($lead->mobile_formatted)
                                        <div class="phone-num">{{ $lead->formatted_mobile }}</div>
                                    @else
                                        <span style="color: var(--muted); font-family: var(--font-mono); font-size: 0.72rem;">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($lead->whatsapp_enabled)
                                        <span class="badge badge-whatsapp">✓ WA</span>
                                    @else
                                        <span style="color: var(--border); font-size: 0.7rem;">—</span>
                                    @endif
                                </td>
                                <td class="cell-mono">{{ $lead->view_number }}</td>
                                <td>
                                    <span class="badge badge-{{ $lead->status }}">{{ $lead->status }}</span>
                                </td>
                                <td class="cell-mono" style="font-size: 0.67rem;">
                                    {{ $lead->created_at->format('d M y') }}
                                </td>
                                <td>
                                    <div class="actions">
                                        @if($lead->status === 'failed')
                                            <form action="{{ route('leads.retry', $lead) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--yellow); border-color: var(--yellow);">
                                                    ↺
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('leads.destroy', $lead) }}" method="POST"
                                              onsubmit="return confirm('Delete this lead?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">✕</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-icon">◈</div>
                                        <p>No leads found.</p>
                                        <p style="margin-top: 0.5rem; opacity: 0.6;">Enter a URL and run the scraper to get started.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($leads->hasPages())
                <div class="pagination">
                    {{ $leads->links('vendor.pagination.custom') }}
                </div>
            @endif
        </div>
    </div>

</div>

</body>
</html>
