const { chromium } = require('playwright');

async function searchResellItems() {
    const browser = await chromium.launch({ 
        headless: false, // Use headed mode to bypass bot detection
        args: ['--disable-blink-features=AutomationControlled']
    });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        viewport: { width: 1280, height: 720 }
    });
    const page = await context.newPage();
    const results = [];

    try {
        // Search Tokopedia with simpler query
        console.log('Searching Tokopedia...');
        await page.goto('https://www.tokopedia.com/search?q=headphone%20murah', { 
            waitUntil: 'domcontentloaded', 
            timeout: 60000 
        });
        await page.waitForTimeout(5000);
        
        const tokopediaItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('[data-testid="spnSRPProdCard"], .css-1sn1xa2');
            cards.forEach(card => {
                const title = card.querySelector('[data-testid="spnSRPProdName"], .css-1bjwylw');
                const price = card.querySelector('[data-testid="spnSRPProdPrice"], .css-1ksb19c');
                const link = card.querySelector('a');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim(),
                        price: price.innerText.trim(),
                        link: link ? link.href : ''
                    });
                }
            });
            return items.slice(0, 8);
        });
        results.push({ source: 'Tokopedia', items: tokopediaItems });
        console.log('Tokopedia items:', tokopediaItems.length);
    } catch (e) {
        results.push({ source: 'Tokopedia', error: e.message });
        console.log('Tokopedia error:', e.message);
    }

    try {
        // Search Shopee
        console.log('Searching Shopee...');
        await page.goto('https://shopee.co.id/search?keyword=headphone%20murah', { 
            waitUntil: 'domcontentloaded', 
            timeout: 60000 
        });
        await page.waitForTimeout(5000);
        
        const shopeeItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('.shopee-search-item-result__item, [data-sqe="item"]');
            cards.forEach(card => {
                const title = card.querySelector('[class*="name"], [class*="title"], .ie3A+n');
                const price = card.querySelector('[class*="price"], .ZEgDH9');
                const link = card.querySelector('a');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim(),
                        price: price.innerText.trim(),
                        link: link ? link.href : ''
                    });
                }
            });
            return items.slice(0, 8);
        });
        results.push({ source: 'Shopee', items: shopeeItems });
        console.log('Shopee items:', shopeeItems.length);
    } catch (e) {
        results.push({ source: 'Shopee', error: e.message });
        console.log('Shopee error:', e.message);
    }

    try {
        // Try Facebook Marketplace (public search without login)
        console.log('Searching Facebook Marketplace...');
        await page.goto('https://www.facebook.com/marketplace/search/?query=elektronik%20murah&minPrice=50000&maxPrice=5000000&exact=false', { 
            waitUntil: 'domcontentloaded', 
            timeout: 60000 
        });
        await page.waitForTimeout(5000);
        
        // Try to close login modal
        try {
            await page.click('div[aria-label="Close"]', { timeout: 3000 });
        } catch {}
        
        await page.waitForTimeout(2000);
        
        // Scroll to load more
        for (let i = 0; i < 3; i++) {
            await page.mouse.wheel(0, 1500);
            await page.waitForTimeout(1500);
        }
        
        const fbItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('[role="article"], [data-testid="marketplace-item"]');
            cards.forEach(card => {
                const text = card.innerText.trim();
                if (text.length > 30) {
                    items.push({ text: text.slice(0, 300) });
                }
            });
            return items.slice(0, 8);
        });
        results.push({ source: 'Facebook Marketplace', items: fbItems });
        console.log('Facebook Marketplace items:', fbItems.length);
    } catch (e) {
        results.push({ source: 'Facebook Marketplace', error: e.message });
        console.log('Facebook Marketplace error:', e.message);
    }

    console.log('\n=== RESULTS ===');
    console.log(JSON.stringify(results, null, 2));
    await browser.close();
}

searchResellItems().catch(console.error);