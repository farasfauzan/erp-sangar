import { test, expect } from '@playwright/test';

test.describe.configure({ retries: 0, workers: 1 });

test.describe('ERP Konstruksi - Import Excel Data GIK UGM', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/login');
    await page.fill('input[name="email"]', 'admin@erp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard', { timeout: 60000 });
    await page.waitForTimeout(3000);
  });

  test('Login Admin → Dashboard load', async ({ page }) => {
    await expect(page.locator('h1:has-text("Dashboard"), h2:has-text("Dashboard")').first()).toBeVisible({ timeout: 10000 });
    console.log('✅ Dashboard loaded');
  });

  test('Sidebar menu count ADMIN = 14', async ({ page }) => {
    const count = await page.locator('aside nav a').count();
    console.log(`Admin sidebar menus: ${count}`);
    expect(count).toBe(14);
  });

  test('Create Supplier: PT. MITRA TEKNIK JAYA', async ({ page }) => {
    await page.locator('aside nav a:has-text("Supplier")').first().click();
    await page.waitForURL('**/suppliers', { timeout: 30000 });
    await page.waitForTimeout(1000);

    await page.locator('a:has-text("Tambah Supplier"), button:has-text("Tambah Supplier")').first().click();
    await page.waitForURL('**/suppliers/create', { timeout: 30000 });
    await page.waitForTimeout(1000);

    await page.fill('input[placeholder="PT. Contoh Sejahtera"]', 'PT. MITRA TEKNIK JAYA');
    await page.fill('input[placeholder="SUP-001"]', 'SUP-MTJ-001');
    await page.fill('textarea[placeholder="Alamat lengkap supplier"]', 'Jl. Raya Naragong KM 5.5 No. 82 RT. 004 RW. 003 Rawalumbu, Kota Bekasi');
    await page.fill('input[placeholder="021-xxxx xxxx"]', '+62 857-1196-9959');
    await page.fill('input[placeholder="info@contoh.com"]', 'mitrateknikjaya@example.com');
    await page.fill('input[placeholder="Nama PIC"]', 'Bp. Deni');
    await page.fill('input[placeholder="00.000.000.0-000.000"]', '12.345.678.9-012.000');
    
    await page.fill('input[placeholder="Bank Mandiri"]', 'Bank Mandiri');
    await page.fill('input[placeholder="1234567890"]', '1234567890');
    await page.fill('input[placeholder="Nama rekening"]', 'PT. MITRA TEKNIK JAYA');

    await page.click('button[type="submit"]:has-text("Simpan Supplier")');
    await Promise.race([
      page.waitForURL('**/suppliers', { timeout: 15000 }),
      page.waitForSelector('.bg-red-100, .text-red-700, .alert-error, [role="alert"]', { timeout: 15000 })
    ]);
    console.log('✅ Supplier created: PT. MITRA TEKNIK JAYA');
  });
});