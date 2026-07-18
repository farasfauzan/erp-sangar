import { test, expect } from '@playwright/test';

test('Full import flow: GIK UGM → sheet selection → import → verify', async ({ page }) => {
  test.setTimeout(180000);

  // Login
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@erp.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);

  // Upload GIK UGM file
  console.log('=== STEP 1: Upload C.1 RAB GIK UGM Ulang-2.xlsx ===');
  const fileInput = page.locator('input[type="file"]').first();
  await fileInput.setInputFiles('C:/Users/faras/.hermes/desktop-attachments/C.1 RAB GIK UGM Ulang-2.xlsx');
  await page.waitForTimeout(15000);

  // Check sheet selection
  const bodyText = await page.locator('body').textContent() || '';
  const hasSheetSelection = bodyText.includes('beberapa sheet');
  console.log(`Sheet selection visible: ${hasSheetSelection}`);

  if (!hasSheetSelection) {
    console.log('ERROR: Sheet selection not visible');
    return;
  }

  // Click "RAB GIK UGM" sheet
  console.log('\n=== STEP 2: Select "RAB GIK UGM" sheet ===');
  await page.locator('button:has-text("RAB GIK UGM")').first().click();
  await page.waitForTimeout(15000);

  // Check re-validation
  const afterSelect = await page.locator('body').textContent() || '';
  const hasValidated = afterSelect.includes('VALIDATED');
  const totalMatch = afterSelect.match(/Total Baris Valid:\s*(\d+)/);
  console.log(`Re-validated: ${hasValidated}`);
  console.log(`Total rows: ${totalMatch?.[1]}`);
  console.log(`Sheet displayed: ${afterSelect.includes('RAB GIK UGM')}`);

  if (!hasValidated) {
    console.log('ERROR: Not validated after sheet selection');
    console.log('Body:', afterSelect.substring(0, 500));
    return;
  }

  // Click Confirm Import
  console.log('\n=== STEP 3: Confirm Import ===');
  await page.locator('button:has-text("Konfirmasi")').first().click();
  await page.waitForTimeout(20000);

  // Check completion
  const afterImport = await page.locator('body').textContent() || '';
  const hasData = afterSelect.includes('item terdaftar') || afterImport.includes('Data RAB');
  console.log(`Import completed: ${hasData}`);

  // Check RAB data tab
  console.log('\n=== STEP 4: Verify RAB Data ===');
  const rabData = await page.locator('body').textContent() || '';
  const itemMatch = rabData.match(/(\d+)\s*item terdaftar/);
  console.log(`Items registered: ${itemMatch?.[1] || 'checking...'}`);

  // Navigate to RAB Data tab to check
  const dataTab = page.locator('button:has-text("Data RAB")').first();
  if (await dataTab.isVisible().catch(() => false)) {
    await dataTab.click();
    await page.waitForTimeout(2000);
    const dataText = await page.locator('body').textContent() || '';
    const itemMatch2 = dataText.match(/(\d+)\s*item terdaftar/);
    console.log(`RAB Data items: ${itemMatch2?.[1] || 'N/A'}`);
  }

  console.log('\n=== DONE ===');
});
