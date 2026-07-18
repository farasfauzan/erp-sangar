import { test, expect } from '@playwright/test';

test('Import Sekolah Rakyat Gorontalo RAB', async ({ page }) => {
  test.setTimeout(180000);

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
  console.log('Uploading C.1 Rev. RAB SEKOLAH GORONTALO-2.xlsx...');
  const fileInput = page.locator('input[type="file"]').first();
  await fileInput.setInputFiles('C:/Users/faras/.hermes/desktop-attachments/C.1 Rev. RAB SEKOLAH GORONTALO-2.xlsx');

  // Wait for validation
  console.log('Waiting for validation...');
  await page.waitForTimeout(15000);

  // Check sheet selection
  const bodyText = await page.locator('body').textContent() || '';
  const hasSheetSelection = bodyText.includes('beberapa sheet');
  console.log(`Sheet selection visible: ${hasSheetSelection}`);

  if (hasSheetSelection) {
    // Click correct sheet
    console.log('Clicking "RAB" sheet...');
    await page.locator('button:has-text("RAB")').first().click();
    await page.waitForTimeout(15000);
  }

  // Check validation result
  const afterValidation = await page.locator('body').textContent() || '';
  const hasValidated = afterValidation.includes('VALIDATED');
  const totalMatch = afterValidation.match(/Total Baris Valid:\s*(\d+)/);
  console.log(`Validated: ${hasValidated}`);
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
});
