import { test, expect } from '@playwright/test';

test('Import B. RAB RSUD MENTAWAI FIX-2.xlsx', async ({ page }) => {
  test.setTimeout(120000);

  // Login
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@erp.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);

  // Monitor network requests
  const requests: string[] = [];
  page.on('response', async (response) => {
    const url = response.url();
    if (url.includes('rab') || url.includes('import')) {
      const status = response.status();
      let body = '';
      try { body = await response.text(); } catch {}
      requests.push(`${status} ${url} → ${body.substring(0, 200)}`);
    }
  });

  // Upload file
  const fileInput = page.locator('input[type="file"]').first();
  await fileInput.setInputFiles('C:/Users/faras/.hermes/desktop-attachments/B. RAB RSUD MENTAWAI FIX-2.xlsx');

  // Wait for response
  await page.waitForTimeout(15000);

  // Print all captured requests
  console.log('=== Network Requests ===');
  for (const r of requests) console.log(r);

  // Check page state
  const bodyText = await page.locator('body').textContent() || '';
  const statusEl = page.locator('text=Status').first();
  if (await statusEl.isVisible().catch(() => false)) {
    const parent = statusEl.locator('..');
    console.log('Status section:', await parent.textContent());
  }

  // Check for error in page
  const errorEls = page.locator('.text-red-500, .text-red-600, [role="alert"]');
  const errorCount = await errorEls.count();
  for (let i = 0; i < errorCount; i++) {
    const text = await errorEls.nth(i).textContent();
    console.log(`Error ${i}: ${text}`);
  }
});
