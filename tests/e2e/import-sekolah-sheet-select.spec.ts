import { test, expect } from '@playwright/test';

test('Select sheet and import Sekolah Rakyat Gorontalo', async ({ page }) => {
  test.setTimeout(120000);

  // Login
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@erp.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);

  // Select Sekolah Rakyat Gorontalo project
  const projectSelect = page.locator('select').first();
  await projectSelect.selectOption({ label: 'Sekolah Rakyat Gorontalo' });
  await page.waitForTimeout(1000);

  // Upload file
  console.log('Uploading file...');
  const fileInput = page.locator('input[type="file"]').first();
  await fileInput.setInputFiles('C:/Users/faras/.hermes/desktop-attachments/C.1 Rev. RAB SEKOLAH GORONTALO-2.xlsx');

  // Wait for validation (longer this time)
  console.log('Waiting for validation (25s)...');
  await page.waitForTimeout(25000);

  // Check sheet selection
  const bodyText = await page.locator('body').textContent() || '';
  const hasSheetSelection = bodyText.includes('beberapa sheet');
  console.log(`Sheet selection visible: ${hasSheetSelection}`);

  if (hasSheetSelection) {
    // Click "RAB" sheet
    console.log('Clicking "RAB" sheet...');
    await page.locator('button:has-text("RAB")').first().click();
    
    // Wait for re-validation
    console.log('Waiting for re-validation (15s)...');
    await page.waitForTimeout(15000);

    // Check validated
    const afterRevalidation = await page.locator('body').textContent() || '';
    const hasValidated = afterRevalidation.includes('VALIDATED');
    const totalMatch = afterRevalidation.match(/Total Baris Valid:\s*(\d+)/);
    console.log(`After re-validation - Validated: ${hasValidated}`);
    console.log(`Total rows: ${totalMatch?.[1]}`);

    if (hasValidated) {
      // Click Confirm Import
      console.log('Clicking Confirm Import...');
      await page.locator('button:has-text("Konfirmasi")').first().click();
      await page.waitForTimeout(20000);

      // Check completion
      const afterImport = await page.locator('body').textContent() || '';
      const hasData = afterImport.includes('item terdaftar') || afterImport.includes('Data RAB');
      console.log(`Import completed: ${hasData}`);
    }
  } else {
    console.log('No sheet selection - checking body:');
    console.log(bodyText.substring(0, 500));
  }
});
