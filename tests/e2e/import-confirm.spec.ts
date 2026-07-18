import { test, expect } from '@playwright/test';

test('Import B. RAB RSUD MENTAWAI FIX-2 - Full Flow', async ({ page }) => {
  test.setTimeout(180000);

  // Login
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@erp.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);

  // Upload file
  const fileInput = page.locator('input[type="file"]').first();
  await fileInput.setInputFiles('C:/Users/faras/.hermes/desktop-attachments/B. RAB RSUD MENTAWAI FIX-2.xlsx');

  // Wait for validation
  console.log('Waiting for validation...');
  await page.waitForTimeout(15000);

  // Check if validated
  const bodyText = await page.locator('body').textContent() || '';
  if (bodyText.includes('VALIDATED')) {
    console.log('Validated! Clicking Confirm...');

    // Click Confirm Import button
    const confirmBtn = page.locator('button:has-text("Konfirmasi"), button:has-text("Confirm")').first();
    await confirmBtn.click();
    console.log('Confirm clicked! Waiting for import...');

    // Wait for import to complete (poll for status)
    await page.waitForTimeout(30000);

    const afterText = await page.locator('body').textContent() || '';
    console.log('Body snippet:', afterText.substring(0, 500));
  } else {
    console.log('Not validated yet. Body:', bodyText.substring(0, 300));
  }
});
