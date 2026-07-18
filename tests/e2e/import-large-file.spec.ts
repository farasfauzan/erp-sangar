import { test, expect } from '@playwright/test';

test('Import B. RAB RSUD MENTAWAI FIX-2.xlsx (6.5MB)', async ({ page }) => {
  test.setTimeout(120000);

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

  // Wait for validation (async job)
  console.log('Waiting for validation...');
  await page.waitForTimeout(15000);

  // Check status
  const bodyText = await page.locator('body').textContent() || '';
  const hasPending = bodyText.includes('PENDING');
  const hasValidated = bodyText.includes('VALIDATED') || bodyText.includes('Tervalidasi');
  const hasFailed = bodyText.includes('FAILED') || bodyText.includes('Gagal');
  const hasError = bodyText.includes('Error') || bodyText.includes('error');

  console.log(`Pending: ${hasPending}, Validated: ${hasValidated}, Failed: ${hasFailed}, Error: ${hasError}`);

  // If validated, click confirm
  if (hasValidated) {
    console.log('Clicking Confirm Import...');
    const confirmBtn = page.locator('button:has-text("Konfirmasi"), button:has-text("Confirm"), button:has-text("Ya, Import")').first();
    await confirmBtn.click();
    await page.waitForTimeout(20000);

    const afterText = await page.locator('body').textContent() || '';
    const success = afterText.includes('berhasil') || afterText.includes('Berhasil');
    console.log(`After confirm - success: ${success}`);
  }

  // Get body snippet for debugging
  console.log(`Body snippet: ${bodyText.substring(0, 500)}`);
});
