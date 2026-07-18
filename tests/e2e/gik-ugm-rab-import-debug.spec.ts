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

const GIK_UGM_FILE = 'C:/Users/faras/rep-sangar/.hermes/desktop-attachments/C.1 RAB GIK UGM Ulang-3.xlsx';

test.describe.configure({ retries: 0, workers: 1 });

test.describe('GIK UGM RAB Import - Debug & Full Flow', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('Debug: Upload file and capture DOM after each step', async ({ page }) => {
    test.setTimeout(300000);

    console.log('\n=== DEBUG TEST: Capture DOM at each step ===');

    await page.goto('/rab-import');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Log initial page state
    const initialTitle = await page.locator('h2').first().textContent();
    console.log('Initial page title:', initialTitle);

    // Upload file
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(GIK_UGM_FILE);
    await page.waitForTimeout(1000);

    console.log('\n--- Clicking Upload button ---');
    const uploadBtn = page.locator('button:has-text("Upload & Deteksi Sheet")');
    await expect(uploadBtn).toBeEnabled({ timeout: 10000 });
    await uploadBtn.click();

    // Wait longer for large file upload + sheet detection
    console.log('Waiting 60s for upload + sheet detection...');
    await page.waitForTimeout(60000);

    // Capture DOM to see what's rendered
    const bodyText = await page.locator('body').textContent();
    console.log('\n=== PAGE CONTENT AFTER UPLOAD ===');
    console.log(bodyText?.substring(0, 3000) || 'EMPTY');

    // Check for step indicators
    const stepIndicators = await page.locator('.flex.items-center.justify-between').first().textContent();
    console.log('\nStep indicator:', stepIndicators);

    // Check all h3 elements
    const h3s = await page.locator('h3').allTextContents();
    console.log('All h3 elements:', h3s);

    // Check all buttons
    const buttons = await page.locator('button').allTextContents();
    console.log('All buttons:', buttons.filter(b => b.trim()));

    // Check selects
    const selects = await page.locator('select').count();
    console.log(`Number of select elements: ${selects}`);
    if (selects > 0) {
      for (let i = 0; i < selects; i++) {
        const options = await page.locator('select').nth(i).locator('option').allTextContents();
        console.log(`  Select ${i}: ${options.filter(o => o.trim()).join(', ')}`);
      }
    }

    // Check for any error messages
    const errors = await page.locator('.bg-red-50, .text-red-800, .alert-error').allTextContents();
    if (errors.length > 0) {
      console.log('ERRORS FOUND:', errors);
    }

    // Check for loading states
    const loading = await page.locator('text=Mengupload, text=Memuat, .animate-spin').count();
    console.log(`Loading indicators: ${loading}`);

    console.log('\n=== END DEBUG ===');
  });

  test('Full import flow with extended timeouts', async ({ page }) => {
    test.setTimeout(600000); // 10 minutes

    console.log('\n=== FULL GIK UGM RAB IMPORT ===');

    await page.goto('/rab-import');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await expect(page.locator('h2:has-text("Import RAB dari Excel")')).toBeVisible({ timeout: 10000 });
    console.log('✅ Page loaded');

    // Step 1: Upload
    console.log('\n--- Step 1: Upload ---');
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(GIK_UGM_FILE);
    await page.waitForTimeout(1000);

    const uploadBtn = page.locator('button:has-text("Upload & Deteksi Sheet")');
    await uploadBtn.click();

    // Extended wait for 2.8MB file with 4 sheets
    console.log('Waiting 90s for upload + sheet detection...');
    await page.waitForTimeout(90000);

    // Try multiple selectors for step 2
    let step2Visible = false;
    const step2Selectors = [
      'h3:has-text("Pilih Sheet RAB")',
      'h3:has-text("2. Pilih Sheet")',
      'text=Pilih Sheet RAB',
      'text=Sheet RAB',
      'select >> option',
    ];

    for (const sel of step2Selectors) {
      try {
        await expect(page.locator(sel).first()).toBeVisible({ timeout: 5000 });
        console.log(`✅ Step 2 detected via: ${sel}`);
        step2Visible = true;
        break;
      } catch {
        // continue
      }
    }

    if (!step2Visible) {
      console.log('⚠️ Step 2 not detected, dumping page state...');
      const bodyText = await page.locator('body').textContent();
      console.log(bodyText?.substring(0, 5000));
      throw new Error('Step 2 (Sheet Selection) not found');
    }

    // Select sheet
    const sheetSelect = page.locator('select').first();
    const sheets = await sheetSelect.locator('option').allTextContents();
    console.log(`Sheets: ${sheets.filter(s => s.trim()).join(', ')}`);

    const rabSheet = sheets.find(s => s.includes('RAB') && !s.includes('SCHD') && !s.includes('REKAP')) || sheets[1];
    if (rabSheet) {
      await sheetSelect.selectOption(rabSheet);
      console.log(`✅ Selected: ${rabSheet}`);
    }

    // Select project
    const projectSelect = page.locator('select').nth(1);
    const projects = await projectSelect.locator('option').allTextContents();
    console.log(`Projects: ${projects.filter(p => p.trim()).join(', ')}`);

    if (projects.length > 1) {
      await projectSelect.selectOption({ index: 1 });
      console.log(`✅ Project: ${projects[1]}`);
    }

    // Step 2: Preview
    console.log('\n--- Step 2: Preview ---');
    const previewBtn = page.locator('button:has-text("Lihat Preview")');
    await previewBtn.click();
    await page.waitForTimeout(30000);

    await expect(page.locator('h3:has-text("Validasi Data")')).toBeVisible({ timeout: 30000 });
    console.log('✅ Preview loaded');

    const rowCount = await page.locator('tbody tr').count();
    console.log(`Preview rows: ${rowCount}`);

    // Step 3: Validate
    console.log('\n--- Step 3: Validate ---');
    const validateBtn = page.locator('button:has-text("Validasi Total Price")');
    await validateBtn.click();
    await page.waitForTimeout(45000);

    const valResult = await page.locator('text=Validasi berhasil, text=tidak ada error, text=error validasi').first().textContent({ timeout: 60000 });
    console.log(`Validation: ${valResult}`);

    // Step 4: Confirm
    console.log('\n--- Step 4: Confirm ---');
    await expect(page.locator('h3:has-text("Konfirmasi Import")')).toBeVisible({ timeout: 20000 });

    const importBtn = page.locator('button:has-text("Import Sekarang")');
    await importBtn.click();

    console.log('Waiting 120s for import...');
    await page.waitForTimeout(120000);

    await expect(page.locator('h3:has-text("Import Selesai")')).toBeVisible({ timeout: 60000 });
    console.log('✅ IMPORT COMPLETE!');

    const msg = await page.locator('text=Import selesai').textContent();
    console.log(`Message: ${msg}`);
  });
});