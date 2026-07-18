# ERP Konstruksi PT. Sinar Cerah Sempurna

ERP Konstruksi adalah aplikasi internal untuk mengelola siklus proyek konstruksi dari impor RAB, pengadaan, pekerjaan subkon, penerimaan barang, tagihan, pembayaran, pajak, sampai pencatatan akuntansi.

Dokumentasi ini menjelaskan alur yang diimplementasikan pada aplikasi saat ini. Alur bisnisnya diselaraskan dengan DAD operasional proyek: Lapangan/Proyek, Purchasing dan Legal, Engineer, Verifikator Keuangan, Manajer Komersial, Keuangan Kantor, Pajak, dan Accounting.

## Daftar Isi

- [Fungsi Utama](#fungsi-utama)
- [Peran Pengguna](#peran-pengguna)
- [Alur Kerja](#alur-kerja)
- [Notifikasi Workflow](#notifikasi-workflow)
- [Panduan Operasional Singkat](#panduan-operasional-singkat)
- [Instalasi Lokal](#instalasi-lokal)
- [Pengujian](#pengujian)
- [API Utama](#api-utama)
- [Struktur Proyek](#struktur-proyek)

## Fungsi Utama

| Area | Fungsi |
| --- | --- |
| Proyek dan RAB | Membuat proyek, mengimpor RAB Excel/CSV, memvalidasi item, dan melihat ringkasan anggaran. |
| Purchase Order | Membuat PO Proyek tanpa harga, routing oleh Engineer, membuat PO Supplier, memilih master supplier, approval, cetak, dan lampiran. |
| SPK | Membuat SPK Subkon atau Mandor dari PO Proyek yang sudah dirouting, approval, cetak, dan pelacakan pekerjaan. |
| Penerimaan Barang | Mencatat barang datang berdasarkan PO Supplier dan memperbarui persediaan. |
| Opname | Mencatat progres pekerjaan SPK untuk dasar tagihan pekerjaan. |
| Invoice dan Pembayaran | Verifikasi bertahap, kelengkapan dokumen, approval cashflow, pembayaran parsial/penuh, dan bukti bayar. |
| Permohonan Dana dan LPJ | Permohonan dana proyek, verifikasi, approval, pembayaran, unggah bukti LPJ, dan approval LPJ. |
| Pajak dan Accounting | E-Faktur, PPN, KPP, buku besar, trial balance, laporan keuangan, serta rekening koran. |
| Workflow | Notifikasi dalam aplikasi per role untuk dokumen yang membutuhkan tindak lanjut. |

## Peran Pengguna

| Role | Tanggung jawab utama |
| --- | --- |
| ADMIN | Superuser seluruh modul, mengelola pengguna, membantu pemulihan/override terkontrol, dan menerima salinan notifikasi workflow. |
| LAPANGAN | Membuat PO Proyek, menerima barang, input opname, mengajukan dana, dan mengirim LPJ. |
| ENGINEER | Menyetujui RAB secara teknis, memverifikasi kebutuhan PO Proyek dan invoice, serta memastikan routing PO sesuai kategori RAB. |
| PURCHASING_LEGAL | Mengelola supplier, membuat PO Supplier dan SPK, serta input tagihan. |
| VERIFIKATOR_KEU | Memverifikasi dokumen invoice, permohonan dana, LPJ, dan approval cashflow. |
| MGR_KOMERSIAL | Menyetujui PO Supplier, SPK, invoice, dan permohonan dana/LPJ sesuai tahapnya. |
| KEU_KANTOR | Mengeksekusi pembayaran invoice dan permohonan dana. |
| PAJAK | Mengelola faktur pajak, E-Faktur, PPN, KPP, kompensasi, atau restitusi. |
| ACCOUNTING | Posting jurnal, melihat laporan keuangan, dan menangani rekening koran. |

## Alur Kerja

### Gambaran Umum

~~~mermaid
flowchart TD
    A[Upload file Excel RAB] --> B[Preview seluruh sheet dan pilih sheet RAB]
    B --> C[Kelompokkan setiap item]
    C --> D{Kategori}
    D -->|Material| M[Material]
    D -->|Subkon| S[Subkon]
    D -->|Pekerja| P[Pekerja]
    D -->|Alat| T[Alat]
    M --> E[Draft RAB]
    S --> E
    P --> E
    T --> E
    E --> F[Ajukan approval teknis]
    F --> G{Engineer}
    G -->|Tolak| C
    G -->|Setujui| H[RAB terkunci dan siap dipakai]
    H --> I[PO Proyek per kategori]
    I --> J{Routing sesuai kategori}
    J -->|Material| K[PO Supplier]
    J -->|Subkon| L[SPK Subkon]
    J -->|Pekerja| N[SPK Mandor]
    J -->|Alat| O[SPK alat atau sewa]
    K --> Q[Penerimaan Barang]
    Q --> R[Stok Material bertambah]
    L --> U[Opname]
    N --> U
    O --> U
    R --> V[Invoice dan approval pembayaran]
    U --> V
    V --> W[Pembayaran, jurnal, dan laporan]
~~~

RAB hanya menyimpan rencana biaya. Impor RAB tidak menambah stok. Stok Material baru dibuat atau ditambah setelah barang pada PO Supplier yang disetujui benar-benar dicatat di Penerimaan Barang.

### Pengadaan: PO Proyek, PO Supplier, dan SPK

~~~mermaid
flowchart LR
    subgraph LP["Lapangan / Proyek"]
        A[Input PO Proyek]
    end

    subgraph ENG["Engineer"]
        B[Verifikasi Kebutuhan]
        C{Sesuai kebutuhan?}
        D{Validasi kategori}
    end

    subgraph PUR["Purchasing dan Legal"]
        E[Buat PO Supplier]
        F[Buat SPK Subkon atau Mandor]
    end

    subgraph MGR["Manajer Komersial"]
        G[Approval PO Supplier atau SPK]
    end

    A --> B --> C
    C -->|Tidak| A
    C -->|Ya| D
    D -->|Material| E --> G
    D -->|Subkon atau Alat| F --> G
    D -->|Pekerja| J[SPK Mandor] --> G
    G -->|Ditolak| A
    G -->|Disetujui: PO| H[Penerimaan Barang]
    G -->|Disetujui: SPK| I[Input Opname]
~~~

Aturan penting:

- PO Proyek dibuat dari item RAB yang sudah disetujui dan tidak memuat harga supplier.
- Satu PO Proyek hanya boleh berisi satu kategori RAB; kategori berbeda harus dipisahkan ke dokumen berbeda.
- Engineer tidak bebas memilih tujuan yang bertentangan dengan kategori: Material wajib ke PO Supplier, sedangkan Subkon, Pekerja, dan Alat wajib ke SPK.
- PO Supplier wajib memiliki PO Proyek sumber yang telah diarahkan Engineer ke PURCHASE_ORDER.
- SPK wajib memiliki PO Proyek sumber yang telah diarahkan Engineer ke SPK.
- Kategori Pekerja dibuat sebagai SPK Mandor. Subkon dan Alat menggunakan SPK Subkon/jasa.
- Pada PO Supplier, pengguna dapat memilih data dari Master Supplier. Nama, alamat, telepon, dan PIC diisi otomatis, kemudian masih dapat disesuaikan untuk dokumen tersebut.
- PO Supplier dan SPK yang sudah dikirim hanya dapat disetujui atau ditolak pada tahap Manajer Komersial.

### Material: Penerimaan Barang sampai Invoice

~~~mermaid
flowchart LR
    A[RAB kategori Material] --> B[PO Proyek]
    B --> C[Engineer arahkan ke PO Supplier]
    C --> D[PO Supplier disetujui]
    D --> E[Logistik/Lapangan menerima barang]
    E --> F[Cek barang dan surat jalan]
    F --> G[Input Penerimaan Barang]
    G --> H[Stok Material dibuat atau ditambah]
    H --> I[Input invoice material]
    I --> J[Verifikasi Engineer]
    J --> K[Verifikasi Keuangan]
    K --> L[Approval Manajer]
    L --> M[Approval Cashflow]
    M --> N[Eksekusi pembayaran]
~~~

Invoice material hanya dapat dibuat untuk PO Supplier yang sudah memiliki penerimaan barang. Dokumen wajib untuk verifikasi finance adalah Invoice, PO, dan Surat Jalan.

### Pekerjaan Subkon: SPK, Opname, dan Invoice

~~~mermaid
flowchart LR
    A[SPK disetujui] --> B[Pelaksanaan pekerjaan]
    B --> C[Input Opname]
    C --> D[Approval Opname]
    D --> E[Input invoice SPK]
    E --> F[Verifikasi Engineer]
    F --> G[Verifikasi Keuangan]
    G --> H[Approval Manajer]
    H --> I[Approval Cashflow]
    I --> J[Pembayaran]
~~~

Invoice SPK hanya dapat dibuat dari SPK dan Opname yang telah disetujui. Dokumen wajib untuk verifikasi finance adalah Invoice, SPK, Opname, dan BAST.

### Permohonan Dana dan LPJ

~~~mermaid
flowchart LR
    A[Keuangan Proyek membuat permohonan dana] --> B[Verifikator Keuangan]
    B --> C[Manajer Komersial]
    C --> D[Keuangan Kantor]
    D --> E[Dana dibayarkan]
    E --> F[Keuangan Proyek mengirim LPJ dan lampiran]
    F --> G[Verifikator Keuangan memeriksa LPJ]
    G --> H[Manajer Komersial menyetujui LPJ]
    H --> I[Workflow selesai]

    B -->|Ditolak| A
    C -->|Ditolak| A
~~~

Nilai rincian LPJ harus sama dengan nilai permohonan dana dan minimal satu dokumen LPJ harus diunggah sebelum LPJ dikirim.

### Invoice, Cashflow, Pembayaran, Pajak, dan Accounting

~~~mermaid
flowchart LR
    A[Invoice dibuat] --> B[Engineer memverifikasi kesesuaian]
    B --> C[Verifikator Keuangan cek dokumen dan nilai]
    C --> D[Manajer Komersial menyetujui]
    D --> E[Verifikator Keuangan finalisasi cashflow]
    E --> F[Keuangan Kantor membayar]
    F --> G[Bukti bayar dan transaksi tersimpan]
    G --> H[Posting jurnal otomatis]
    H --> I[Rekening koran dan laporan keuangan]

    J[Upload E-Faktur CSV] --> K[Validasi pajak]
    K --> L[Konfirmasi BKP dan berkas KPP]
    L --> M[Kompensasi atau restitusi PPN]
    M --> N[Rekap Accounting]
~~~

Status pembayaran invoice mendukung UNPAID, PARTIAL, dan PAID. Bukti pembayaran dapat diunggah saat eksekusi pembayaran. Jika akun jurnal telah tersedia, pembayaran diposting ke buku besar.

## Notifikasi Workflow

Setiap perpindahan dokumen utama membuat notifikasi tersimpan di ikon lonceng pada header. Notifikasi dipolling secara berkala dan dapat ditandai sudah dibaca. Mengklik notifikasi akan membuka halaman kerja yang sesuai.

| Kejadian | Tujuan notifikasi |
| --- | --- |
| RAB diajukan | Engineer: Approval RAB teknis |
| PO Proyek dibuat | Engineer: Verifikasi Kebutuhan |
| Engineer merouting PO ke PO Supplier atau SPK | Purchasing dan Legal |
| PO Supplier atau SPK dikirim | Manajer Komersial |
| PO Supplier atau SPK disetujui | Lapangan dan pembuat dokumen |
| Invoice dibuat | Engineer: Verifikasi Tagihan |
| Invoice lolos Engineer | Verifikator Keuangan |
| Invoice lolos Finance | Manajer Komersial |
| Cashflow invoice disetujui | Keuangan Kantor |
| Permohonan dana atau LPJ dikirim | Role verifikator pada tahap berikutnya |

ADMIN menerima salinan notifikasi agar dapat memantau seluruh handoff workflow.

## Panduan Operasional Singkat

### Membuat PO Supplier dari kebutuhan proyek

1. Pastikan item RAB berkategori Material dan sudah disetujui Engineer.
2. Lapangan membuat PO Proyek pada menu Purchase Orders; satu PO hanya berisi kategori Material.
3. Engineer membuka Verifikasi Kebutuhan dan meneruskan ke PO Supplier.
4. Purchasing dan Legal menerima notifikasi, membuka Purchase Orders, lalu membuat PO Supplier.
5. Pada form PO Supplier, pilih PO Proyek sumber dan pilih supplier dari Master Supplier bila sudah terdaftar.
6. Klik Kirim Approval pada daftar PO.
7. Manajer Komersial melakukan approval.
8. Saat material tiba, catat pada menu Penerimaan Barang; pada tahap ini stok baru bertambah.

### Membuat SPK dari kebutuhan proyek

1. Pastikan item RAB berkategori Subkon, Pekerja, atau Alat dan sudah disetujui Engineer.
2. Lapangan membuat PO Proyek terpisah untuk satu kategori tersebut.
3. Engineer membuka Verifikasi Kebutuhan dan meneruskan ke SPK sesuai kategori.
4. Purchasing dan Legal membuka Kontrak SPK dan membuat SPK dari PO Proyek sumber; kategori Pekerja otomatis menggunakan tipe Mandor.
5. Kirim SPK untuk approval Manajer Komersial.
6. Setelah disetujui, Lapangan mencatat progres melalui Input Opname.

### Memproses invoice

1. Purchasing dan Legal menginput invoice beserta dokumen pendukung.
2. Engineer membuka Verifikasi Tagihan untuk memastikan kesesuaian pekerjaan atau material.
3. Verifikator Keuangan memeriksa kelengkapan dokumen dan nilai tagihan.
4. Manajer Komersial memberi approval.
5. Verifikator Keuangan menyetujui cashflow.
6. Keuangan Kantor mencatat pembayaran dan bukti bayar.

## Instalasi Lokal

### Prasyarat

- PHP 8.3 atau lebih baru
- Composer 2
- Node.js 18 atau lebih baru dan npm
- SQLite untuk pengembangan lokal atau MySQL untuk lingkungan bersama/produksi

### Menjalankan aplikasi

~~~bash
git clone https://github.com/farasfauzan/rep-sangar.git
cd rep-sangar

composer install
npm install
~~~

Buat file .env dari .env.example, lalu atur koneksi database. Untuk Windows PowerShell:

~~~powershell
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
~~~

Aplikasi dapat diakses di http://127.0.0.1:8000.

Untuk menjalankan server, queue, log, dan Vite sekaligus:

~~~bash
composer run dev
~~~

### Akun seed lokal

Seeder membuat satu akun untuk setiap role. Format email adalah nama role huruf kecil diikuti @erp.com; kata sandi awalnya adalah password.

| Role | Email |
| --- | --- |
| ADMIN | admin@erp.com |
| ENGINEER | engineer@erp.com |
| PURCHASING_LEGAL | purchasing_legal@erp.com |
| VERIFIKATOR_KEU | verifikator_keu@erp.com |
| MGR_KOMERSIAL | mgr_komersial@erp.com |
| KEU_KANTOR | keu_kantor@erp.com |

Ganti kata sandi default sebelum aplikasi dipakai oleh pengguna selain pengembangan lokal.

## Pengujian

~~~bash
# Seluruh test backend
php artisan test

# Test workflow tertentu
php artisan test tests/Feature/Api/PurchaseOrderControllerTest.php
php artisan test tests/Feature/Api/WorkflowNotificationTest.php

# Build frontend untuk memastikan modul React terkompilasi
npm run build
~~~

## API Utama

API menggunakan autentikasi sesi aplikasi dan berada di bawah prefix /api. Endpoint di bawah ini memerlukan pengguna yang sudah login serta role yang sesuai.

| Area | Contoh endpoint |
| --- | --- |
| Proyek | GET /api/projects, POST /api/projects |
| RAB | GET /api/rab, POST /api/rab/import/upload |
| PO | GET /api/pos, POST /api/pos, PUT /api/pos/{id}/route |
| SPK | GET /api/spks, POST /api/spks, PUT /api/spks/{id}/submit |
| Penerimaan barang | GET /api/goods-receipts, POST /api/goods-receipts |
| Opname | GET /api/opnames, POST /api/opnames |
| Invoice | GET /api/invoices, POST /api/invoices, PUT /api/invoices/{id}/engineer-verify |
| Dana dan LPJ | GET /api/fund-requests, POST /api/fund-requests, PUT /api/fund-requests/{id}/lpj |
| Supplier | GET /api/suppliers, POST /api/suppliers |
| Notifikasi | GET /api/notifications, PUT /api/notifications/{id}/read |
| Accounting | GET /api/general-ledger, GET /api/bank-statements |
| Pajak | GET /api/taxes, POST /api/efaktur/upload |

Lihat routes/api.php untuk daftar endpoint lengkap dan pembatasan role.

## Struktur Proyek

~~~text
app/
  Http/Controllers/Api/    Controller API per modul
  Models/                  Model Eloquent
  Notifications/           Notifikasi workflow tersimpan
  Services/                Layanan workflow dan integrasi
database/
  migrations/              Skema database
  seeders/                 Data awal, role, dan contoh proyek
resources/js/
  Pages/                   Halaman React/Inertia
  Layouts/                 Layout aplikasi dan sidebar
  Components/              Komponen UI termasuk notifikasi
routes/
  web.php                  Rute halaman Inertia
  api.php                  Rute JSON API dan middleware role
tests/Feature/Api/         Test feature dan workflow API
~~~

## Catatan Pengoperasian

- Jalankan php artisan migrate setiap kali mengambil perubahan yang memiliki migration baru.
- Jangan menggunakan php artisan migrate:fresh pada database yang berisi data operasional karena perintah tersebut menghapus seluruh tabel sebelum migrasi ulang.
- File lampiran disimpan melalui disk public; pastikan php artisan storage:link telah dijalankan pada lingkungan yang membutuhkan akses file publik.
- Permission menu membantu navigasi, tetapi API juga dibatasi oleh middleware role. Selalu gunakan akun sesuai peran prosesnya saat pengujian workflow.

## Lisensi

Proprietary. Seluruh hak cipta dilindungi.
