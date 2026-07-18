const { chromium } = require('playwright');

async function searchResellItems() {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    const results = [];

    try {
        // Search Tokopedia
        console.log('Searching Tokopedia...');
        await page.goto('https://www.tokopedia.com/search?q=elektronik%20murah%20dibawah%205%20juta', { 
            waitUntil: 'networkidle', 
            timeout: 60000 
        });
        await page.waitForTimeout(3000);
        
        const tokopediaItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('[data-testid="spnSRPProdCard"], .css-1sn1xa2, [class*="product-card"], [class*="ProductCard"]');
            cards.forEach(card => {
                const title = card.querySelector('[data-testid="spnSRPProdName"], .css-1bjwylw, [class*="product-name"], [class*="ProductName"], h3, [class*="title"]');
                const price = card.querySelector('[data-testid="spnSRPProdPrice"], .css-1ksb19c, [class*="price"], [class*="Price"]');
                const link = card.querySelector('a');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim(),
                        price: price.innerText.trim(),
                        link: link ? link.href : ''
                    });
                }
            });
            return items.slice(0, 10);
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
        await page.goto('https://shopee.co.id/search?keyword=elektronik%20murah%20dibawah%205%20juta', { 
            waitUntil: 'networkidle', 
            timeout: 60000 
        });
        await page.waitForTimeout(5000);
        
        const shopeeItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('.shopee-search-item-result__item, [class*="shop-search-result-item"], [data-sqe="item"]');
            cards.forEach(card => {
                const title = card.querySelector('[class*="name"], [class*="title"], .ie3A+n');
                const price = card.querySelector('[class*="price"], .ZEgDH9, [class*="Price"]');
                const link = card.querySelector('a');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim(),
                        price: price.innerText.trim(),
                        link: link ? link.href : ''
                    });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'Shopee', items: shopeeItems });
        console.log('Shopee items:', shopeeItems.length);
    } catch (e) {
        results.push({ source: 'Shopee', error: e.message });
        console.log('Shopee error:', e.message);
    }

    try {
        // Search Bukalapak
        console.log('Searching Bukalapak...');
        await page.goto('https://www.bukalapak.com/products?search%5Bkeywords%5D=elektronik%20murah%20dibawah%205%20juta', { 
            waitUntil: 'networkidle', 
            timeout: 60000 
        });
        await page.waitForTimeout(3000);
        
        const bukalapakItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('[data-testid="product-card"], .product-card, [class*="ProductCard"]');
            cards.forEach(card => {
                const title = card.querySelector('[data-testid="product-card-title"], .product-card-title, h3, [class*="title"]');
                const price = card.querySelector('[data-testid="product-card-price"], .product-card-price, [class*="price"]');
                const link = card.querySelector('a');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim(),
                        price: price.innerText.trim(),
                        link: link ? link.href : ''
                    });
                }
            });
            return items.slice(0, 10);
        });
        results.push({ source: 'Bukalapak', items: bukalapakItems });
        console.log('Bukalapak items:', bukalapakItems.length);
    } catch (e) {
        results.push({ source: 'Bukalapak', error: e.message });
        console.log('Bukalapak error:', e.message);
    }

    try {
        // Search OLX
        console.log('Searching OLX...');
        await page.goto('https://www.olx.co.id/elektronik_gadget/q-elektronik-murah-dibawah-5-juta', { 
            waitUntil: 'networkidle', 
            timeout: 60000 
        });
        await page.waitForTimeout(3000);
        
        const olxItems = await page.evaluate(() => {
            const items = [];
            const cards = document.querySelectorAll('[data-cy="l-card"], .css-1sw7q4x, [class*="offer-card"]');
            cards.forEach(card => {
                const title = card.querySelector('[data-cy="ad-title"], h6, [class*="title"]');
                const price = card.querySelector('[data-cy="ad-price"], [class*="price"]');
                const link = card.querySelector('a');
                if (title && price) {
                    items.push({
                        title: title.innerText.trim(),
                        price: price.innerText.trim(),
                        link: link ? link.href : ''
                    });
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

    console.log('\\n=== RESULTS ===');
    console.log(JSON.stringify(results, null, 2));
    await browser.close();
}

searchResellItems().catch(console.error);