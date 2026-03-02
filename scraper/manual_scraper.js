const puppeteer = require('puppeteer');

const TARGET_URL = process.argv[2] || 'https://www.quickerala.com/hotels-restaurants/ct-412';
const MAX_SCROLLS = parseInt(process.argv[3]) || 10;
const SCROLL_DELAY_MS = 2000;

async function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    await page.setViewport({ width: 1280, height: 1000 });

    try {
        console.log(`[LOG] Navigating to: ${TARGET_URL}`);
        await page.goto(TARGET_URL, { waitUntil: 'networkidle2', timeout: 60000 });

        let scrollCount = 0;
        while (scrollCount < MAX_SCROLLS) {
            console.log(`[LOG] Scroll ${scrollCount + 1}/${MAX_SCROLLS}...`);
            await page.evaluate(() => window.scrollBy(0, window.innerHeight));
            await sleep(SCROLL_DELAY_MS);
            scrollCount++;
        }

        console.log(`[LOG] Extraction starting for elements with class: "list-card d-grid rounded-10 hover-underline"`);

        const extractedData = await page.evaluate(() => {
            // Using the specific class requested by the user
            const cards = document.querySelectorAll('.list-card.d-grid.rounded-10.hover-underline');
            const data = [];

            cards.forEach((card, index) => {
                const item = {
                    index: index + 1,
                    full_html: card.outerHTML,
                    title: card.querySelector('.title')?.innerText?.trim() || 'N/A',
                    link: card.querySelector('a')?.href || 'N/A',
                    location: card.querySelector('.text-grey')?.innerText?.trim() || 'N/A',
                    // Robust attribute extraction: Check the card OR any child element
                    view_number: card.getAttribute('data-view-number') ||
                        card.querySelector('[data-view-number]')?.getAttribute('data-view-number') ||
                        'N/A',
                    address_id: card.getAttribute('data-address') ||
                        card.querySelector('[data-address]')?.getAttribute('data-address') ||
                        'N/A'
                };
                data.push(item);
            });

            return data;
        });

        console.log(`[LOG] Found ${extractedData.length} elements.`);

        // Detailed logging of each element as requested
        extractedData.forEach(item => {
            
            console.log(`[ITEM_LOG] Element #${item.index}: ${item.title} | Location: ${item.location} | ViewID: ${item.view_number}`);
            // console.log(`[HTML_LOG] ${item.full_html}`); // Verbose HTML log
        });

        // Output final JSON for Laravel
        process.stdout.write(JSON.stringify(extractedData));

    } catch (error) {
        console.error(`[ERROR] ${error.message}`);
        process.stdout.write(JSON.stringify([]));
    } finally {
        await browser.close();
    }
})();
