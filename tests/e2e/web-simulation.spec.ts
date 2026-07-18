import { test, expect } from '@playwright/test';

const CREDENTIALS = {
  admin: { email: 'admin@erp.com', password: 'password' },
  lapangan: { email: 'lapangan@erp.com', password: 'password' },
  engineer: { email: 'engineer@erp.com', password: 'password' },
  purchasing: { email: 'purchasing_legal@erp.com', password: 'password' },
  verifikator: { email: 'verifikator_keu@erp.com', password: 'password' },
  mgr_komersial: { email: 'mgr_komersial@erp.com', password: 'password' },
  keu_kantor: { email: 'keu_kantor@erp.com', password: 'password' },
  pajak: { email: 'pajak@erp.com', password: 'password' },
  accounting: { email: 'accounting@erp.com', password: 'password' },
};

async function loginAs(page, role) {
  const creds = CREDENTIALS[role];
  await page.fill('input[name="email"]', creds.email);
  await page.fill('input[name="password"]', creds.password);
  await page.click('button[type="submit"]');
  // Wait for login to complete - wait for dashboard content
  await page.waitForURL('**/dashboard', { timeout: 30000 });
}

test.describe('ERP Konstruksi - Web Simulation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/login');
  });

  test('Login sebagai Admin → Dashboard load', async ({ page }) => {
    await loginAs(page, 'admin');
    
    await expect(page.locator('h1:has-text("Dashboard"), h2:has-text("Dashboard")').first()).toBeVisible({ timeout: 10000 });
    console.log('✅ Dashboard loaded');
  });

  test('Sidebar menu count untuk ADMIN (14 menu termasuk Inventaris)', async ({ page }) => {
    await loginAs(page, 'admin');
    
    const sidebarLinks = page.locator('aside nav a');
    const count = await sidebarLinks.count();
    console.log(`Admin sidebar menus: ${count}`);
    expect(count).toBe(14);
  });

  test('Login sebagai LAPANGAN → Dashboard dengan 6 menu (termasuk Inventaris)', async ({ page }) => {
    await loginAs(page, 'lapangan');
    
    const sidebarLinks = page.locator('aside nav a');
    const count = await sidebarLinks.count();
    console.log(`Lapangan sidebar menus: ${count}`);
    expect(count).toBe(6);
  });

  test('Navigasi ke Purchase Order page', async ({ page }) => {
    await loginAs(page, 'admin');
    
    await page.locator('aside nav a:has-text("Purchase Orders"), aside nav a:has-text("PO"), aside nav a:has-text("Purchase Order")').first().click();
    await page.waitForURL('**/po', { timeout: 30000 });
    
    await expect(page.locator('h1:has-text("Purchase Orders"), h2:has-text("Purchase Orders"), h1:has-text("PO"), h2:has-text("PO")').first()).toBeVisible({ timeout: 10000 });
    console.log('✅ PO page loaded');
  });

  test('Navigasi ke RAB Control page', async ({ page }) => {
    await loginAs(page, 'admin');
    
    await page.locator('aside nav a:has-text("Kontrol RAB"), aside nav a:has-text("RAB")').first().click();
    await page.waitForURL('**/rab-control', { timeout: 30000 });
    
    await expect(page.locator('h1:has-text("Kontrol RAB"), h2:has-text("Kontrol RAB"), h1:has-text("RAB"), h2:has-text("RAB")').first()).toBeVisible({ timeout: 10000 });
    console.log('✅ RAB Control page loaded');
  });

  test('Screenshot dashboard untuk visual regression', async ({ page }) => {
    await loginAs(page, 'admin');
    
    await expect(page.locator(':text("Project")').first()).toBeVisible({ timeout: 15000 });
    
    await page.screenshot({ 
      path: 'test-results/dashboard-screenshot.png',
      fullPage: true 
    });
    console.log('📸 Screenshot saved');
  });

  test('Project switcher di dashboard', async ({ page }) => {
    await loginAs(page, 'admin');
    
    const projectButtons = page.locator('button:has-text("Project"), button:has-text("project")');
    const count = await projectButtons.count();
    console.log(`Project buttons found: ${count}`);
    
    if (count > 1) {
      await projectButtons.nth(1).click();
      await page.waitForTimeout(500);
      console.log('✅ Project switched');
    }
  });
  test('Logout flow', async ({ page }) => {
      await loginAs(page, 'admin');
      await page.waitForTimeout(2000);
    
      await page.locator('button:has-text("Admin"), button:has-text("admin")').first().click({ timeout: 5000 });
      await page.waitForTimeout(500);
    
      const logOutBtn = page.locator('button:has-text("Log Out")').first();
      await expect(logOutBtn).toBeVisible({ timeout: 5000 });
      await logOutBtn.click({ force: true });
    
      // After logout, Laravel redirects to "/" which then redirects to login
      // Check for either "/" or "/login"
      await page.waitForURL(/(\/login$|\/$)/, { timeout: 20000 });
      console.log('✅ Logged out');
    });
  });