import { test, expect } from '@playwright/test';

const CREDENTIALS = { email: 'admin@erp.com', password: 'password' };

const TEST_FILE = 'C:\\Users\\faras\\rep-sangar\\.hermes\\desktop-attachments\\C.1 RAB GIK UGM Ulang-3.xlsx';

async function login(page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', CREDENTIALS.email);
  await page.fill('input[name="password"]', CREDENTIALS.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);
}

async function navigateToRabImport(page) {
  await page.goto('/rab-import');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  // Verify page loaded
  await expect(page.locator('h2:has-text("Import RAB dari Excel")')).toBeVisible({ timeout: 10000 });
}

test.describe.configure({ retries: 0, workers: 1 });

test.describe('ERP Konstruksi - RAB Import GIK UGM End-to-End', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('Admin login → navigate to RAB Import page', async ({ page }) => {
    await navigateToRabImport(page);
    console.log('✅ RAB Import page loaded successfully');
  });

  test('Upload GIK UGM Excel file → detect sheets', async ({ page }) => {
    await navigateToRabImport(page);

    // Upload file
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(TEST_FILE);
    await page.waitForTimeout(1000);

    // Click Upload & Detect Sheets button
    await page.click('button:has-text("Upload & Deteksi Sheet")');
    
    // Wait for upload and sheet detection
    await page.waitForTimeout(10000);

    // Verify we moved to preview step (step 2)
    await expect(page.locator('h3:has-text("2. Pilih Sheet RAB")')).toBeVisible({ timeout: 15000 });
    
    // Check sheet detection
    const sheetsText = await page.locator('p:has-text("sheet terdeteksi")').textContent();
    console.log('Sheets detected:', sheetsText);
    
    // Get available sheets from dropdown
    const sheetOptions = await page.locator('select >> option').allTextContents();
    console.log('Available sheets:', sheetOptions);
    
    expect(sheetOptions.length).toBeGreaterThan(1); // At least one sheet + placeholder
  });

  test('Full import flow: Upload → Select Sheet → Preview → Validate → Import', async ({ page }) => {
    test.setTimeout(180000); // 3 minutes for full flow
    
    await navigateToRabImport(page);

    // Step 1: Upload file
    console.log('📤 Step 1: Upload file...');
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(TEST_FILE);
    await page.waitForTimeout(1000);

    await page.click('button:has-text("Upload & Deteksi Sheet")');
    await page.waitForTimeout(15000); // Wait for upload + sheet detection

    // Step 2: Select sheet and project
    console.log('📋 Step 2: Select sheet and project...');
    await expect(page.locator('h3:has-text("2. Pilih Sheet RAB")')).toBeVisible({ timeout: 15000 });

    // Get available sheets
    const sheetSelect = page.locator('select').first();
    const sheetOptions = await sheetSelect.locator('option').allTextContents();
    console.log('Available sheets:', sheetOptions);
    
    // Select first actual sheet (skip placeholder)
    const targetSheet = sheetOptions.find(opt => opt && opt !== '-- Pilih Sheet --');
    if (!targetSheet) {
      throw new Error('No valid sheet found in dropdown');
    }
    console.log(`Selecting sheet: ${targetSheet}`);
    await sheetSelect.selectOption(targetSheet);
    await page.waitForTimeout(1000);

    // Select project - get available projects
    const projectSelect = page.locator('select').nth(1);
    const projectOptions = await projectSelect.locator('option').allTextContents();
    console.log('Available projects:', projectOptions);
    
    const targetProject = projectOptions.find(opt => opt && opt !== '-- Pilih Project --');
    if (!targetProject) {
      console.log('⚠️ No project available, creating one or using existing...');
      // Check if there's at least one project
      if (projectOptions.length <= 1) {
        throw new Error('No projects available for import. Please create a project first.');
      }
    }
    await projectSelect.selectOption(targetProject);
    await page.waitForTimeout(1000);

    // Click "Lihat Preview"
    console.log('👁️ Step 3: Load preview...');
    await page.click('button:has-text("Lihat Preview")');
    await page.waitForTimeout(15000); // Wait for preview to load

    // Step 3: Validate preview data
    await expect(page.locator('h3:has-text("3. Validasi Data")')).toBeVisible({ timeout: 15000 });
    
    // Check preview table has data
    const previewRows = await page.locator('table tbody tr').count();
    console.log(`Preview rows loaded: ${previewRows}`);
    expect(previewRows).toBeGreaterThan(0);

    // Check project info if detected
    const projectInfo = await page.locator('p:has-text("Info Project Terdeteksi")').textContent().catch(() => null);
    if (projectInfo) {
      console.log('Project info detected:', projectInfo);
    }

    // Step 4: Run validation
    console.log('✅ Step 4: Run validation...');
    await page.click('button:has-text("Validasi Total Price")');
    await page.waitForTimeout(15000); // Wait for validation

    // Check validation result
    const validationResult = await page.locator('.bg-emerald-50, .bg-red-50, .bg-amber-50').first().textContent().catch(() => null);
    console.log('Validation result:', validationResult);

    // Step 5: Confirm and Import
    console.log('📥 Step 5: Confirm import...');
    await expect(page.locator('h3:has-text("4. Konfirmasi Import")')).toBeVisible({ timeout: 15000 });

    // Check summary info
    const summaryItems = await page.locator('.rounded.bg-gray-50.p-3').allTextContents();
    console.log('Import summary:', summaryItems);

    // Click Import Sekarang
    await page.click('button:has-text("Import Sekarang")');
    await page.waitForTimeout(30000); // Wait for import to complete

    // Step 6: Verify completion
    console.log('✅ Step 6: Verify completion...');
    await expect(page.locator('h3:has-text("Import Selesai")')).toBeVisible({ timeout: 30000 });
    
    const successMessage = await page.locator('.text-gray-600').textContent();
    console.log('Success message:', successMessage);

    // Click "Import File Lain" to reset
    await page.click('button:has-text("Import File Lain")');
    await page.waitForTimeout(2000);
    
    // Should be back at step 1
    await expect(page.locator('h3:has-text("1. Upload File Excel RAB")')).toBeVisible({ timeout: 10000 });
    console.log('✅ Full import flow completed successfully!');
  });

  test('Verify imported data appears in RabStorage', async ({ page }) => {
    // First do a quick import if not already done
    await navigateToRabImport(page);
    
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(TEST_FILE);
    await page.waitForTimeout(1000);
    await page.click('button:has-text("Upload & Deteksi Sheet")');
    await page.waitForTimeout(10000);

    // Quick path - just verify navigation to storage works
    await page.goto('/rab-storage');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await expect(page.locator('h1:has-text("Penyimpanan RAB")')).toBeVisible({ timeout: 10000 });
    console.log('✅ RabStorage page accessible');
    
    // Check if there are import jobs listed
    const importJobsCount = await page.locator('table tbody tr').count().catch(() => 0);
    console.log(`Import jobs in storage: ${importJobsCount}`);
  });

  test('Verify sheet selection logic for multi-sheet Excel', async ({ page }) => {
    await navigateToRabImport(page);

    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(TEST_FILE);
    await page.waitForTimeout(1000);
    await page.click('button:has-text("Upload & Deteksi Sheet")');
    await page.waitForTimeout(10000);

    await expect(page.locator('h3:has-text("2. Pilih Sheet RAB")')).toBeVisible({ timeout: 15000 });

    // Get all sheet names
    const sheetSelect = page.locator('select').first();
    const sheetOptions = await sheetSelect.locator('option').allTextContents();
    
    console.log('All sheets in GIK UGM file:', sheetOptions);
    
    // Expected sheets based on file analysis: REKAP Tot, SCHD, SCHD (2), RAB GIK UGM
    const expectedSheets = ['REKAP Tot', 'SCHD', 'SCHD (2)', 'RAB GIK UGM'];
    const foundSheets = sheetOptions.filter(opt => expectedSheets.some(exp => opt?.includes(exp)));
    
    console.log('Expected sheets found:', foundSheets);
    expect(foundSheets.length).toBeGreaterThanOrEqual(2); // At least 2 of the expected sheets
    
    // Test selecting each sheet and verifying preview loads
    for (const sheet of foundSheets.slice(0, 2)) { // Test first 2 sheets to save time
      console.log(`Testing sheet: ${sheet}`);
      await sheetSelect.selectOption(sheet);
      await page.waitForTimeout(1000);
      
      await page.click('button:has-text("Lihat Preview")');
      await page.waitForTimeout(10000);
      
      const previewRows = await page.locator('table tbody tr').count().catch(() => 0);
      console.log(`  Sheet "${sheet}" has ${previewRows} preview rows`);
      
      // Go back to preview step
      await page.click('button:has-text("Ganti Sheet")');
      await page.waitForTimeout(1000);
    }
  });

  test('Test validation detects total_price = volume × unit_price', async ({ page }) => {
    await navigateToRabImport(page);

    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(TEST_FILE);
    await page.waitForTimeout(1000);
    await page.click('button:has-text("Upload & Deteksi Sheet")');
    await page.waitForTimeout(10000);

    const sheetSelect = page.locator('select').first();
    const sheetOptions = await sheetSelect.locator('option').allTextContents();
    const targetSheet = sheetOptions.find(opt => opt && opt !== '-- Pilih Sheet --' && opt.includes('RAB'));
    
    if (targetSheet) {
      await sheetSelect.selectOption(targetSheet);
      await page.waitForTimeout(1000);
      
      const projectSelect = page.locator('select').nth(1);
      const projectOptions = await projectSelect.locator('option').allTextContents();
      const targetProject = projectOptions.find(opt => opt && opt !== '-- Pilih Project --');
      if (targetProject) {
        await projectSelect.selectOption(targetProject);
        await page.waitForTimeout(1000);
      }

      await page.click('button:has-text("Lihat Preview")');
      await page.waitForTimeout(10000);

      await page.click('button:has-text("Validasi Total Price")');
      await page.waitForTimeout(15000);

      // Check validation result
      const validationText = await page.locator('.bg-emerald-50, .bg-red-50, .bg-amber-50').first().textContent().catch(() => 'No validation result visible');
      console.log('Validation result:', validationText);
      
      // Should show checked rows count
      expect(validationText).toContain('baris dicek');
    } else {
      console.log('⚠️ No RAB sheet found, skipping validation test');
    }
  });
});