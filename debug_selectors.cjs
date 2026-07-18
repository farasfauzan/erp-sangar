const { chromium } = require('playwright');

async function debugSelectors() {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1366, height: 768 });
    
    // Try Tokopedia search
    console.log('=== TOKOPEDIA DEBUG ===');
    await page.goto('https://www.tokopedia.com/search?q=fujifilm%20camera', { 
        waitUntil: 'domcontentloaded', 
        timeout: 90000 
    });
    await page.waitForTimeout(10000);
    
    const pageContent = await page.evaluate(() => {
        // Get all elements with class names
        const elements = document.querySelectorAll('[class]');
        const classes = new Set();
        elements.forEach(el => {
            const cls = el.className;
            if (cls && typeof cls === 'string') {
                cls.split(' ').forEach(c => {
                    if (c.includes('product') || c.includes('card') || c.includes('item') || c.includes('prd') || c.includes('spn')) {
                        classes.add(c);
                    }
                });
            }
        });
        return Array.from(classes).slice(0, 50);
    });
    console.log('Product-related classes:', pageContent);
    
    // Get all text content that mentions fujifilm
    const fujifilmText = await page.evaluate(() => {
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const texts = [];
        while (walker.nextNode()) {
            const text = walker.currentNode.textContent.trim();
            if (text.toLowerCase().includes('fujifilm') || text.toLowerCase().includes('x-t') || text.toLowerCase().includes('x-e') || text.toLowerCase().includes('instax')) {
                texts.push(text.slice(0, 200));
            }
        }
        return texts.slice(0, 20);
    });
    console.log('Fujifilm mentions:', fujifilmText);
    
    // Try OLX
    console.log('\n=== OLX DEBUG ===');
    await page.goto('https://www.olx.co.id/elektronik-gadget_dijual?q=fujifilm', { 
        waitUntil: 'domcontentloaded', 
        timeout: 90000 
    });
    await page.waitForTimeout(8000);
    
    const olxClasses = await page.evaluate(() => {
        const elements = document.querySelectorAll('[class]');
        const classes = new Set();
        elements.forEach(el => {
            const cls = el.className;
            if (cls && typeof cls === 'string') {
                cls.split(' ').forEach(c => {
                    if (c.includes('item') || c.includes('card') || c.includes('ad') || c.includes('list')) {
                        classes.add(c);
                    }
                });
            }
        });
        return Array.from(classes).slice(0, 50);
    });
    console.log('OLX item classes:', olxClasses);
    
    const olxText = await page.evaluate(() => {
        const texts = [];
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const text = walker.currentNode.textContent.trim();
            if (text.toLowerCase().includes('fujifilm') || text.toLowerCase().includes('x-t') || text.toLowerCase().includes('instax')) {
                texts.push(text.slice(0, 200));
            }
        }
        return texts.slice(0, 20);
    });
    console.log('OLX Fujifilm mentions:', olxText);
    
    await browser.close();
}

debugSelectors().catch(console.error);