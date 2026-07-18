import { test, expect } from '@playwright/test';

test('SPK form - PO filter by project', async ({ page }) => {
  // Login
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@erp.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(1000);

  // Go to SPK page
  await page.goto('/spk');
  await page.waitForTimeout(1000);

  // Click "+ Buat SPK"
  await page.locator('button:has-text("Buat SPK")').first().click();
  await page.waitForTimeout(1000);

  // Select GIK UGM project using label
  await page.locator('label:has-text("Proyek") + select, label:has-text("Proyek") ~ select').first().selectOption({ label: 'GIK UGM' });
  await page.waitForTimeout(2000);

  // Check all selects
  const selects = page.locator('select');
  const count = await selects.count();
  console.log(`Found ${count} selects`);

  for (let i = 0; i < count; i++) {
    const options = await selects.nth(i).locator('option').allTextContents();
    console.log(`\nSelect ${i}: ${options.length} options`);
    for (const opt of options.slice(0, 5)) {
      console.log(`  ${opt}`);
    }
  }

  // Check PO dropdown specifically (should be the one with "Tanpa link PO")
  const poSelect = page.locator('select:has(option:has-text("Tanpa link PO"))');
  const hasPoSelect = await poSelect.count();
  console.log(`\nPO select found: ${hasPoSelect > 0}`);

  if (hasPoSelect > 0) {
    const poOptions = await poSelect.locator('option').allTextContents();
    console.log(`PO options: ${poOptions.length}`);
    for (const opt of poOptions.slice(0, 10)) {
      console.log(`  ${opt}`);
    }
  }
});
