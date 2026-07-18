import { test, expect } from '@playwright/test';

test('Import small file + large file', async ({ page }) => {
  test.setTimeout(120000);

  // Login
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@erp.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);

  // Test 1: Small file (19KB)
  console.log('=== Test 1: RSUD MENTAWAI.xlsx (19KB) ===');
  const requests1: string[] = [];
  page.on('response', async (response) => {
    if (response.url().includes('rab')) {
      requests1.push(`${response.status()} ${response.url()}`);
    }
  });

  const fileInput1 = page.locator('input[type="file"]').first();
  await fileInput1.setInputFiles('C:/Users/faras/.hermes/desktop-attachments/RSUD MENTAWAI.xlsx');
  await page.waitForTimeout(10000);

  for (const r of requests1) console.log(`  ${r}`);
  const bodyText1 = await page.locator('body').textContent() || '';
  const hasValidation = bodyText1.includes('VALIDATED') || bodyText1.includes('Tervalidasi');
  console.log(`  Validated: ${hasValidation}`);

  // Test 2: Large file (6.5MB) - after small file works
  console.log('\n=== Test 2: B. RAB RSUD MENTAWAI FIX-2.xlsx (6.5MB) ===');
  
  // Reset page
  await page.goto('/dashboard');
  await page.waitForTimeout(2000);

  const requests2: string[] = [];
  page.on('response', async (response) => {
    if (response.url().includes('rab')) {
      let body = '';
      try { body = await response.text(); } catch {}
      requests2.push(`${response.status()} ${response.url()} → ${body.substring(0, 200)}`);
    }
  });

  const fileInput2 = page.locator('input[type="file"]').first();
  await fileInput2.setInputFiles('C:/Users/faras/.hermes/desktop-attachments/B. RAB RSUD MENTAWAI FIX-2.xlsx');
  await page.waitForTimeout(15000);

  for (const r of requests2) console.log(`  ${r}`);
  const bodyText2 = await page.locator('body').textContent() || '';
  const hasValidation2 = bodyText2.includes('VALIDATED') || bodyText2.includes('Tervalidasi');
  console.log(`  Validated: ${hasValidation2}`);
});
