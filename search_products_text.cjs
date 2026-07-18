const { chromium } = require('playwright');

async function searchProducts() {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1366, height: 768 });
    
    const allResults = [];

    // Search Tokopedia - use text extraction approach
    try {
        console.log('=== TOKOPEDIA - Fujifilm Camera ===');
        await page.goto('https://www.tokopedia.com/search?q=fujifilm%20camera%20murah%20dibawah%205%20juta', { 
            waitUntil: 'domcontentloaded', 
            timeout: 90000 
        });
        await page.waitForTimeout(10000);
        
        const items = await page.evaluate(() => {
            const results = [];
            // Get all text nodes and look for product patterns
            const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            const texts = [];
            while (walker.nextNode()) {
                const text = walker.currentNode.textContent.trim();
                if (text.length > 10) texts.push(text);
            }
            
            // Look for product patterns: title followed by price
            for (let i = 0; i < texts.length - 1; i++) {
                const text = texts[i];
                const nextText = texts[i + 1];
                
                // Check if this looks like a product title (has camera/lens keywords)
                const isProduct = /fujifilm|x-t|x-e|x-pro|gfx|instax|camera|lens|flash|battery|charger|case|strap/i.test(text);
                // Check if next text looks like a price
                const isPrice = /^Rp\s?[\d.,]+|^\d+[.,]\d{3}|^Rp\d/.test(nextText);
                
                if (isProduct && isPrice && text.length < 200) {
                    results.push({
                        title: text.slice(0, 120),
                        price: nextText.slice(0, 30)
                    });
                }
            }
            
            // Also look for elements that contain both title and price
            const allElements = document.querySelectorAll('*');
            allElements.forEach(el => {
                const text = el.innerText.trim();
                if (text.length > 20 && text.length < 500) {
                    const hasFujifilm = /fujifilm|x-t|x-e|x-pro|gfx|instax/i.test(text);
                    const hasPrice = /Rp\s?[\d.,]+/.test(text);
                    if (hasFujifilm && hasPrice) {
                        // Try to split title and price
                        const lines = text.split('\n').map(l => l.trim()).filter(l => l);
                        if (lines.length >= 2) {
                            results.push({
                                title: lines[0].slice(0, 120),
                                price: lines.find(l => /Rp\s?[\d.,]+/.test(l))?.slice(0, 30) || lines[1].slice(0, 30)
                            });
                        }
                    }
                }
            });
            
            // Deduplicate
            const seen = new Set();
            return results.filter(r => {
                const key = r.title + r.price;
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            }).slice(0, 15);
        });
        
        allResults.push({ source: 'Tokopedia - Fujifilm Camera <5Juta', items });
        console.log('Found:', items.length);
        items.forEach((item, i) => console.log(`  ${i+1}. ${item.title} - ${item.price}`));
    } catch (e) {
        console.log('Tokopedia error:', e.message);
        allResults.push({ source: 'Tokopedia', error: e.message });
    }

    // Search Shopee
    try {
        console.log('\n=== SHOPEE - Fujifilm Camera ===');
        await page.goto('https://shopee.co.id/search?keyword=fujifilm%20camera%20murah%20dibawah%205%20juta', { 
            waitUntil: 'domcontentloaded', 
            timeout: 90000 
        });
        await page.waitForTimeout(10000);
        
        const items = await page.evaluate(() => {
            const results = [];
            const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            const texts = [];
            while (walker.nextNode()) {
                const text = walker.currentNode.textContent.trim();
                if (text.length > 5) texts.push(text);
            }
            
            for (let i = 0; i < texts.length - 1; i++) {
                const text = texts[i];
                const nextText = texts[i + 1];
                
                const isProduct = /fujifilm|x-t|x-e|x-pro|gfx|instax|camera|lens|flash|battery|charger|case|strap/i.test(text);
                const isPrice = /^Rp\s?[\d.,]+|^\d+[.,]\d{3}/.test(nextText);
                
                if (isProduct && isPrice && text.length < 200) {
                    results.push({
                        title: text.slice(0, 120),
                        price: nextText.slice(0, 30)
                    });
                }
            }
            
            const seen = new Set();
            return results.filter(r => {
                const key = r.title + r.price;
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            }).slice(0, 15);
        });
        
        allResults.push({ source: 'Shopee - Fujifilm Camera <5Juta', items });
        console.log('Found:', items.length);
        items.forEach((item, i) => console.log(`  ${i+1}. ${item.title} - ${item.price}`));
    } catch (e) {
        console.log('Shopee error:', e.message);
        allResults.push({ source: 'Shopee', error: e.message });
    }

    // Search OLX
    try {
        console.log('\n=== OLX - Fujifilm ===');
        await page.goto('https://www.olx.co.id/elektronik-gadget_dijual?q=fujifilm', { 
            waitUntil: 'domcontentloaded', 
            timeout: 90000 
        });
        await page.waitForTimeout(10000);
        
        const items = await page.evaluate(() => {
            const results = [];
            const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            const texts = [];
            while (walker.nextNode()) {
                const text = walker.currentNode.textContent.trim();
                if (text.length > 5) texts.push(text);
            }
            
            for (let i = 0; i < texts.length - 1; i++) {
                const text = texts[i];
                const nextText = texts[i + 1];
                
                const isProduct = /fujifilm|x-t|x-e|x-pro|gfx|instax|camera|lens|flash|battery|charger/i.test(text);
                const isPrice = /Rp\s?[\d.,]+/.test(nextText) || /^\d+[.,]\d{3}/.test(nextText);
                
                if (isProduct && isPrice && text.length < 200) {
                    results.push({
                        title: text.slice(0, 120),
                        price: nextText.slice(0, 30)
                    });
                }
            }
            
            const seen = new Set();
            return results.filter(r => {
                const key = r.title + r.price;
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            }).slice(0, 15);
        });
        
        allResults.push({ source: 'OLX - Fujifilm', items });
        console.log('Found:', items.length);
        items.forEach((item, i) => console.log(`  ${i+1}. ${item.title} - ${item.price}`));
    } catch (e) {
        console.log('OLX error:', e.message);
        allResults.push({ source: 'OLX', error: e.message });
    }

    // Search Facebook Marketplace - Jakarta
    try {
        console.log('\n=== FACEBOOK MARKETPLACE - Jakarta Fujifilm ===');
        await page.goto('https://www.facebook.com/marketplace/jakarta/search/?query=fujifilm%20camera&minPrice=500000&maxPrice=5000000', { 
            waitUntil: 'domcontentloaded', 
            timeout: 90000 
        });
        await page.waitForTimeout(10000);
        
        // Try to dismiss login
        try {
            await page.click('[aria-label="Close"], [aria-label="Tutup"], button:has-text("Tidak Sekarang"), button:has-text("Not Now")', { timeout: 3000 });
        } catch {}
        
        await page.waitForTimeout(3000);
        
        // Scroll
        for (let i = 0; i < 5; i++) {
            await page.mouse.wheel(0, 3000);
            await page.waitForTimeout(2000);
        }
        
        const items = await page.evaluate(() => {
            const results = [];
            const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            const texts = [];
            while (walker.nextNode()) {
                const text = walker.currentNode.textContent.trim();
                if (text.length > 10) texts.push(text);
            }
            
            for (let i = 0; i < texts.length; i++) {
                const text = texts[i];
                // Look for Fujifilm camera listings with price
                if (/fujifilm|x-t|x-e|x-pro|gfx|instax/i.test(text) && /Rp\s?[\d.,]+/.test(text) && text.length < 500) {
                    results.push({ text: text.slice(0, 500) });
                }
            }
            
            const seen = new Set();
            return results.filter(r => {
                const key = r.text.slice(0, 100);
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            }).slice(0, 10);
        });
        
        allResults.push({ source: 'Facebook Marketplace Jakarta - Fujifilm', items });
        console.log('Found:', items.length);
        items.forEach((item, i) => console.log(`  ${i+1}. ${item.text.slice(0, 200)}`));
    } catch (e) {
        console.log('Facebook error:', e.message);
        allResults.push({ source: 'Facebook Marketplace', error: e.message });
    }

    // Search Bukalapak
    try {
        console.log('\n=== BUKALAPAK - Fujifilm ===');
        await page.goto('https://www.bukalapak.com/products?search%5Bkeywords%5D=fujifilm%20camera%20murah', { 
            waitUntil: 'domcontentloaded', 
            timeout: 90000 
        });
        await page.waitForTimeout(10000);
        
        const items = await page.evaluate(() => {
            const results = [];
            const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            const texts = [];
            while (walker.nextNode()) {
                const text = walker.currentNode.textContent.trim();
                if (text.length > 5) texts.push(text);
            }
            
            for (let i = 0; i < texts.length - 1; i++) {
                const text = texts[i];
                const nextText = texts[i + 1];
                
                const isProduct = /fujifilm|x-t|x-e|x-pro|gfx|instax|camera|lens|flash|battery|charger/i.test(text);
                const isPrice = /Rp\s?[\d.,]+/.test(nextText);
                
                if (isProduct && isPrice && text.length < 200) {
                    results.push({
                        title: text.slice(0, 120),
                        price: nextText.slice(0, 30)
                    });
                }
            }
            
            const seen = new Set();
            return results.filter(r => {
                const key = r.title + r.price;
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            }).slice(0, 15);
        });
        
        allResults.push({ source: 'Bukalapak - Fujifilm', items });
        console.log('Found:', items.length);
        items.forEach((item, i) => console.log(`  ${i+1}. ${item.title} - ${item.price}`));
    } catch (e) {
        console.log('Bukalapak error:', e.message);
        allResults.push({ source: 'Bukalapak', error: e.message });
    }

    console.log('\n=== FINAL SUMMARY ===');
    allResults.forEach(r => {
        if (r.items) {
            console.log(`${r.source}: ${r.items.length} items`);
        } else {
            console.log(`${r.source}: ERROR - ${r.error}`);
        }
    });
    
    await browser.close();
}

searchProducts().catch(console.error);