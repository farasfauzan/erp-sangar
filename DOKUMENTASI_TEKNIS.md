# ERP Konstruksi — Dokumentasi Teknis Lengkap

> **Project:** ERP Konstruksi (rep-sangar)  
> **Company:** PT. SINAR CERAH SEMPURNA  
> **Stack:** Laravel 11 + React/Inertia.js + Tailwind CSS + SQLite (dev) / MySQL (prod)  
> **Last Updated:** 2025-07-13

---

## 📋 Daftar Isi

1. [Arsitektur & Struktur Project](#arsitektur--struktur-project)
2. [Database & Models](#database--models)
3. [Authentication & Authorization](#authentication--authorization)
4. [API Layer](#api-layer)
5. [Frontend (React + Inertia.js)](#frontend-react--inertiajs)
6. [Module Bisnis Utama](#module-bisnis-utama)
7. [Workflow & State Management](#workflow--state-management)
8. [Print & PDF Generation](#print--pdf-generation)
9. [Deployment & Infrastructure](#deployment--infrastructure)
10. [Testing dengan Playwright](#testing-dengan-playwright)

---

## 🏗 Arsitektur & Struktur Project

```
rep-sangar/
├── app/
│   ├── Console/Commands/          # Artisan commands (AI import, test)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/               # 23 API Controllers (resource-based)
│   │   │   ├── Auth/              # Laravel Breeze auth controllers
│   │   │   ├── PrintController.php
│   │   │   ├── ProfileController.php
│   │   │   └── RabStorageController.php
│   │   ├── Middleware/
│   │   │   ├── CheckRole.php      # RBAC middleware
│   │   │   └── HandleInertiaRequests.php
│   │   └── Requests/
│   ├── Jobs/                      # Queue jobs (AI RAB import, validation)
│   ├── Models/                    # 28 Eloquent Models
│   ├── Providers/
│   ├── Services/
│   │   └── MimoAiService.php      # AI classification service
│   ├── Support/
│   │   └── WorkflowState.php      # State machine for approvals
│   └── Traits/
│       └── HandlesRabParsing.php  # RAB CSV/Excel parsing
├── bootstrap/
│   └── app.php                    # Laravel 11 bootstrap (middleware, routing config)
├── config/
│   ├── auth.php, database.php, ... (standard Laravel)
│   └── erp.php                    # Custom ERP config
├── database/
│   ├── migrations/                # 40+ migrations
│   ├── seeders/
│   └── database.sqlite            # Dev database
├── deploy/                        # Production deployment configs
│   ├── nginx/, supervisor/, systemd/
│   ├── deploy-simulator.sh
│   ├── backup-script.sh
│   └── deploy-checklist.md
├── resources/
│   ├── css/app.css                # Tailwind imports
│   ├── js/
│   │   ├── app.jsx                # Inertia app entry
│   │   ├── bootstrap.js           # Axios + CSRF config
│   │   ├── Components/            # 18 reusable UI components
│   │   ├── Layouts/
│   │   │   ├── AuthenticatedLayout.jsx
│   │   │   └── GuestLayout.jsx
│   │   ├── Pages/                 # 50+ page components
│   │   │   ├── Auth/              # Login, Register, Password reset
│   │   │   ├── Dashboard/         # 6 dashboard widgets
│   │   │   ├── Print/             # Print-optimized pages
│   │   │   └── Profile/
│   │   ├── hooks/
│   │   │   ├── useApi.js          # Centralized API client
│   │   │   └── useProjects.js     # Shared projects cache
│   │   └── Layouts/
│   └── views/
│       └── app.blade.php          # Inertia root template
├── routes/
│   ├── web.php                    # Inertia routes (auth, dashboard, module pages)
│   ├── api.php                    # 200+ API endpoints (resource + custom)
│   └── console.php
├── tests/                         # (kosong - akan diisi Playwright)
├── public/
│   └── build/                     # Vite production assets
├── storage/
│   ├── app/private/               # Uploaded files (PO attachments, RAB imports)
│   └── logs/
├── vite.config.js                 # Vite + Laravel plugin + React
├── composer.json
├── package.json
└── .env                           # APP_URL=http://localhost, DB=sqlite
```

---

## 🗄 Database & Models (28 Models)

| Model | Tabel | Deskripsi |
|-------|-------|-----------|
| **User** | `users` | 9 roles, soft delete |
| **Role** | `roles` | ADMIN, LAPANGAN, ENGINEER, PURCHASING_LEGAL, VERIFIKATOR_KEU, MGR_KOMERSIAL, KEU_KANTOR, PAJAK, ACCOUNTING |
| **Project** | `projects` | Master proyek konstruksi |
| **PurchaseOrder** | `purchase_orders` | PO header + items, workflow submit→approve |
| **PoItem** | `po_items` | Line items PO |
| **PoAttachment** | `po_attachments` | File lampiran PO |
| **Spk** | `spks` | Surat Perintah Kerja (kontrak) |
| **SpkProgress** | `spk_progress` | Progress fisik/keuangan SPK |
| **RabBudget** | `rab_budgets` | RAB detail per project (hierarki kode) |
| **RabImportJob** | `rab_import_jobs` | Async import RAB dari Excel/CSV |
| **GoodsReceipt** | `goods_receipts` | Penerimaan barang (GR) |
| **GoodsReceiptItem** | `goods_receipt_items` | Line items GR |
| **Invoice** | `invoices` | Tagihan vendor |
| **InvoiceAttachment** | `invoice_attachments` | Lampiran invoice |
| **FundRequest** | `fund_requests` | Permintaan dana (LPJ) |
| **FundReceipt** | `fund_receipts` | Penerimaan dana |
| **PaymentExecution** | (via Invoice) | Eksekusi pembayaran |
| **Opname** | `opnames` | Progres/nilai pekerjaan SPK |
| **InventoryStock** | `inventory_stocks` | Master barang/stok |
| **StockMovement** | `stock_movements` | Mutasi stok in/out/adjust |
| **PurchaseRequisition** | `purchase_requisitions` | PR sebelum PO |
| **Supplier** | `suppliers` | Master vendor |
| **ChartOfAccount** | `chart_of_accounts` | COA akuntansi (hierarki) |
| **GeneralLedger** | `general_ledgers` | Jurnal umum |
| **Transaction** | `transactions` | Transaksi kas/bank |
| **Tax** | `taxes` | Pajak PPN/PPh |
| **Efaktur** | `efakturs` | E-Faktur CSV import |
| **Bast** | `basts` | Berita Acara Serah Terima |
| **ApprovalLog** | `approval_logs` | Audit trail approval |
| **AuditLog** | `audit_logs` | Generic audit trail |

**Key Relationships:**
```
Project 1──M PurchaseOrder, SPK, RabBudget, Invoice, FundRequest, InventoryStock
PurchaseOrder 1──M PoItem, PoAttachment, GoodsReceipt
SPK 1──M SpkProgress, Bast
Invoice 1──M InvoiceAttachment, PaymentExecution
FundRequest 1──M FundReceipt
ChartOfAccount (self-referencing hierarchy)
User belongsTo Role
```

---

## 🔐 Authentication & Authorization

### Auth Stack
- **Laravel Breeze** + **Inertia React** (auth scaffolding)
- **Sanctum** session-based (SPA)
- **Email verification** required
- **Password reset** via email

### RBAC (9 Roles)
```php
// app/Http/Middleware/CheckRole.php
$roles = [
    'ADMIN'              => full access,
    'LAPANGAN'           => PO draft, GR, Opname, LPJ,
    'ENGINEER'           => RAB control, verifikasi kebutuhan/tagihan,
    'PURCHASING_LEGAL'   => PO, Supplier, SPK, Invoicing,
    'VERIFIKATOR_KEU'    => Verifikasi dokumen, LPJ,
    'MGR_KOMERSIAL'      => Executive dashboard, approval PO/SPK/cashflow,
    'KEU_KANTOR'         => Arus kas, antrean bayar, eksekusi pembayaran,
    'PAJAK'              => Faktur pajak, E-Faktur CSV, restitusi,
    'ACCOUNTING'         => Jurnal posting, laporan keuangan, audit trail
];
```

**Middleware usage:**
```php
// Route level
Route::middleware('role:ADMIN,ENGINEER')->group(...);

// Controller level
$this->middleware('role:ADMIN,MGR_KOMERSIAL')->only(['approve', 'reject']);
```

### Permission Matrix (Sidebar per Role)
| Menu | ADMIN | LAPANGAN | ENGINEER | PURCHASING | VERIFIKATOR | MGR_KOM | KEU_KANTOR | PAJAK | ACCOUNTING |
|------|-------|----------|----------|------------|-------------|---------|------------|-------|------------|
| Dashboard | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| RAB Control | ✅ | | ✅ | | | ✅ | | | |
| RAB Storage | ✅ | | ✅ | | | ✅ | | | |
| PO | ✅ | ✅ | | ✅ | | ✅ | | | |
| Supplier | ✅ | | | ✅ | | | | | |
| SPK | ✅ | | | ✅ | | ✅ | | | |
| Goods Receipt | ✅ | ✅ | | ✅ | | ✅ | | | |
| Opname | ✅ | ✅ | | | | | | | |
| Invoicing | ✅ | | | ✅ | | | | | |
| Approval | ✅ | | ✅ | | ✅ | ✅ | | | |
| Fund Requests | ✅ | ✅ | | | ✅ | | | | |
| Payment | ✅ | | | | | ✅ | ✅ | | |
| Faktur Pajak | ✅ | | | | | | | ✅ | |
| E-Faktur CSV | ✅ | | | | | | | ✅ | |
| Posting Jurnal | ✅ | | | | | | | | ✅ |
| Laporan Keuangan | ✅ | | | | | ✅ | | | ✅ |
| Audit Trail | ✅ | | | | | | | | ✅ |
| Kelola User | ✅ | | | | | | | | |
| Inventory | ✅ | | | | | | | | |

---

## 🌐 API Layer (routes/api.php)

**200+ endpoints** — grouped by resource + custom actions.

### Base Middleware
```php
Route::middleware(['auth:web', 'verified'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->group(function () { ... });
```

### Resource Controllers (API)
| Controller | Resource | Custom Actions |
|------------|----------|----------------|
| `ProjectController` | `/projects` | - |
| `PurchaseOrderController` | `/pos`, `/purchase-orders` | `submit`, `approve`, `reject`, `route`, `attachments` |
| `SpkController` | `/spks` | `submit`, `approve`, `reject` |
| `InvoiceController` | `/invoices` | `verifyEngineer`, `verifyFinance`, `cashflowApprove`, `managerApprove`, `executePayment` |
| `RabBudgetController` | `/rab` | `summary`, `rollup`, `export`, `import`, `autoImport`, `aiCategorize` |
| `GoodsReceiptController` | `/goods-receipts` | `getByPo` |
| `OpnameController` | `/opnames` | `approve`, `reject` |
| `FundRequestController` | `/fund-requests` | `approve`, `reject`, `pay`, `submitLpj`, `verifyLpj` |
| `FundReceiptController` | `/fund-receipts` | `confirm`, `dispute` |
| `InventoryController` | `/inventory` | `receive`, `adjust`, `movements` |
| `DashboardReportController` | `/dashboard/*` | `executive`, `financial`, `projects` |
| `TaxController` | `/taxes` | `calculate`, `submitRestitusi`, `approveRestitusi`, `payRestitusi` |
| `EfakturController` | `/efaktur` | `uploadCsv`, `validateRecord`, `updateStatus`, `destroy` |
| `FinancialReportController` | `/reports` | `neraca`, `labaRugi`, `arusKas` |
| `GeneralLedgerController` | `/general-ledger` | `trialBalance`, `export` |
| `ChartOfAccountController` | `/chart-of-accounts` | CRUD hierarchy |
| `SupplierController` | `/suppliers` | CRUD |
| `UserController` | `/users` | `assignRole` |
| `RoleController` | `/roles` | index |
| `AuditLogController` | `/audit-logs` | index |
| `BastController` | `/basts` | CRUD |
| `PurchaseRequisitionController` | `/purchase-requisitions` | CRUD |

### Response Format (Standard)
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}
```
Error: `success: false`, `message`, `errors` (validation)

---

## 🎨 Frontend (React + Inertia.js)

### Tech Stack
- **React 18** + **Inertia.js** (SPA without SPA routing)
- **Tailwind CSS** (utility-first)
- **Vite** (HMR, build)
- **Ziggy** (Laravel route helpers in JS)
- **Axios** (withCredentials: true, XSRF token auto)

### Entry Point
```jsx
// resources/js/app.jsx
createInertiaApp({
  resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
  setup({ el, App, props }) {
    createRoot(el).render(<ToastProvider><App {...props} /></ToastProvider>);
  },
  progress: { color: '#4B5563' },
});
```

### Layouts
| Layout | File | Digunakan oleh |
|--------|------|----------------|
| `AuthenticatedLayout` | `Layouts/AuthenticatedLayout.jsx` | Semua halaman butuh auth (sidebar, navbar, breadcrumb) |
| `GuestLayout` | `Layouts/GuestLayout.jsx` | Login, Register, Password reset |

**Sidebar dinamis** → `AuthenticatedLayout.jsx` → `getRoleMenus()` filter by `auth.user.role.role_name`.

### Hooks (Shared State)
| Hook | File | Fungsi |
|------|------|--------|
| `useApi` | `hooks/useApi.js` | Wrapper axios (GET/POST/PUT/DELETE) + toast error handling |
| `useProjects` | `hooks/useProjects.js` | Cache projects global (fetch once, share across pages) |

### Components (18 reusable)
| Component | File | Deskripsi |
|-----------|------|-----------|
| `Button` / `PrimaryButton` / `SecondaryButton` / `DangerButton` | `Components/ui/Button.jsx` | Variasi button |
| `Card` | `Components/ui/Card.jsx` | Container dengan header/footer |
| `Modal` / `ConfirmModal` / `InputPromptModal` | `Components/ui/Modal.jsx` | Dialog overlays |
| `DataTable` | `Components/ui/DataTable.jsx` | Sortable, paginated, selectable table |
| `FormField` / `TextInput` / `InputLabel` / `Select` / `Checkbox` | `Components/ui/FormField.jsx` | Form primitives |
| `StatusBadge` | `Components/ui/StatusBadge.jsx` | Colored badge by status |
| `PageHeader` | `Components/ui/PageHeader.jsx` | Title + breadcrumb + actions |
| `LoadingSpinner` | `Components/ui/LoadingSpinner.jsx` | Loading indicator |
| `Toast` / `useToast` | `Components/ui/Toast.jsx` | Notification system |
| `Dropdown` | `Components/Dropdown.jsx` | User menu dropdown |
| `ApplicationLogo` | `Components/ApplicationLogo.jsx` | SVG logo PT. SCS |
| `ErrorBoundary` | `Components/ErrorBoundary.jsx` | Catch render errors |

### Pages (50+)
**Grup utama:**
- **Dashboard** → `Dashboard.jsx` + 6 widget (`ExecutiveSummary`, `FinancialChart`, `ProjectsList`, `RabImport`, `QuickActions`, `RoleOverview`)
- **Pengadaan** → `PurchaseOrder`, `PurchaseOrderDetail`, `PurchaseOrderEdit`, `CreatePO`, `SupplierList`, `SupplierForm`
- **RAB** → `RabControl` (tree + import), `RabStorage` (list + download)
- **Penerimaan** → `GoodsReceipt`, `OpnamePage`
- **Tagihan** → `InvoiceAdmin`, `FakturPajak`, `EFakturCsv`
- **Approval** → `ApprovalDashboard` (multi-tab)
- **Keuangan** → `FundRequestPage`, `PaymentExecution`, `PostingJurnal`, `LaporanKeuangan`
- **Pajak** → `FakturPajak`, `EFakturCsv`
- **Akuntansi** → `PostingJurnal`, `LaporanKeuangan`, `AuditTrail`
- **Inventaris** → `InventoryDashboard`, `StockMovements`
- **SPK** → `Spk`
- **Print** → `PurchaseOrderPrint`, `SpkPrint`, `InvoicePrint` (layout khusus A4)
- **User Mgmt** → `UserManagement`
- **Profile** → `Edit` + partials

---

## 🔄 Module Bisnis Utama

### 1. Purchase Order (PO) — `PurchaseOrderController`
**Flow:** Draft → Submit → Route (Engineer) → Approve (Manager) → PO Aktif
```php
// Status: draft → submitted → routed → approved → rejected
// Actions: store, submit, route, approve, reject
// Attachments: upload/delete/get
// Print: PDF + HTML print page
```

### 2. RAB (Rencana Anggaran Biaya) — `RabBudgetController`
- **Hierarki:** Kode item (1.1.1.1) → parent/child
- **Import:** Excel/CSV → `RabImportJob` (queue) → validasi → `autoImport`
- **AI Categorize:** `MimoAiService` klasifikasi kode RAB
- **Rollup:** Summary per kategori, export Excel
- **Versioning:** `version` field, status `ARCHIVED` untuk history

### 3. Goods Receipt (GR) — `GoodsReceiptController`
- Link ke PO → update qty received
- Status: `partial` / `completed`
- Auto-create `StockMovement` (IN)

### 4. SPK (Surat Perintah Kerja) — `SpkController`
- Kontrak dengan vendor
- Progress: `SpkProgress` (fisik % + keuangan %)
- BAST: `BastController` (closing SPK)

### 5. Invoicing & Approval — `InvoiceController`
**Multi-level approval:**
```
Engineer Verify → Finance Verify → Cashflow Approve → Manager Approve → Payment Execute
```
| Role | Action |
|------|--------|
| ENGINEER | `verifyEngineer` (cek kelengkapan teknis) |
| VERIFIKATOR_KEU | `verifyFinance` (cek dokumen pajak) |
| MGR_KOMERSIAL | `cashflowApprove` / `managerApprove` |
| KEU_KANTOR | `executePayment` (transfer) |

### 6. Fund Request (LPJ) — `FundRequestController`
- Pengajuan dana → Approve → Cairkan → LPJ → Verifikasi LPJ
- `FundReceipt` untuk pencairan dana

### 7. Akuntansi — `GeneralLedgerController` + `ChartOfAccountController`
- **COA:** Hierarchical (parent_id), tipe: `asset/liability/equity/revenue/expense`
- **Jurnal:** Double-entry, balance check
- **Reports:** Neraca, Laba Rugi, Arus Kas, Trial Balance

### 7. Pajak — `TaxController` + `EfakturController`
- Faktur Pajak (Output/Input PPN)
- E-Faktur CSV import/export (format DJP)
- Restitusi PPN workflow

### 8. Inventory — `InventoryController`
- `InventoryStock` hanya untuk item RAB kategori Material atau stok manual tanpa relasi RAB.
- Impor RAB tidak membuat stok. `GoodsReceiptController` membuat/menambah stok ketika Material dari PO yang disetujui diterima.
- Kategori Subkon, Pekerja, dan Alat ditolak oleh Penerimaan Barang dan diarahkan ke SPK/opname.
- Mutasi: `receive` (penerimaan barang), `adjust` (koreksi stok), `movements` (riwayat).
- `OpnameController` adalah progres pekerjaan SPK, bukan penerimaan stok Material.

---

## 🔀 Workflow & State Management

### Approval State Machine (`app/Support/WorkflowState.php`)
```php
class WorkflowState {
    const DRAFT = 'draft';
    const SUBMITTED = 'submitted';
    const ROUTED = 'routed';
    const APPROVED = 'approved';
    const REJECTED = 'rejected';
    const PAID = 'paid';
    const CANCELLED = 'cancelled';
    
    // Transitions valid per entity
    static $transitions = [
        'purchase_order' => [
            'draft' => ['submitted'],
            'submitted' => ['routed', 'rejected'],
            'routed' => ['approved', 'rejected'],
            'approved' => ['paid', 'cancelled'],
        ],
        'invoice' => [...],
        'fund_request' => [...],
    ];
}
```

### Approval Log
Setiap action (submit, approve, reject, route) → `ApprovalLog::create([...])`

---

## 🖨 Print & PDF Generation

### PrintController (`PrintController.php`)
```php
public function purchaseOrderPrint($id) { ... }
public function spkPrint($id) { ... }
public function invoicePrint($id) { ... }
public function bastPrint($id) { ... }
public function purchaseOrderPdf($id) { ... }
public function spkPdf($id) { ... }
public function invoicePdf($id) { ... }
public function bastPdf($id) { ... }
```

### Print Pages (React) — `Pages/Print/`
| Page | Route | Fitur |
|------|-------|-------|
| `PurchaseOrderPrint` | `/purchase-orders/{id}/print` | A4, landscape, detail item |
| `SpkPrint` | `/spks/{id}/print` | Kontrak + progress |
| `InvoicePrint` | `/invoices/{id}/print` | Tagihan + PPN |
| `BastPrint` | `/basts/{id}/print` | BAST closing |

**PDF:** `dompdf` (barryvdh/laravel-dompdf) → stream/download

### Company Info (Header Print)
```
PT. SINAR CERAH SEMPURNA
NPWP: 002.652.984.2-331.000
Alamat: Karangrejo Barat No. 9 RT 002 RW 002, Tinjomoyo, Banyumanik, Semarang
Signatory: NARWAN PRATANTA, ST (Manager Komersial)
PO Format: {nomor}S/SCS-SMG/{PROYEK}/PO/{ROMAN}/{TAHUN}
```

---

## 🚀 Deployment & Infrastructure

### Production Stack
- **OS:** Ubuntu 22.04/24.04
- **Web Server:** Nginx + PHP-FPM 8.3
- **Process Manager:** Supervisor (queue workers, horizon optional)
- **Database:** MySQL 8.0 / MariaDB 10.6
- **Cache:** Redis (session, cache, queue)
- **SSL:** Let's Encrypt (Certbot)
- **Queue:** `database` driver (dev) → `redis` (prod)

### Deploy Scripts (`deploy/`)
| File | Fungsi |
|------|--------|
| `deploy-simulator.sh` | Dry-run deployment (validasi config, migrate, build, permissions) |
| `backup-script.sh` | Backup DB + storage + .env (retensi 7 hari) |
| `deploy-checklist.md` | Checklist manual pre/post deploy |
| `nginx/nginx.conf` | Nginx config (rate limit, SSL, proxy) |
| `supervisor/supervisor.conf` | Queue workers, scheduler, horizon |
| `systemd/` | Systemd services |

### Quick Deploy (Production)
```bash
# 1. Clone & setup
git clone https://github.com/farasfauzan/rep-sangar.git /var/www/erp-konstruksi
cd /var/www/erp-konstruksi

# 2. Env & deps
cp .env.example .env.production
# edit .env.production (DB, APP_URL, MAIL, etc.)
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# 3. Laravel optimize
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

# 4. Permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 5. Services
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
sudo supervisorctl reread && sudo supervisorctl update
```

---

## 🧪 Testing dengan Playwright

### Install (sudah dilakukan)
```bash
cd /c/Users/faras/rep-sangar
npm install -D @playwright/test
npx playwright install
```

### Struktur Test (akan dibuat)
```
tests/
├── e2e/
│   ├── auth/
│   │   ├── login.spec.ts
│   │   ├── logout.spec.ts
│   │   └── password-reset.spec.ts
│   ├── dashboard/
│   │   ├── dashboard-load.spec.ts
│   │   ├── role-menu-check.spec.ts
│   │   └── project-switcher.spec.ts
│   ├── purchase-order/
│   │   ├── po-create.spec.ts
│   │   ├── po-submit-approve.spec.ts
│   │   ├── po-attachment.spec.ts
│   │   └── po-print.spec.ts
│   ├── rab/
│   │   ├── rab-import.spec.ts
│   │   ├── rab-tree.spec.ts
│   │   └── rab-export.spec.ts
│   ├── invoice/
│   │   ├── invoice-flow.spec.ts
│   │   ├── approval-chain.spec.ts
│   │   └── payment.spec.ts
│   ├── fund-request/
│   │   └── lpj-flow.spec.ts
│   ├── inventory/
│   │   ├── opname.spec.ts
│   │   └── stock-movement.spec.ts
│   ├── accounting/
│   │   ├── jurnal-posting.spec.ts
│   │   └── reports.spec.ts
│   ├── tax/
│   │   ├── efaktur-import.spec.ts
│   │   └── faktur-pajak.spec.ts
│   └── visual/
│       └── dashboard-screenshot.spec.ts
├── fixtures/
│   ├── users.json          # Test users per role
│   └── test-data.sql       # Seed data untuk test
├── utils/
│   ├── auth.ts             # Login helper per role
│   ├── selectors.ts        # Centralized selectors
│   └── api.ts              # API helpers
├── playwright.config.ts
└── package.json scripts
```

### Playwright Config (`playwright.config.ts`)
```typescript
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    { name: 'Mobile Chrome', use: { ...devices['Pixel 5'] } },
    { name: 'Mobile Safari', use: { ...devices['iPhone 12'] } },
  ],
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8000',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 120000,
  },
});
```

### Contoh Test: Login + Dashboard Role Check
```typescript
// tests/e2e/auth/login.spec.ts
import { test, expect } from '@playwright/test';
import { loginAs } from '../utils/auth';

test.describe('Authentication', () => {
  test('admin login → dashboard dengan 13 menu sidebar', async ({ page }) => {
    await loginAs(page, 'admin');
    await expect(page).toHaveURL('/dashboard');
    
    // Count sidebar menus
    const menus = page.locator('aside nav a');
    await expect(menus).toHaveCount(13);
  });

  test('lapangan login → dashboard dengan 5 menu sidebar', async ({ page }) => {
    await loginAs(page, 'lapangan');
    const menus = page.locator('aside nav a');
    await expect(menus).toHaveCount(5);
  });
});
```

```typescript
// tests/utils/auth.ts
export async function loginAs(page, role) {
  const credentials = {
    admin: { email: 'admin@erp.com', password: 'password' },
    lapangan: { email: 'lapangan@erp.com', password: 'password' },
    engineer: { email: 'engineer@erp.com', password: 'password' },
    purchasing: { email: 'purchasing@erp.com', password: 'password' },
    verifikator: { email: 'verifikator@erp.com', password: 'password' },
    mgr_komersial: { email: 'mgr@erp.com', password: 'password' },
    keu_kantor: { email: 'keu@erp.com', password: 'password' },
    pajak: { email: 'pajak@erp.com', password: 'password' },
    accounting: { email: 'accounting@erp.com', password: 'password' },
  };
  
  await page.goto('/login');
  await page.fill('[name=email]', credentials[role].email);
  await page.fill('[name=password]', credentials[role].password);
  await page.click('button[type=submit]');
  await page.waitForURL('/dashboard');
}
```

### Run Commands
```bash
# Interactive UI mode
npx playwright test --ui

# Headed (lihat browser)
npx playwright test --headed

# Specific test
npx playwright test tests/e2e/auth/login.spec.ts

# Debug mode
npx playwright test --debug

# Generate test (codegen)
npx playwright codegen http://127.0.0.1:8000

# CI mode
npx playwright test --reporter=github
```

### CI/CD (GitHub Actions)
```yaml
# .github/workflows/playwright.yml
name: Playwright E2E
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - name: Install deps
        run: |
          composer install --no-dev --optimize-autoloader
          npm ci
          npm run build
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', extensions: mbstring, pdo, sqlite }
      - name: Start Laravel
        run: |
          php artisan migrate --force --seed
          php artisan serve --host=0.0.0.0 --port=8000 &
      - name: Install Playwright
        run: npx playwright install --with-deps
      - name: Run tests
        run: npx playwright test
      - uses: actions/upload-artifact@v4
        if: failure()
        with:
          name: playwright-report
          path: playwright-report/
          retention-days: 7
```

---

## 📚 Quick Reference

### Environment Variables (Key)
```env
APP_NAME="ERP Konstruksi"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=log
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
```

### Common Artisan Commands
```bash
# Dev
php artisan serve --host=127.0.0.1 --port=8000
npm run dev                    # Vite HMR (port 5173+)

# Database
php artisan migrate:fresh --seed
php artisan db:seed --class=RoleSeeder

# Cache
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache

# Queue
php artisan queue:work --tries=3

# Print test
php artisan route:list --name=print

# Deploy sim
bash deploy/deploy-simulator.sh
```

### File Permissions (Windows/Git Bash)
```bash
# Storage & cache writable
chmod -R 775 storage bootstrap/cache
# Or via Windows: Properties → Security → Users → Full Control
```

---

## 🎯 Next Steps (Roadmap)

### Immediate (Test Suite)
1. ✅ Playwright installed
2. 🔲 Create `tests/e2e/` structure
3. 🔲 Implement auth + dashboard role tests
4. 🔲 CRUD tests per module
5. 🔲 CI/CD GitHub Actions

### Short-term
- [ ] WebSocket/Reverb untuk notifikasi real-time
- [ ] Horizon untuk queue monitoring
- [ ] Telescope untuk debug production
- [ ] S3/MinIO untuk file storage

### Medium-term
- [ ] Mobile PWA (offline GR/Opname)
- [ ] AI RAB categorization improvement
- [ ] Multi-tenant (multi-company)
- [ ] API rate limiting + throttling

---

*Dokumen ini di-generate otomatis dari eksplorasi codebase. Update saat ada perubahan signifikan arsitektur.*
