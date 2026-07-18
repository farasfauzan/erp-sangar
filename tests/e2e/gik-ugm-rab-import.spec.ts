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

// Path to the GIK UGM RAB Excel file provided by user
const GIK_UGM_FILE = 'C:/Users/faras/rep-sangar/.hermes/desktop-attachments/C.1 RAB GIK UGM Ulang-3.xlsx';

test.describe('GIK UGM RAB Import - End-to-End', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('Complete GIK UGM RAB import flow', async ({ page }) => {
    test.setTimeout(300000); // 5 minutes for full import

    console.log('\n=== Starting GIK UGM RAB Import Test ===');

    // Navigate to RAB Import page
    await page.goto('/rab-import');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Verify page loaded correctly
    await expect(page.locator('h2:has-text("Import RAB dari Excel")')).toBeVisible({ timeout: 10000 });
    console.log('✅ RAB Import page loaded');

    // Step 1: Upload file
    console.log('\n--- Step 1: Upload File ---');
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(GIK_UGM_FILE);
    await page.waitForTimeout(1000);

    // Click upload button
    const uploadBtn = page.locator('button:has-text("Upload & Deteksi Sheet")');
    await expect(uploadBtn).toBeEnabled({ timeout: 10000 });
    await uploadBtn.click();

    // Wait for upload and sheet detection
    await page.waitForTimeout(15000);

    // Verify we moved to preview step (Step 2)
    await expect(page.locator('h3:has-text("Pilih Sheet RAB")')).toBeVisible({ timeout: 20000 });
    console.log('✅ File uploaded, sheet detection complete');

    // Get available sheets
    const sheetSelect = page.locator('select').first();
    const sheets = await sheetSelect.locator('option').allTextContents();
    console.log(`Available sheets: ${sheets.filter(s => s).join(', ')}`);

    // Select the main RAB sheet (likely "RAB GIK UGM" or similar)
    const rabSheet = sheets.find(s => s.includes('RAB') && !s.includes('SCHD') && !s.includes('REKAP')) || sheets[1];
    if (rabSheet) {
      await sheetSelect.selectOption(rabSheet);
      console.log(`✅ Selected sheet: ${rabSheet}`);
    } else {
      await sheetSelect.selectOption({ index: 1 });
      console.log('✅ Selected first data sheet');
    }

    // Select project
    const projectSelect = page.locator('select').nth(1);
    await expect(projectSelect).toBeVisible({ timeout: 5000 });
    const projects = await projectSelect.locator('option').allTextContents();
    console.log(`Available projects: ${projects.filter(p => p).join(', ')}`);
    
    // Select first available project (not the placeholder)
    if (projects.length > 1) {
      await projectSelect.selectOption({ index: 1 });
      console.log(`✅ Selected project: ${projects[1]}`);
    }

    // Step 2: Click Preview
    console.log('\n--- Step 2: Load Preview ---');
    const previewBtn = page.locator('button:has-text("Lihat Preview")');
    await expect(previewBtn).toBeEnabled({ timeout: 10000 });
    await previewBtn.click();

    // Wait for preview to load
    await page.waitForTimeout(20000);

    // Verify preview step (Step 3 - Validate)
    await expect(page.locator('h3:has-text("Validasi Data")')).toBeVisible({ timeout: 30000 });
    console.log('✅ Preview loaded');

    // Check preview data
    const previewRows = page.locator('tbody tr');
    const rowCount = await previewRows.count();
    console.log(`Preview rows loaded: ${rowCount}`);

    // Verify table has expected columns
    const headers = await page.locator('thead th').allTextContents();
    console.log(`Table headers: ${headers.join(' | ')}`);

    // Step 3: Run Validation
    console.log('\n--- Step 3: Run Validation ---');
    const validateBtn = page.locator('button:has-text("Validasi Total Price")');
    await expect(validateBtn).toBeEnabled({ timeout: 10000 });
    await validateBtn.click();

    // Wait for validation
    await page.waitForTimeout(30000);

    // Check validation result
    const validationResult = page.locator('text=Validasi berhasil, text=tidak ada error, text=error validasi');
    await expect(validationResult.first()).toBeVisible({ timeout: 40000 });
    console.log('✅ Validation complete');

    // Step 4: Confirm Import
    console.log('\n--- Step 4: Confirm Import ---');
    
    // Should now be on confirm step
    await expect(page.locator('h3:has-text("Konfirmasi Import")')).toBeVisible({ timeout: 20000 });

    // Check confirm step details
    const confirmInfo = page.locator('text=Estimasi Item');
    await expect(confirmInfo).toBeVisible({ timeout: 10000 });

    // Click Import button
    const importBtn = page.locator('button:has-text("Import Sekarang")');
    await expect(importBtn).toBeEnabled({ timeout: 10000 });
    await importBtn.click();

    // Wait for import to complete (this can take a while for large files)
    console.log('Waiting for import to complete...');
    await page.waitForTimeout(60000);

    // Step 5: Verify completion
    console.log('\n--- Step 5: Verify Completion ---');
    await expect(page.locator('h3:has-text("Import Selesai")')).toBeVisible({ timeout: 60000 });
    console.log('✅ Import completed successfully!');

    // Get success message
    const successMessage = await page.locator('text=Import selesai').textContent();
    console.log(`Success message: ${successMessage}`);

    // Verify we can navigate to RabStorage to see imported data
    console.log('\n--- Verify in RabStorage ---');
    await page.goto('/rab-storage');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Select the project we imported to
    const storageProjectSelect = page.locator('select#rab-storage-project');
    await expect(storageProjectSelect).toBeVisible({ timeout: 10000 });
    
    // Get project options and select the one we used
    const storageProjects = await storageProjectSelect.locator('option').allTextContents();
    if (storageProjects.length > 1) {
      await storageProjectSelect.selectOption({ index: 1 });
      await page.waitForTimeout(3000);
    }

    // Switch to Data tab
    await page.click('button:has-text("Data RAB")');
    await page.waitForTimeout(3000);

    // Check if data was imported
    const dataRows = page.locator('tbody tr');
    const importedCount = await dataRows.count();
    console.log(`Imported RAB items in storage: ${importedCount}`);
    
    expect(importedCount).toBeGreaterThan(0);
    console.log('✅ Data verified in RabStorage');
  });

  test('Quick validation - file upload and sheet detection only', async ({ page }) => {
    test.setTimeout(120000); // 2 minutes

    console.log('\n=== Quick Validation Test ===');

    await page.goto('/rab-import');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Upload file
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(GIK_UGM_FILE);
    await page.waitForTimeout(1000);

    const uploadBtn = page.locator('button:has-text("Upload & Deteksi Sheet")');
    await uploadBtn.click();
    await page.waitForTimeout(15000);

    // Verify sheet detection
    await expect(page.locator('h3:has-text("Pilih Sheet RAB")')).toBeVisible({ timeout: 20000 });
    
    const sheetSelect = page.locator('select').first();
    const sheets = await sheetSelect.locator('option').allTextContents();
    console.log(`Detected sheets: ${sheets.filter(s => s).join(', ')}`);
    
    // Should have multiple sheets (REKAP Tot, SCHD, SCHD (2), RAB GIK UGM)
    expect(sheets.length).toBeGreaterThan(2);
    
    // Check for expected sheets
    const hasRekap = sheets.some(s => s.includes('REKAP'));
    const hasSchd = sheets.some(s => s.includes('SCHD'));
    const hasRab = sheets.some(s => s.includes('RAB') || s.includes('GIK'));
    
    console.log(`Has REKAP: ${hasRekap}, Has SCHD: ${hasSchd}, Has RAB/GIK: ${hasRab}`);
    
    expect(hasRekap).toBeTruthy();
    expect(hasSchd).toBeTruthy();
    
    console.log('✅ Sheet detection working correctly');
  });
});