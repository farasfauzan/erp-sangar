import { test, expect } from '@playwright/test';

test('Import GIK UGM with sheet selection', async ({ page }) => {
  test.setTimeout(180000);

  // Login
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@erp.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);

  // Monitor network
  const responses: string[] = [];
  page.on('response', async (response) => {
    if (response.url().includes('rab')) {
      let body = '';
      try { body = await response.text(); } catch {}
      responses.push(`${response.status()} ${response.url().split('?')[0]} → ${body.substring(0, 300)}`);
    }
  });

  // Upload GIK UGM file
  console.log('Uploading C.1 RAB GIK UGM Ulang-2.xlsx...');
  const fileInput = page.locator('input[type="file"]').first();
  await fileInput.setInputFiles('C:/Users/faras/.hermes/desktop-attachments/C.1 RAB GIK UGM Ulang-2.xlsx');

  // Wait for validation
  console.log('Waiting for validation (20s)...');
  await page.waitForTimeout(20000);

  // Print responses
  console.log('\n=== Network Responses ===');
  for (const r of responses) console.log(r);

  // Check page state
  const bodyText = await page.locator('body').textContent() || '';
  console.log('\n=== Page State ===');
  console.log('Has VALIDATED:', bodyText.includes('VALIDATED'));
  console.log('Has sheet buttons:', bodyText.includes('RAB GIK UGM') || bodyText.includes('REKAP'));
  console.log('Has "beberapa sheet":', bodyText.includes('beberapa sheet'));

  // Look for sheet selection
  const sheetSection = page.locator('text=beberapa sheet');
  if (await sheetSection.isVisible().catch(() => false)) {
    console.log('\n✅ Sheet selection visible!');
    const buttons = page.locator('.bg-blue-50 button');
    const count = await buttons.count();
    console.log(`Sheet buttons: ${count}`);
    for (let i = 0; i < count; i++) {
      const text = await buttons.nth(i).textContent();
      console.log(`  Button ${i}: ${text}`);
    }
  } else {
    console.log('\n❌ Sheet selection NOT visible');
    console.log('Body snippet:', bodyText.substring(0, 800));
  }
});
