import { test, expect } from '@playwright/test';

test('Import with sheet selection', async ({ page }) => {
  test.setTimeout(120000);

  // Login
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@erp.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);

  // Upload GIK UGM file (has multiple sheets)
  const fileInput = page.locator('input[type="file"]').first();
  await fileInput.setInputFiles('C:/Users/faras/.hermes/desktop-attachments/C.1 RAB GIK UGM Ulang-2.xlsx');

  // Wait for validation
  console.log('Waiting for validation...');
  await page.waitForTimeout(15000);

  // Check if sheet selection appears
  const sheetButtons = page.locator('button:has-text("RAB GIK UGM"), button:has-text("REKAP"), button:has-text("SCHD")');
  const sheetCount = await sheetButtons.count();
  console.log(`Sheet buttons found: ${sheetCount}`);

  if (sheetCount > 0) {
    // Click the correct sheet
    console.log('Clicking "RAB GIK UGM" sheet...');
    await page.locator('button:has-text("RAB GIK UGM")').first().click();

    // Wait for re-validation
    await page.waitForTimeout(15000);

    // Check validated
    const bodyText = await page.locator('body').textContent() || '';
    const hasValidated = bodyText.includes('VALIDATED');
    console.log(`After sheet select - Validated: ${hasValidated}`);

    if (hasValidated) {
      // Check total
      const totalMatch = bodyText.match(/Total Baris Valid:\s*(\d+)/);
      console.log(`Total rows: ${totalMatch?.[1]}`);

      // Check selected sheet displayed
      const hasSheetInfo = bodyText.includes('RAB GIK UGM');
      console.log(`Sheet name displayed: ${hasSheetInfo}`);
    }
  } else {
    console.log('No sheet selection - checking if single sheet was auto-selected');
    const bodyText = await page.locator('body').textContent() || '';
    console.log('Body:', bodyText.substring(0, 500));
  }
});
