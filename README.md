# QuicKerala Lead Scraper — Laravel Project

A full-featured Laravel application that scrapes business leads from QuicKerala.com listings, handles infinite scroll pagination via Puppeteer, and fetches contact details (mobile + WhatsApp status) via the QuicKerala API.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│  Web Dashboard (Blade)                              │
│  → Submit URL → Queue Job → View Results / Export  │
└──────────────────────┬──────────────────────────────┘
                       │
         ┌─────────────▼──────────────┐
         │     RunScraperJob          │
         │   (Laravel Queue Worker)   │
         └─────────────┬──────────────┘
                       │
          ┌────────────▼────────────┐
          │     ScraperService      │
          │                         │
          │  1. extractCards()      │──► Node.js Puppeteer
          │     Infinite scroll      │    (scraper/scraper.js)
          │                         │
          │  2. persistCardStubs()  │──► MySQL / SQLite
          │     Save viewNum + addr │    leads table
          │                         │
          │  3. processAllPending() │──► QuicKerala API
          │     GET /business/N/    │    /phone?addressId=...
          │     phone endpoint      │
          └─────────────────────────┘
```

---

## Requirements

- **PHP** 8.2+
- **Laravel** 11.x
- **Node.js** 18+ (for Puppeteer)
- **MySQL** or **SQLite**
- **Composer**

---

## Installation

### 1. Clone / Create Laravel Project

```bash
composer create-project laravel/laravel quickerala-scraper
cd quickerala-scraper
```

### 2. Copy Project Files

Copy all files from this package into your Laravel root, maintaining the directory structure.

### 3. Install PHP Dependencies

```bash
composer require symfony/process
# Laravel's HTTP client is already included via Guzzle
```

### 4. Install Puppeteer

```bash
npm install puppeteer
```

> On headless servers, you may need Chrome dependencies:
> ```bash
> # Ubuntu/Debian
> apt-get install -y chromium-browser
> npx puppeteer browsers install chrome
> ```

### 5. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
# Database (MySQL example)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quickerala_leads
DB_USERNAME=root
DB_PASSWORD=secret

# Queue (use 'database' for simplicity, 'redis' for production)
QUEUE_CONNECTION=database
```

### 6. Run Migrations

```bash
php artisan migrate
```

### 7. Set Up Queue Worker

For web-triggered scrapes:

```bash
# Run queue worker (keep this running)
php artisan queue:work --timeout=600
```

Or use `supervisor` in production:

```ini
[program:laravel-worker]
command=php /var/www/quickerala-scraper/artisan queue:work --sleep=3 --tries=1 --timeout=600
autostart=true
autorestart=true
```

---

## Usage

### Web Dashboard

```bash
php artisan serve
# Open: http://localhost:8000
```

1. Enter a QuicKerala listing URL (e.g. `https://www.quickerala.com/restaurants/ernakulam`)
2. Set max scrolls (default: 50) and request delay (default: 500ms)
3. Click **Run Scraper**
4. The job is queued — refresh the page to see results populate
5. Export to CSV using the **↓ CSV** or **↓ WhatsApp** buttons

### CLI (Artisan Command)

```bash
# Basic scrape
php artisan scrape:leads "https://www.quickerala.com/restaurants/ernakulam"

# With options
php artisan scrape:leads "https://www.quickerala.com/hotels/thrissur" \
    --max-scrolls=100 \
    --delay=800

# Only process pending leads (skip re-scraping the page)
php artisan scrape:leads "https://..." --skip-extraction

# Export to CSV
php artisan leads:export
php artisan leads:export --whatsapp           # WhatsApp-enabled only
php artisan leads:export --output=/tmp/my.csv # Custom path
php artisan leads:export --status=all         # All statuses
```

---

## File Structure

```
app/
├── Console/Commands/
│   ├── ScrapeLeads.php          # CLI: php artisan scrape:leads
│   └── ExportLeads.php          # CLI: php artisan leads:export
├── Http/Controllers/
│   └── LeadController.php       # Web dashboard & API
├── Jobs/
│   └── RunScraperJob.php        # Queued scraper job
├── Models/
│   ├── Lead.php                 # Lead model
│   └── ScrapeSession.php        # Session tracking model
└── Services/
    └── ScraperService.php       # Core scraping & parsing logic

database/migrations/
└── ..._create_leads_table.php   # leads + scrape_sessions tables

resources/views/leads/
└── index.blade.php              # Dashboard UI

routes/
└── web.php                      # Web routes

scraper/
└── scraper.js                   # Node.js Puppeteer infinite-scroll scraper
```

---

## How It Works

### 1. Puppeteer Infinite Scroll (`scraper/scraper.js`)

The Node.js script:
- Opens the target URL in a headless Chrome browser
- Waits for `.list-card` elements to appear
- Repeatedly scrolls to the bottom, waiting for new cards to load
- Detects when no new cards appear (stops automatically)
- Also tries clicking "load more" buttons if present
- Outputs a JSON array of `{ viewNumber, addressId, title }` to stdout
- Laravel captures this via `Symfony\Component\Process`

### 2. Card Stubs Saved Immediately

After extraction, all cards are saved to the `leads` table with `status = 'pending'`. This prevents data loss if the contact-fetching phase fails.

### 3. Contact API Calls

For each pending lead, the service calls:

```
GET https://www.quickerala.com/business/{viewNumber}/phone
    ?addressId={addressId}
    &_={timestamp}
```

The response is parsed for:
- `businessName`
- `whatsAppEnabled` (boolean)
- `data.mobile.label` (field label)
- `data.mobile.valueFormatted` (the phone number, e.g. `918714421133`)

### 4. Rate Limiting

A configurable delay (default 500ms) between API requests prevents rate limiting or IP bans.

---

## Database Schema

### `leads`

| Column | Type | Description |
|---|---|---|
| `view_number` | string (unique) | QuicKerala business ID |
| `address_id` | string | Address parameter for API |
| `title` | string | Title from `.list-card` |
| `business_name` | string | From API response |
| `mobile` | string | Field label |
| `mobile_formatted` | string | E.g. `918714421133` |
| `whatsapp_enabled` | boolean | WhatsApp status |
| `status` | enum | `pending`, `fetched`, `failed` |
| `raw_response` | text | Full JSON response |

### `scrape_sessions`

Tracks each scrape run with totals and a running log.

---

## Production Tips

1. **Use Redis** for the queue (`QUEUE_CONNECTION=redis`) for reliability
2. **Increase Chrome memory** on large scrapes: add `--max_old_space_size=4096` to the node command
3. **Proxy rotation**: Add proxy support in `scraper.js` for large-scale scraping
4. **Database indexing**: `view_number` is already unique; add index on `status` for large tables
5. **Scheduled re-scrapes**: Add to `app/Console/Kernel.php`:

```php
$schedule->command('scrape:leads "https://..." --skip-extraction')
         ->daily();
```

---

## Troubleshooting

| Problem | Solution |
|---|---|
| `Puppeteer scraper not found` | Move `scraper.js` to `<project-root>/scraper/scraper.js` |
| `No cards found` | The site may have changed its HTML structure; update the selector in `scraper.js` |
| `HTTP 429` errors | Increase `--delay` to 1000ms+ |
| Chrome won't launch | Install system Chrome deps (see Installation step 4) |
| Queue jobs not running | Start `php artisan queue:work` in a separate terminal |
#   d a t a s c r a p p i n g - q u c k k e r a l a - d a t a b a s e  
 