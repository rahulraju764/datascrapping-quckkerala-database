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
                    locality: (card.querySelector('.title')?.getAttribute('title') || '').split(',')[1]?.trim() || 'N/A',
                    district: (card.querySelector('.title')?.getAttribute('title') || '').split(',')[2]?.trim() || 'N/A',
                    category: Array.from(card.querySelectorAll('.bg-link')).map(el => el.innerText.trim()).filter(txt => txt && !txt.includes('in '))[0] || 'N/A',
                    subcategories: Array.from(card.querySelectorAll('.bg-link')).map(el => el.innerText.trim()).filter(txt => txt && !txt.includes('in ')).slice(1).join(', ') || 'N/A',
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

        console.log(`[LOG] Found ${extractedData.length} elements. Fetching contact details...`);

        // Second pass: Fetch phone numbers for each card from the dynamic link
        for (let i = 0; i < extractedData.length; i++) {
            const item = extractedData[i];
            if (item.view_number !== 'N/A' && item.address_id !== 'N/A') {
                try {
                    if ((i + 1) % 10 === 0 || i === 0 || (i + 1) === extractedData.length) {
                        console.log(`[LOG] Fetching contacts: ${i + 1}/${extractedData.length} items...`);
                    }

                    const phoneData = await page.evaluate(async (viewNo, addressId) => {
                        try {
                            const timestamp = Date.now();
                            const url = `https://www.quickerala.com/business/${viewNo}/phone?addressId=${addressId}&_=${timestamp}`;
                            const response = await fetch(url, {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            return await response.json();
                        } catch (e) {
                            return { error: e.message };
                        }
                    }, item.view_number, item.address_id);

                    item.phone_details = phoneData;

                    // Extract phone from the specific structure: data.mobile.value
                    let extractedPhone = 'N/A';
                    if (phoneData.data && phoneData.data.mobile) {
                        extractedPhone = phoneData.data.mobile.value || phoneData.data.mobile.valueFormatted;
                    } else {
                        extractedPhone = phoneData.phone || phoneData.mobile || 'N/A';
                    }

                    item.phone = extractedPhone;
                    item.business_name_api = phoneData.businessName || null;
                    item.whatsapp_api = phoneData.whatsAppEnabled == "1";

                    // Small delay to prevent rate limiting
                    await sleep(800);
                } catch (err) {
                    console.log(`[LOG] Failed to fetch phone for ${item.view_number}: ${err.message}`);
                    item.phone = 'Error';
                }
            } else {
                item.phone = 'No ID';
            }
        }

        // Detailed logging of each element as requested
        extractedData.forEach(item => {
            const phoneStr = item.phone || 'N/A';
            console.log(`[ITEM_LOG] Element #${item.index}: ${item.title} | Phone: ${phoneStr} | ViewID: ${item.view_number}`);
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
