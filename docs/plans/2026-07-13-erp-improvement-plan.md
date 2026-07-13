# ERP Konstruksi — Master Improvement Plan

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** Transform the construction ERP from a working prototype into a production-ready, secure, and complete system.

**Architecture:** Laravel 11 + React/Inertia.js SPA with Sanctum auth, role-based access, SQLite (dev) / MySQL (prod)

**Tech Stack:** PHP 8.3, Laravel 11, React 18, Inertia.js v2, Tailwind CSS, Recharts, Maatwebsite/Excel

---

## Phase 1: Backend Critical Fixes (Security & Broken Features)

### Task 1.1: Apply RBAC Middleware to All API Routes

**Objective:** Enforce role-based access control on all API endpoints using the existing `CheckRole` middleware.

**Files:**
- Modify: `app/Http/Middleware/CheckRole.php`
- Modify: `routes/api.php`
- Modify: `bootstrap/app.php` (register middleware alias)

**Steps:**

1. Register `CheckRole` as route middleware alias in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\CheckRole::class,
    ]);
})
```

2. Apply role middleware to each route group in `routes/api.php`:
```php
// Projects — all roles can view, only ADMIN/MGR_KOMERSIAL can create/edit
Route::middleware('role:ADMIN,MGR_KOMERSIAL')->group(function () {
    Route::apiResource('projects', ProjectController::class)->only(['store', 'update', 'destroy']);
});
Route::middleware('role:ADMIN,LAPANGAN,ENGINEER,PURCHASING_LEGAL,VERIFIKATOR_KEU,MGR_KOMERSIAL,KEU_KANTOR,PAJAK,ACCOUNTING')->group(function () {
    Route::apiResource('projects', ProjectController::class)->only(['index', 'show']);
});

// RAB — Engineer/Admin manage, all can view
Route::middleware('role:ADMIN,ENGINEER')->group(function () {
    Route::post('rab/import/confirm', [RabBudgetController::class, 'confirmImport']);
    Route::post('rab/import', [RabBudgetController::class, 'import']);
    Route::post('rab/{rabBudget}/approve', [RabBudgetController::class, 'approve']);
    Route::post('rab/{rabBudget}/reject', [RabBudgetController::class, 'reject']);
    Route::post('rab/bulk-approve', [RabBudgetController::class, 'bulkApprove']);
});

// PO — Purchasing creates, Admin/Mgr approves
Route::middleware('role:ADMIN,PURCHASING_LEGAL')->group(function () {
    Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['store', 'update']);
});
Route::middleware('role:ADMIN,MGR_KOMERSIAL')->group(function () {
    Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject']);
});

// SPK — similar pattern
// Invoice — Engineer verifies, Finance verifies, Manager approves
// Fund Request — various approval roles
// Goods Receipt — LAPANGAN receives
// Opname — LAPANGAN/Admin
// Inventory — all can view
// Audit Log — ADMIN only
// Dashboard — all authenticated
```

3. Verify: Create test user with role=LAPANGAN, attempt to approve PO → expect 403.

---

### Task 1.2: Remove `$user->id ?? 1` Fallbacks

**Objective:** Replace insecure user ID fallbacks with proper auth checks.

**Files:**
- Modify: `app/Http/Controllers/Api/PurchaseOrderController.php`
- Modify: `app/Http/Controllers/Api/SpkController.php`
- Modify: `app/Http/Controllers/Api/FundRequestController.php`

**Steps:**

1. In each controller, replace:
```php
$userId = $request->user()->id ?? 1;
```
With:
```php
$userId = $request->user()->id;
```

2. The `auth:web` middleware already ensures `$request->user()` is never null. If somehow null, it should 500 rather than silently use ID 1.

3. Verify: Call API without auth cookie → expect 401 (not fallback to user 1).

---

### Task 1.3: Fix AuditLogController Column Names

**Objective:** Fix broken audit log queries that use non-existent columns.

**Files:**
- Modify: `app/Http/Controllers/Api/AuditLogController.php`

**Steps:**

1. Replace in `index()` method:
```php
// FROM (broken):
->when($request->query('table_name'), fn($q, $v) => $q->where('table_name', $v))
->when($request->query('record_id'), fn($q, $v) => $q->where('record_id', $v))

// TO (correct):
->when($request->query('auditable_type'), fn($q, $v) => $q->where('auditable_type', $v))
->when($request->query('auditable_id'), fn($q, $v) => $q->where('auditable_id', $v))
```

2. Also add `user` relationship eager loading:
```php
->with('user:id,name')
```

3. Verify: Call `GET /api/audit-logs?auditable_type=App\Models\PurchaseOrder` → returns data.

---

### Task 1.4: Fix Empty `spk_progress` Migration

**Objective:** Add missing columns to the `spk_progress` table.

**Files:**
- Create: `database/migrations/2026_07_13_000001_fix_spk_progress_table.php`
- Modify: `app/Models/SpkProgress.php` (add relationships)

**Steps:**

1. Create migration:
```php
public function up(): void
{
    Schema::table('spk_progresses', function (Blueprint $table) {
        $table->foreignId('spk_id')->constrained('spks')->cascadeOnDelete();
        $table->foreignId('rab_budget_id')->nullable()->constrained('rab_budgets')->nullOnDelete();
        $table->text('work_description')->nullable();
        $table->decimal('progress_percentage', 5, 2)->default(0);
        $table->decimal('amount', 15, 2)->default(0);
        $table->date('progress_date')->nullable();
        $table->text('notes')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        
        $table->index(['spk_id', 'progress_date']);
    });
}
```

2. Add relationships to `SpkProgress` model:
```php
public function spk() { return $this->belongsTo(Spk::class); }
public function rabBudget() { return $this->belongsTo(RabBudget::class); }
public function creator() { return $this->belongsTo(User::class, 'created_by'); }
```

3. Run: `php artisan migrate`

---

### Task 1.5: Wrap FundRequest & Opname Approve/Reject in DB Transaction

**Objective:** Prevent race conditions on approval operations.

**Files:**
- Modify: `app/Http/Controllers/Api/FundRequestController.php`
- Modify: `app/Http/Controllers/Api/OpnameController.php`

**Steps:**

1. In `FundRequestController::approve()`:
```php
public function approve(Request $request, FundRequest $fundRequest)
{
    return DB::transaction(function () use ($request, $fundRequest) {
        $fundRequest = FundRequest::where('id', $fundRequest->id)->lockForUpdate()->firstOrFail();
        
        if ($fundRequest->status !== 'PENDING_APPROVAL') {
            return response()->json(['message' => 'Cannot approve: status is ' . $fundRequest->status], 422);
        }
        
        $fundRequest->update([
            'status' => 'APPROVED',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        
        // ... rest of logic
        return response()->json($fundRequest->fresh());
    });
}
```

2. Same pattern for `reject()`, and for `OpnameController::approve()` and `reject()`.

3. Verify: Concurrent approval requests → only one succeeds, other gets 422.

---

### Task 1.6: Fix Dashboard Cashflow for SQLite Compatibility

**Objective:** Make cashflow report work with SQLite (dev) and MySQL (prod).

**Files:**
- Modify: `app/Http/Controllers/Api/DashboardReportController.php`

**Steps:**

1. Replace `DATE_FORMAT` with Laravel's database-agnostic approach:
```php
// FROM:
$format = match($range) { ... };
$dateColumn = "DATE_FORMAT(payment_date, '{$format}')";

// TO:
use Illuminate\Support\Facades\DB;

$dateColumn = match(config('database.default')) {
    'sqlite' => match($range) {
        'daily' => DB::raw("strftime('%Y-%m-%d', payment_date)"),
        'monthly' => DB::raw("strftime('%Y-%m', payment_date)"),
        default => DB::raw("strftime('%Y', payment_date)"),
    },
    default => match($range) {
        'daily' => DB::raw("DATE_FORMAT(payment_date, '%Y-%m-%d')"),
        'monthly' => DB::raw("DATE_FORMAT(payment_date, '%Y-%m')"),
        default => DB::raw("DATE_FORMAT(payment_date, '%Y')"),
    },
};
```

2. Verify: `GET /api/dashboard/financial?project_id=1&range=monthly` returns valid JSON.

---

### Task 1.7: Add Pagination to All Index Endpoints

**Objective:** Prevent OOM on large datasets.

**Files:**
- Modify: All 12 API controllers' `index()` methods

**Steps:**

1. Replace `->get()` with `->paginate()`:
```php
// FROM:
return response()->json($query->with([...])->latest()->get());

// TO:
$perPage = min($request->query('per_page', 15), 100);
return response()->json($query->with([...])->latest()->paginate($perPage));
```

2. Response format changes from array to `{ data: [...], current_page, last_page, per_page, total }`.

3. Update frontend pages to handle paginated response (Phase 3).

---

### Task 1.8: Clean Up Patch Files

**Objective:** Remove dead code that could corrupt the codebase if re-run.

**Files:**
- Delete: `patch_controllers.php`, `patch_create_po.php`, `patch_dashboard.php`, `patch_excel.php`, `patch_frontend.php`, `patch_migrations.php`, `patch_migrations_strict.php`, `patch_models.php`, `patch_po_frontend.php`, `patch_po_spk.php`, `patch_projects.php`, `patch_rab_sheets.php`

**Steps:**

```bash
git rm patch_*.php
git commit -m "chore: remove dead patch scripts (already applied to source)"
```

---

### Task 1.9: Fix Config Issues

**Objective:** Set correct timezone, app name, and environment defaults.

**Files:**
- Modify: `config/app.php`
- Modify: `.env.example`

**Steps:**

1. In `config/app.php`:
```php
'timezone' => 'Asia/Jakarta',
'locale' => 'id',
'faker_locale' => 'id_ID',
```

2. In `.env.example`:
```
APP_NAME="ERP Konstruksi"
APP_TIMEZONE=Asia/Jakarta
TAX_RATE=0.11
DEFAULT_CURRENCY=IDR
```

3. Create `config/erp.php`:
```php
return [
    'tax_rate' => env('TAX_RATE', 0.11),
    'currency' => env('DEFAULT_CURRENCY', 'IDR'),
    'approval_threshold' => env('APPROVAL_THRESHOLD', 100000000),
    'roles' => [
        'ADMIN' => 1,
        'LAPANGAN' => 2,
        'ENGINEER' => 3,
        'PURCHASING_LEGAL' => 4,
        'VERIFIKATOR_KEU' => 5,
        'MGR_KOMERSIAL' => 6,
        'KEU_KANTOR' => 7,
        'PAJAK' => 8,
        'ACCOUNTING' => 9,
    ],
];
```

4. Replace hardcoded `0.11` in controllers with `config('erp.tax_rate')`.

---

## Phase 2: Backend Missing Features

### Task 2.1: Supplier/Vendor Management

**Objective:** Create master data for suppliers.

**Files:**
- Create: `database/migrations/2026_07_13_000002_create_suppliers_table.php`
- Create: `app/Models/Supplier.php`
- Create: `app/Http/Controllers/Api/SupplierController.php`
- Modify: `routes/api.php`

**Migration:**
```php
Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code')->unique();
    $table->string('npwp')->nullable();
    $table->text('address')->nullable();
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->string('bank_name')->nullable();
    $table->string('bank_account_number')->nullable();
    $table->string('bank_account_name')->nullable();
    $table->string('contact_person')->nullable();
    $table->text('notes')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
```

**Controller:** Standard CRUD with search, filter by `is_active`, pagination.

**PO Integration:** Add `supplier_id` FK to `purchase_orders` table, keep `supplier_name` for backward compat.

---

### Task 2.2: Chart of Accounts & General Ledger

**Objective:** Implement double-entry bookkeeping.

**Files:**
- Create: `database/migrations/2026_07_13_000003_create_chart_of_accounts_table.php`
- Create: `app/Models/ChartOfAccount.php`
- Create: `app/Http/Controllers/Api/GeneralLedgerController.php`
- Modify: `routes/api.php`

**Migration:**
```php
Schema::create('chart_of_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->string('name');
    $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
    $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**GL Controller with double-entry validation:**
```php
public function store(Request $request)
{
    $validated = $request->validate([
        'entries' => 'required|array|min:2',
        'entries.*.account_code' => 'required|exists:chart_of_accounts,code',
        'entries.*.debit' => 'required|numeric|min:0',
        'entries.*.credit' => 'required|numeric|min:0',
        'description' => 'required|string',
        'reference_type' => 'nullable|string',
        'reference_id' => 'nullable|integer',
    ]);

    // Validate debit = credit
    $totalDebit = collect($validated['entries'])->sum('debit');
    $totalCredit = collect($validated['entries'])->sum('credit');
    
    if (abs($totalDebit - $totalCredit) > 0.01) {
        return response()->json(['message' => 'Debit must equal credit'], 422);
    }

    return DB::transaction(function () use ($validated, $request) {
        $journalNumber = 'JRN-' . date('Ymd') . '-' . str_pad(GeneralLedger::max('id') + 1, 6, '0', STR_PAD_LEFT);
        
        foreach ($validated['entries'] as $entry) {
            GeneralLedger::create([
                ...$entry,
                'journal_number' => $journalNumber,
                'transaction_date' => now(),
                'created_by' => $request->user()->id,
            ]);
        }
        
        return response()->json(['journal_number' => $journalNumber], 201);
    });
}
```

---

### Task 2.3: User Management API

**Objective:** CRUD users with role assignment.

**Files:**
- Create: `app/Http/Controllers/Api/UserController.php`
- Modify: `routes/api.php`

**Endpoints:**
```
GET    /api/users           — list (paginated, searchable)
GET    /api/users/{id}      — show
POST   /api/users           — create (ADMIN only)
PUT    /api/users/{id}      — update
DELETE /api/users/{id}      — soft delete
PUT    /api/users/{id}/role — assign role
```

---

### Task 2.4: Tax Management API

**Objective:** Expose Tax model via API for frontend pages.

**Files:**
- Create: `app/Http/Controllers/Api/TaxController.php`
- Modify: `routes/api.php`

**Endpoints:**
```
GET    /api/taxes              — list all tax types
POST   /api/taxes              — create (ADMIN/PAJAK)
PUT    /api/taxes/{tax}        — update
POST   /api/taxes/calculate    — calculate tax for amount
```

---

### Task 2.5: Inventory Stock Movement Log

**Objective:** Track all stock in/out movements.

**Files:**
- Create: `database/migrations/2026_07_13_000004_create_stock_movements_table.php`
- Create: `app/Models/StockMovement.php`
- Modify: `app/Http/Controllers/Api/InventoryController.php`

**Migration:**
```php
Schema::create('stock_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('inventory_stock_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['in', 'out', 'adjustment']);
    $table->decimal('quantity', 15, 2);
    $table->string('reference_type')->nullable(); // GoodsReceipt, Opname, etc.
    $table->unsignedBigInteger('reference_id')->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

---

## Phase 3: Frontend Overhaul

### Task 3.1: Create Shared Component Library

**Objective:** Build reusable UI components to replace inline styles and Breeze defaults.

**Files:**
- Create: `resources/js/Components/ui/Button.jsx`
- Create: `resources/js/Components/ui/Card.jsx`
- Create: `resources/js/Components/ui/DataTable.jsx`
- Create: `resources/js/Components/ui/FormField.jsx`
- Create: `resources/js/Components/ui/StatusBadge.jsx`
- Create: `resources/js/Components/ui/Modal.jsx`
- Create: `resources/js/Components/ui/Toast.jsx`
- Create: `resources/js/Components/ui/PageHeader.jsx`
- Create: `resources/js/Components/ui/EmptyState.jsx`
- Create: `resources/js/Components/ui/LoadingSpinner.jsx`
- Create: `resources/js/Components/ui/Select.jsx`
- Create: `resources/js/Components/ui/index.js` (barrel export)

**Component specs:**

```jsx
// Button.jsx
export default function Button({ variant = 'primary', size = 'md', loading, children, ...props }) {
    const variants = {
        primary: 'bg-blue-600 text-white hover:bg-blue-700',
        secondary: 'bg-gray-200 text-gray-800 hover:bg-gray-300',
        danger: 'bg-red-600 text-white hover:bg-red-700',
        success: 'bg-green-600 text-white hover:bg-green-700',
    };
    const sizes = { sm: 'px-3 py-1.5 text-sm', md: 'px-4 py-2', lg: 'px-6 py-3 text-lg' };
    return (
        <button
            className={`rounded-lg font-medium transition ${variants[variant]} ${sizes[size]} ${loading ? 'opacity-50' : ''}`}
            disabled={loading}
            {...props}
        >
            {loading ? 'Loading...' : children}
        </button>
    );
}
```

```jsx
// DataTable.jsx
export default function DataTable({ columns, data, onSort, sortColumn, sortDir, emptyMessage = 'Belum ada data' }) {
    // Sortable headers, responsive scroll wrapper, empty state
}
```

```jsx
// Toast.jsx — replace all alert() calls
// Uses React context + portal for global toast notifications
```

---

### Task 3.2: Fix AuthenticatedLayout Sidebar

**Objective:** Fix sidebar styling, add breadcrumbs, responsive behavior.

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx`

**Changes:**
1. Remove `border-b-2` from NavLink (that's for horizontal nav)
2. Add proper sidebar active state: `bg-blue-50 border-l-4 border-blue-600`
3. Add breadcrumb component above content
4. Add mobile hamburger with slide-over drawer
5. Add user avatar + role badge in sidebar footer

---

### Task 3.3: Extract Dashboard into Components

**Objective:** Break 1,413-line Dashboard.jsx into manageable components.

**Files:**
- Create: `resources/js/Pages/Dashboard/ExecutiveSummary.jsx`
- Create: `resources/js/Pages/Dashboard/FinancialChart.jsx`
- Create: `resources/js/Pages/Dashboard/ProjectsList.jsx`
- Create: `resources/js/Pages/Dashboard/RabImport.jsx`
- Create: `resources/js/Pages/Dashboard/QuickActions.jsx`
- Create: `resources/js/Pages/Dashboard/RoleOverview.jsx`
- Modify: `resources/js/Pages/Dashboard.jsx` (orchestrator only)

---

### Task 3.4: Replace alert()/confirm()/prompt() with Toast & Modals

**Objective:** Professional UX feedback system.

**Files:**
- Modify: All pages using alert/confirm/prompt

**Changes:**
1. Create `useToast()` hook + ToastContext
2. Replace `alert('Berhasil')` → `toast.success('Berhasil')`
3. Replace `confirm('Yakin?')` → `<ConfirmModal>` component
4. Replace `prompt('Alasan')` → `<PromptModal>` with textarea

---

### Task 3.5: Add ErrorBoundary to All Pages

**Objective:** Prevent white screen on page crashes.

**Files:**
- Create: `resources/js/Components/ErrorBoundary.jsx` (extract from Dashboard)
- Modify: `resources/js/app.jsx` — wrap Inertia page resolver with ErrorBoundary

---

### Task 3.6: Standardize Data Fetching

**Objective:** Choose one pattern (Inertia props OR client-side API) per page.

**Decision:** Use Inertia server-side props for initial data, client-side axios for mutations/refreshes.

**Files:**
- Modify: All pages using mixed patterns

---

### Task 3.7: Fix Tailwind Version Conflict

**Objective:** Resolve v3/v4 incompatibility.

**Files:**
- Modify: `package.json`

**Steps:**
```bash
# Remove v4 plugin
npm uninstall @tailwindcss/vite

# Ensure v3 is consistent
npm install -D tailwindcss@^3.4.0 postcss autoprefixer
```

---

## Phase 4: Frontend Missing Pages

### Task 4.1: Purchase Order Detail & Edit Pages

**Files:**
- Create: `resources/js/Pages/PurchaseOrderDetail.jsx`
- Create: `resources/js/Pages/PurchaseOrderEdit.jsx`
- Modify: `resources/js/Pages/PurchaseOrder.jsx` (add link to detail)

**Features:**
- View full PO with line items, RAB linkage, approval history
- Edit draft POs only
- Print/PDF export button
- Status timeline

---

### Task 4.2: Supplier Management Pages

**Files:**
- Create: `resources/js/Pages/SupplierList.jsx`
- Create: `resources/js/Pages/SupplierForm.jsx`

---

### Task 4.3: User Management Pages

**Files:**
- Create: `resources/js/Pages/UserManagement.jsx`
- Create: `resources/js/Pages/UserForm.jsx`

---

### Task 4.4: Complete Stub Pages

**Files:**
- Modify: `resources/js/Pages/FakturPajak.jsx` — wire to Tax API
- Modify: `resources/js/Pages/EFakturCsv.jsx` — server-side export
- Modify: `resources/js/Pages/PostingJurnal.jsx` — wire to GL API

---

### Task 4.5: Inventory Management Pages

**Files:**
- Create: `resources/js/Pages/InventoryDashboard.jsx`
- Create: `resources/js/Pages/StockMovements.jsx`

---

### Task 4.6: Print/Export Support

**Files:**
- Create: `resources/js/Pages/Print/PurchaseOrderPrint.jsx`
- Create: `resources/js/Pages/Print/InvoicePrint.jsx`
- Create: `resources/js/Pages/Print/SpkPrint.jsx`

---

## Phase 5: Infrastructure & Quality

### Task 5.1: Create Model Factories

**Files:**
- Create: `database/factories/ProjectFactory.php`
- Create: `database/factories/RabBudgetFactory.php`
- Create: `database/factories/PurchaseOrderFactory.php`
- Create: `database/factories/SpkFactory.php`
- Create: `database/factories/InvoiceFactory.php`
- Create: `database/factories/GoodsReceiptFactory.php`
- Create: `database/factories/FundRequestFactory.php`
- Create: `database/factories/SupplierFactory.php`

---

### Task 5.2: Add Feature Tests for All API Controllers

**Files:**
- Create: `tests/Feature/Api/ProjectControllerTest.php`
- Create: `tests/Feature/Api/PurchaseOrderControllerTest.php`
- Create: `tests/Feature/Api/SpkControllerTest.php`
- Create: `tests/Feature/Api/InvoiceControllerTest.php`
- Create: `tests/Feature/Api/GoodsReceiptControllerTest.php`
- Create: `tests/Feature/Api/FundRequestControllerTest.php`
- Create: `tests/Feature/Api/RabBudgetControllerTest.php`
- Create: `tests/Feature/Api/InventoryControllerTest.php`
- Create: `tests/Feature/Api/GeneralLedgerControllerTest.php`

---

### Task 5.3: Write Real README

**Files:**
- Modify: `README.md`

**Content:**
- Project description (ERP Konstruksi)
- Architecture overview
- Setup instructions (`composer setup`)
- Role system documentation
- API endpoint list
- Business workflow diagram
- Environment variables
- Default credentials
- Contributing guidelines

---

### Task 5.4: Add GitHub Actions CI

**Files:**
- Create: `.github/workflows/ci.yml`

```yaml
name: CI
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --no-progress
      - run: cp .env.example .env
      - run: php artisan key:generate
      - run: php artisan test
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - run: npm ci
      - run: npm run build
```

---

### Task 5.5: Add Dockerfile

**Files:**
- Create: `Dockerfile`
- Create: `docker-compose.yml`
- Create: `.dockerignore`

---

## Execution Order

1. Phase 1 (Tasks 1.1–1.9) — **Security & stability first**
2. Phase 2 (Tasks 2.1–2.5) — **Backend completeness**
3. Phase 3 (Tasks 3.1–3.7) — **Frontend foundation**
4. Phase 4 (Tasks 4.1–4.6) — **Frontend features**
5. Phase 5 (Tasks 5.1–5.5) — **Quality & infrastructure**

Each phase builds on the previous. Total: ~40 tasks, estimated 20-30 hours of focused work.
