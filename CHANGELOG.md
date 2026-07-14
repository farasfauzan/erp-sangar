# Changelog — ERP Konstruksi

## [Unreleased]

### 🔧 Bug Fixes

#### RAB Data Integrity
- **Fix `total_price = 0`** — 594 items GIK UGM punya `volume` dan `unit_price` tapi `total_price = 0`. Bulk update: `total_price = volume * unit_price`
- **Fix corrupted items** — 4 items dengan `description="2", vol=4, price=5, total=6` → diupdate ke `total = 20`
- **Fix duplicate items** — 1.770 duplikat GIK UGM (items dengan `total_price = 0` yang punya versi `total_price > 0`) dihapus. PO items di-repoint ke versi yang benar

#### MimoAiService
- **Fix `TypeError: null to string`** — `config('services.mimo.api_key')` return `null` karena env `MIMO_API_KEY` belum di-set. Property typed `string` tidak terima `null`
- **Fix:** `$this->apiKey = config('services.mimo.api_key') ?? ''`
- **Akibat:** Import RAB gagal karena AI categorization crash sebelum import jalan

#### Faktur Pajak
- **Fix `TypeError: t.reduce is not a function`** — API `/api/taxes` return `{success: true, data: paginator}` tapi code expect array. `res.data.data` = paginator object, bukan array items
- **Fix:** `const items = res.data?.data; setTaxes(Array.isArray(items) ? items : items?.data || [])`

#### PHP Upload Limit
- **Fix file upload gagal** — `C:\php83\php.ini` punya `upload_max_filesize = 2M`. File6.5MB ditolak
- **Fix:** Update ke `upload_max_filesize = 20M`, `post_max_size = 25M`

### ✨ New Features

#### Sheet Selection untuk Import RAB
- **Problem:** Import scan semua sheet → data dari sheet berbeda tercampur (GIK UGM: 3819 items dari multi-sheet vs 1032 items dari "RAB GIK UGM" sheet)
- **Solution:** User pilih sheet mana yang mau di-import

**Backend:**
- Migration: `sheet_name` column di `rab_import_jobs`
- `parseRaw()` — accept `$filterSheet` parameter, filter sheet by name
- `importAsync()` — accept `sheet_name` parameter
- `ValidateRabImportJob` — return `available_sheets` di diff response
- `revalidateImport()` — endpoint baru: update sheet_name, re-trigger validation
- Route: `POST /rab/import-job/{id}/revalidate`

**Frontend (React):**
- Sheet selection UI — tombol untuk setiap sheet yang tersedia
- `handleSheetSelect()` — panggil revalidate endpoint, restart polling
- Tampilkan `selected_sheet` di validation result

**Flow baru:**
```
Upload → Validate (all sheets) → Show sheet buttons → User picks → Re-validate → Confirm → Import
```

### 📊 Data Updates

#### RSUD Mentawai
- Import dari `B. RAB RSUD MENTAWAI FIX-2.xlsx` → 765 items, Rp 123.109.369.914

#### GIK UGM
- Cleanup: 3819 → 2049 items (hapus 1.770 duplikat)
- Import dari `C.1 RAB GIK UGM Ulang-2.xlsx` → 1033 items dari sheet "RAB GIK UGM"

### 🧪 Testing

#### E2E Tests
- `all-roles-test.spec.ts` — 58 test, semua 9 roles (login, sidebar, navigation, logout)
- `full-feature-test.spec.ts` — 28 test, admin comprehensive
- `rab-import-test.spec.ts` — import test multiple file formats
- `sheet-select-debug.spec.ts` — sheet selection feature test
- `full-import-flow.spec.ts` — end-to-end import flow

#### Test Results
- **86 test passed** (full-feature + all-roles)
- **Sheet selection:** 5 sheets detected, user pilih, re-validate works
- **Import flow:** Upload → validate → select sheet → confirm → import → verify

### 📁 Files Changed

#### Backend
- `app/Http/Controllers/Api/RabBudgetController.php` — `importAsync()`, `revalidateImport()`
- `app/Http/Controllers/Api/DashboardReportController.php` — (no changes)
- `app/Jobs/ValidateRabImportJob.php` — pass `sheet_name` to `parseRaw`, add `available_sheets` to diff
- `app/Jobs/ExecuteRabImportJob.php` — pass `sheet_name` to `parseRaw`
- `app/Models/RabImportJob.php` — add `sheet_name` to fillable
- `app/Services/MimoAiService.php` — fix null apiKey
- `app/Traits/HandlesRabParsing.php` — add `$filterSheet` parameter to `parseRaw()`
- `app/Http/Controllers/RabStorageController.php` — (no changes)
- `database/migrations/2026_07_14_120133_add_sheet_name_to_rab_import_jobs_table.php` — new
- `routes/web.php` — add revalidate route

#### Frontend
- `resources/js/Pages/Dashboard/RabImport.jsx` — sheet selection UI
- `resources/js/Pages/FakturPajak.jsx` — fix API response parsing

#### Config
- `C:\php83\php.ini` — upload_max_filesize=20M, post_max_size=25M
