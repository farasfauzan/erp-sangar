const { chromium } = require('playwright');

async function searchFujifilm() {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1366, height: 768 });
    
    const results = [];

    // Search Tokopedia for Fujifilm camera
    try {
        console.log('Searching Tokopedia for Fujifilm...');
        await page.goto('https://www.tokopedia.com/search?q=fujifilm%20camera%20murah', { 
            waitUntil: 'domcontentloaded', 
            timeout: 90000 
        });
        await page.waitForTimeout(8000);
        
        const tokopediaItems = await page.evaluate(() => {
            const items = [];
            // Try multiple selectors
            const cards = document.querySelectorAll('[data-testid="master-product-card"], .css-1sn1xa2, [class*="product-card"], [class*="prd_link"]');
            cards.forEach(card => {
                const title = card.querySelector('[data-testid="spnSRPProdName"], .css-1bjwylw, [class*="product-name"], h3, h4');
                const price = card.querySelector('[data-testid="spnSRPProdPrice"], .css-1ksb19c, [class*="price"]');
                const link = card.querySelector('a');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim().slice(0, 100),
                        price: price.innerText.trim(),
                        link: link ? link.href : ''
                    });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'Tokopedia - Fujifilm', items: tokopediaItems });
        console.log('Tokopedia Fujifilm items:', tokopediaItems.length);
        tokopediaItems.forEach(item => console.log('  -', item.title, item.price));
    } catch (e) {
        results.push({ source: 'Tokopedia - Fujifilm', error: e.message });
        console.log('Tokopedia error:', e.message);
    }

    // Search Shopee for Fujifilm
    try {
        console.log('\nSearching Shopee for Fujifilm...');
        await page.goto('https://shopee.co.id/search?keyword=fujifilm%20camera%20murah', { 
            waitUntil: 'domcontentloaded', 
            timeout: 90000 
        });
        await page.waitForTimeout(8000);
        
        const shopeeItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('.shopee-search-item-result__item, [data-sqe="item"], .col-xs-2-4');
            cards.forEach(card => {
                const title = card.querySelector('[class*="name"], [class*="title"], .ie3A+n, ._1NoI8_');
                const price = card.querySelector('[class*="price"], .ZEgDH9, ._1w9jLI');
                const link = card.querySelector('a');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim().slice(0, 100),
                        price: price.innerText.trim(),
                        link: link ? link.href : ''
                    });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'Shopee - Fujifilm', items: shopeeItems });
        console.log('Shopee Fujifilm items:', shopeeItems.length);
        shopeeItems.forEach(item => console.log('  -', item.title, item.price));
    } catch (e) {
        results.push({ source: 'Shopee - Fujifilm', error: e.message });
        console.log('Shopee error:', e.message);
    }

    // Search OLX for Fujifilm
    try {
        console.log('\nSearching OLX for Fujifilm...');
        await page.goto('https://www.olx.co.id/elektronik-gadget_dijual?q=fujifilm', { 
            waitUntil: 'domcontentloaded', 
            timeout: 90000 
        });
        await page.waitForTimeout(8000);
        
        const olxItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('[data-aut-id="itemBox"], [data-cy="l-card"], li[data-aut-id="itemBox"], .EIR5N');
            cards.forEach(card => {
                const title = card.querySelector('[data-aut-id="itemTitle"], h2, h3, [data-cy="ad-title"]');
                const price = card.querySelector('[data-aut-id="itemPrice"], [data-cy="ad-price"]');
                const location = card.querySelector('[data-aut-id="item-location"], [data-cy="ad-location"]');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim().slice(0, 100),
                        price: price.innerText.trim(),
                        location: location ? location.innerText.trim() : ''
                    });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'OLX - Fujifilm', items: olxItems });
        console.log('OLX Fujifilm items:', olxItems.length);
        olxItems.forEach(item => console.log('  -', item.title, item.price, item.location));
    } catch (e) {
        results.push({ source: 'OLX - Fujifilm', error: e.message });
        console.log('OLX error:', e.message);
    }

    // Search Facebook Marketplace for Fujifilm
    try {
        console.log('\nSearching Facebook Marketplace for Fujifilm...');
        await page.goto('https://www.facebook.com/marketplace/jakarta/search/?query=fujifilm%20camera&minPrice=500000&maxPrice=5000000', { 
            waitUntil: 'domcontentloaded', 
            timeout: 90000 
        });
        await page.waitForTimeout(8000);
        
        // Try to dismiss login
        try {
            await page.click('[aria-label="Close"], [aria-label="Tutup"], button:has-text("Tidak Sekarang"), button:has-text("Not Now")', { timeout: 3000 });
        } catch {}
        
        await page.waitForTimeout(3000);
        
        // Scroll
        for (let i = 0; i < 4; i++) {
            await page.mouse.wheel(0, 2000);
            await page.waitForTimeout(2000);
        }
        
        const fbItems = await page.evaluate(() => {
            const items = [];
            const elements = document.querySelectorAll('[role="article"], [data-testid="marketplace-item"]');
            elements.forEach(el => {
                const text = el.innerText.trim();
                if (text.length > 30 && (text.includes('Fujifilm') || text.includes('fujifilm') || text.includes('X-T') || text.includes('X-E') || text.includes('X-Pro') || text.includes('GFX') || text.includes('Instax'))) {
                    items.push({ text: text.slice(0, 500) });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'Facebook Marketplace - Fujifilm', items: fbItems });
        console.log('Facebook Marketplace Fujifilm items:', fbItems.length);
        fbItems.forEach(item => console.log('  -', item.text.slice(0, 150)));
    } catch (e) {
        results.push({ source: 'Facebook Marketplace - Fujifilm', error: e.message });
        console.log('Facebook Marketplace error:', e.message);
    }

    // Search Bukalapak for Fujifilm
    try {
        console.log('\nSearching Bukalapak for Fujifilm...');
        await page.goto('https://www.bukalapak.com/products?search%5Bkeywords%5D=fujifilm%20camera', { 
            waitUntil: 'domcontentloaded', 
            timeout: 90000 
        });
        await page.waitForTimeout(8000);
        
        const blItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('[data-testid="product-card"], .bl-product-card, .product-card, [class*="product-card"]');
            cards.forEach(card => {
                const title = card.querySelector('[data-testid="product-title"], .bl-product-card__title, h3, h4, [class*="product-name"]');
                const price = card.querySelector('[data-testid="product-price"], .bl-product-card__price, [class*="price"]');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim().slice(0, 100),
                        price: price.innerText.trim()
                    });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'Bukalapak - Fujifilm', items: blItems });
        console.log('Bukalapak Fujifilm items:', blItems.length);
        blItems.forEach(item => console.log('  -', item.title, item.price));
    } catch (e) {
        results.push({ source: 'Bukalapak - Fujifilm', error: e.message });
        console.log('Bukalapak error:', e.message);
    }

    console.log('\n=== FINAL RESULTS ===');
    console.log(JSON.stringify(results, null, 2));
    await browser.close();
}

searchFujifilm().catch(console.error);