const { chromium } = require('playwright');

async function searchResellItems() {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1366, height: 768 });
    
    const results = [];

    // Try to search using DuckDuckGo first for resell tips
    try {
        console.log('Searching DuckDuckGo for resell tips...');
        await page.goto('https://duckduckgo.com/html/?q=barang+murah+dibawah+5+juta+resell+facebook+marketplace+indonesia', { 
            waitUntil: 'domcontentloaded', 
            timeout: 60000 
        });
        await page.waitForTimeout(3000);
        
        const results_ddg = await page.evaluate(() => {
            const items = [];
            const links = document.querySelectorAll('.result__snippet, .result__title, .web-result-description');
            links.forEach(link => {
                const text = link.innerText.trim();
                if (text.length > 30) {
                    items.push({ text: text.slice(0, 300) });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'DuckDuckGo Search Results', items: results_ddg });
        console.log('DuckDuckGo results:', results_ddg.length);
    } catch (e) {
        results.push({ source: 'DuckDuckGo', error: e.message });
        console.log('DuckDuckGo error:', e.message);
    }

    // Try OLX with different approach
    try {
        console.log('\nTrying OLX...');
        await page.goto('https://www.olx.co.id/elektronik-gadget_dijual', { 
            waitUntil: 'domcontentloaded', 
            timeout: 60000 
        });
        await page.waitForTimeout(5000);
        
        const olxItems = await page.evaluate(() => {
            const items = [];
            // Try multiple selectors
            const selectors = [
                '[data-aut-id="itemBox"]',
                '[data-cy="l-card"]',
                'li[data-aut-id="itemBox"]',
                '.EIR5N',
                '._2Vp0i'
            ];
            
            let cards = [];
            for (const sel of selectors) {
                cards = document.querySelectorAll(sel);
                if (cards.length > 0) break;
            }
            
            if (cards.length === 0) {
                // Fallback: find all list items with price-like content
                cards = document.querySelectorAll('li, article, div[class*="card"]');
            }
            
            cards.forEach(card => {
                const text = card.innerText.trim();
                if (text.length > 20 && (text.includes('Rp') || text.includes('RP') || text.includes('ribu') || text.includes('jt'))) {
                    items.push({ text: text.slice(0, 300) });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'OLX', items: olxItems });
        console.log('OLX items:', olxItems.length);
    } catch (e) {
        results.push({ source: 'OLX', error: e.message });
        console.log('OLX error:', e.message);
    }

    // Try Bukalapak
    try {
        console.log('\nTrying Bukalapak...');
        await page.goto('https://www.bukalapak.com/products?search%5Bkeywords%5D=elektronik%20murah%20dibawah%205%20juta', { 
            waitUntil: 'domcontentloaded', 
            timeout: 60000 
        });
        await page.waitForTimeout(5000);
        
        const blItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('[data-testid="product-card"], .bl-product-card, .product-card, [class*="product"]');
            cards.forEach(card => {
                const title = card.querySelector('[data-testid="product-title"], .bl-product-card__title, h3, h4, [class*="name"]');
                const price = card.querySelector('[data-testid="product-price"], .bl-product-card__price, [class*="price"]');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim(),
                        price: price.innerText.trim()
                    });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'Bukalapak', items: blItems });
        console.log('Bukalapak items:', blItems.length);
    } catch (e) {
        results.push({ source: 'Bukalapak', error: e.message });
        console.log('Bukalapak error:', e.message);
    }

    // Try Facebook Marketplace search with different approach
    try {
        console.log('\nTrying Facebook Marketplace (no login)...');
        await page.goto('https://www.facebook.com/marketplace/jakarta/search/?query=elektronik%20murah&minPrice=50000&maxPrice=5000000', { 
            waitUntil: 'domcontentloaded', 
            timeout: 60000 
        });
        await page.waitForTimeout(5000);
        
        // Try to dismiss login
        try {
            await page.click('[aria-label="Close"], [aria-label="Tutup"], button:has-text("Tidak Sekarang"), button:has-text("Not Now")', { timeout: 3000 });
        } catch {}
        
        await page.waitForTimeout(2000);
        
        // Scroll
        for (let i = 0; i < 3; i++) {
            await page.mouse.wheel(0, 2000);
            await page.waitForTimeout(2000);
        }
        
        const fbItems = await page.evaluate(() => {
            const items = [];
            const elements = document.querySelectorAll('[role="article"], [data-testid="marketplace-item"], div[class*="x1lliihq"]');
            elements.forEach(el => {
                const text = el.innerText.trim();
                if (text.length > 30 && (text.includes('Rp') || text.includes('Rp.'))) {
                    items.push({ text: text.slice(0, 400) });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'Facebook Marketplace Jakarta', items: fbItems });
        console.log('Facebook Marketplace items:', fbItems.length);
    } catch (e) {
        results.push({ source: 'Facebook Marketplace', error: e.message });
        console.log('Facebook Marketplace error:', e.message);
    }

    console.log('\n=== FINAL RESULTS ===');
    console.log(JSON.stringify(results, null, 2));
    await browser.close();
}

searchResellItems().catch(console.error);