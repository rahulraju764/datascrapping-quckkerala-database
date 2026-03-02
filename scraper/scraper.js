/**
 * scraper.js — Puppeteer infinite-scroll scraper for quickerala.com
 * 
 * Usage: node scraper.js <url> [maxScrolls]
 * Output: JSON array of { viewNumber, addressId, title } to stdout
 * 
 * Install: npm install puppeteer
 */

const puppeteer = require('puppeteer');

const TARGET_URL = process.argv[2] || 'https://www.quickerala.com/restaurants/ernakulam';
const MAX_SCROLLS = parseInt(process.argv[3]) || 100;
const SCROLL_DELAY_MS = 1500; // Wait between scrolls for content to load

async function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function autoScroll(page) {
    return page.evaluate(async (delay) => {
        return new Promise((resolve) => {
            let totalHeight = 0;
            let prevHeight = 0;
            let noChangeCount = 0;
            const MAX_NO_CHANGE = 5; // Stop after 5 consecutive scrolls with no new content

            const timer = setInterval(() => {
                window.scrollBy(0, window.innerHeight);
                totalHeight += window.innerHeight;

                const currentHeight = document.body.scrollHeight;
                if (currentHeight === prevHeight) {
                    noChangeCount++;
                    if (noChangeCount >= MAX_NO_CHANGE) {
                        clearInterval(timer);
                        resolve();
                    }
                } else {
                    noChangeCount = 0;
                    prevHeight = currentHeight;
                }
            }, delay);
        });
    }, SCROLL_DELAY_MS);
}

async function extractCards(page) {
    return page.evaluate(() => {
        const cards = document.querySelectorAll('.list-card');
        const results = [];

        cards.forEach(card => {
            const viewNumber = card.getAttribute('data-view-number');
            const addressId = card.getAttribute('data-address');

            // Try multiple selectors for the business title
            const titleEl =
                card.querySelector('.list-card-title') ||
                card.querySelector('h3') ||
                card.querySelector('h2') ||
                card.querySelector('.business-name') ||
                card.querySelector('[class*="title"]');

            const title = titleEl ? titleEl.innerText.trim() : 'Unknown';

            if (viewNumber) {
                results.push({ viewNumber, addressId, title });
            }
        });

        return results;
    });
}

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
        ],
    });

    const page = await browser.newPage();

    // Set a realistic user agent
    await page.setUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    );

    // Set viewport
    await page.setViewport({ width: 1280, height: 800 });

    try {
        process.stderr.write(`[scraper] Navigating to: ${TARGET_URL}\n`);
        await page.goto(TARGET_URL, { waitUntil: 'networkidle2', timeout: 60000 });

        // Wait for initial cards to appear
        await page.waitForSelector('.list-card', { timeout: 15000 }).catch(() => {
            process.stderr.write('[scraper] Warning: .list-card selector not found on initial load\n');
        });

        const seen = new Map(); // viewNumber => card data
        let scrollCount = 0;
        let lastCount = 0;

        while (scrollCount < MAX_SCROLLS) {
            // Extract all currently visible cards
            const cards = await extractCards(page);
            cards.forEach(card => {
                if (!seen.has(card.viewNumber)) {
                    seen.set(card.viewNumber, card);
                }
            });

            process.stderr.write(`[scraper] Scroll ${scrollCount + 1}: ${seen.size} unique cards found\n`);

            // Check if new cards were added
            if (seen.size === lastCount && scrollCount > 3) {
                process.stderr.write('[scraper] No new cards after scroll. Checking for "load more" button...\n');

                // Try clicking a "load more" button if present
                const loadMoreClicked = await page.evaluate(() => {
                    const btn = document.querySelector(
                        'button[class*="load-more"], .load-more, [class*="loadmore"], button[class*="see-more"]'
                    );
                    if (btn) { btn.click(); return true; }
                    return false;
                });

                if (!loadMoreClicked) {
                    process.stderr.write('[scraper] No load-more button found. Assuming all content loaded.\n');
                    break;
                }

                await sleep(2000);
            }

            lastCount = seen.size;

            // Scroll to bottom to trigger infinite scroll
            await autoScroll(page);
            await sleep(SCROLL_DELAY_MS);
            scrollCount++;
        }

        const results = Array.from(seen.values());
        process.stderr.write(`[scraper] Done. Total unique cards: ${results.length}\n`);

        // Output JSON to stdout for Laravel to consume
        process.stdout.write(JSON.stringify(results));

    } catch (error) {
        process.stderr.write(`[scraper] Fatal error: ${error.message}\n`);
        process.stdout.write(JSON.stringify([]));
    } finally {
        await browser.close();
    }
})();
