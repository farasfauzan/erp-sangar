import { test, expect } from '@playwright/test';

const CREDENTIALS = { email: 'admin@erp.com', password: 'password' };

async function login(page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', CREDENTIALS.email);
  await page.fill('input[name="password"]', CREDENTIALS.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);
}

const FILES = [
  { name: 'B. RAB FIX-2', path: 'C:/Users/faras/.hermes/desktop-attachments/B. RAB RSUD MENTAWAI FIX-2.xlsx' },
  { name: 'RSUD MENTAWAI', path: 'C:/Users/faras/.hermes/desktop-attachments/RSUD MENTAWAI.xlsx' },
  { name: 'RSUD MENTAWAI vivi', path: 'C:/Users/faras/.hermes/desktop-attachments/RSUD MENTAWAI vivi.xlsx' },
  { name: 'PO Mentawai', path: 'C:/Users/faras/.hermes/desktop-attachments/Purchase Order RSUD Mentawai.xlsx' },
];

test('Import RAB files - all formats', async ({ page }) => {
  test.setTimeout(120000); // 2 minutes
  await login(page);

  for (const file of FILES) {
    console.log(`\n=== Testing: ${file.name} ===`);

    // Navigate to dashboard
    await page.goto('/dashboard');
    await page.waitForTimeout(2000);

    // Upload file via input
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(file.path);
    await page.waitForTimeout(1000);

    // The file upload triggers handleFileChange → posts to /rab/import-async
    // Wait for validation response
    await page.waitForTimeout(10000);

    // Check for error messages or validation results
    const bodyText = await page.locator('body').textContent() || '';
    
    // Look for import-related messages
    const hasImportResult = bodyText.includes('Import') || bodyText.includes('import') || bodyText.includes('validasi');
    const hasConfirmBtn = await page.locator('button:has-text("Konfirmasi"), button:has-text("Confirm"), button:has-text("import")').first().isVisible().catch(() => false);
    const hasErrorMsg = bodyText.includes('Error') || bodyText.includes('error') || bodyText.includes('Gagal');
    const hasSuccessMsg = bodyText.includes('berhasil') || bodyText.includes('Berhasil');

    console.log(`  Import result visible: ${hasImportResult}`);
    console.log(`  Confirm button visible: ${hasConfirmBtn}`);
    console.log(`  Has error: ${hasErrorMsg}`);
    console.log(`  Has success: ${hasSuccessMsg}`);

    // If confirm button is visible, click it
    if (hasConfirmBtn) {
      console.log(`  Clicking Confirm Import...`);
      await page.locator('button:has-text("Konfirmasi"), button:has-text("Confirm"), button:has-text("import")').first().click();
      
      // Wait for import to complete
      await page.waitForTimeout(15000);
      
      const afterText = await page.locator('body').textContent() || '';
      const successAfter = afterText.includes('berhasil') || afterText.includes('Berhasil');
      console.log(`  After confirm - success: ${successAfter}`);
    }

    // Check for "X items" or "Rp" in body (indicates data loaded)
    const hasItems = bodyText.includes('item') || bodyText.includes('Item');
    const hasCurrency = bodyText.includes('Rp');
    console.log(`  Has items text: ${hasItems}`);
    console.log(`  Has currency: ${hasCurrency}`);
  }
});
