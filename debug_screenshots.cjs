const { chromium } = require('playwright');

async function debugScreenshots() {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1366, height: 768 });
    
    // Tokopedia
    console.log('=== TOKOPEDIA ===');
    await page.goto('https://www.tokopedia.com/search?q=fujifilm%20camera%20murah', { 
        waitUntil: 'domcontentloaded', 
        timeout: 90000 
    });
    await page.waitForTimeout(10000);
    await page.screenshot({ path: 'tokopedia.png', fullPage: true });
    console.log('Screenshot saved: tokopedia.png');
    
    // Get HTML to see structure
    const html = await page.content();
    console.log('HTML length:', html.length);
    
    // Find all product-like elements
    const elements = await page.evaluate(() => {
        const results = [];
        const all = document.querySelectorAll('*');
        all.forEach(el => {
            const cls = el.className;
            const id = el.id;
            const text = el.innerText;
            if ((cls && typeof cls === 'string' && (cls.includes('product') || cls.includes('card') || cls.includes('item') || cls.includes('prd'))) ||
                (id && typeof id === 'string' && (id.includes('product') || id.includes('card') || id.includes('item')))) {
                if (text && text.length > 10 && text.length < 500) {
                    results.push({
                        tag: el.tagName,
                        class: cls,
                        id: id,
                        text: text.slice(0, 200)
                    });
                }
            }
        });
        return results.slice(0, 30);
    });
    console.log('Product-like elements:', JSON.stringify(elements, null, 2));
    
    // Shopee
    console.log('\n=== SHOPEE ===');
    await page.goto('https://shopee.co.id/search?keyword=fujifilm%20camera', { 
        waitUntil: 'domcontentloaded', 
        timeout: 90000 
    });
    await page.waitForTimeout(10000);
    await page.screenshot({ path: 'shopee.png', fullPage: true });
    console.log('Screenshot saved: shopee.png');
    
    // OLX
    console.log('\n=== OLX ===');
    await page.goto('https://www.olx.co.id/elektronik-gadget_dijual?q=fujifilm', { 
        waitUntil: 'domcontentloaded', 
        timeout: 90000 
    });
    await page.waitForTimeout(10000);
    await page.screenshot({ path: 'olx.png', fullPage: true });
    console.log('Screenshot saved: olx.png');
    
    await browser.close();
}

debugScreenshots().catch(console.error);