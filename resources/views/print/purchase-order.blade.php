<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak PO - {{ $po->po_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.5; }
        .container { max-width: 210mm; margin: 0 auto; padding: 20mm; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1a1a1a; padding-bottom: 12px; margin-bottom: 20px; }
        .header-left h1 { font-size: 18px; font-weight: 700; }
        .header-left p { font-size: 11px; color: #555; }
        .header-right h2 { font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-table { width: 100%; font-size: 12px; }
        .info-table td { padding: 3px 0; vertical-align: top; }
        .info-table .label { color: #666; width: 130px; }
        .info-table .value { font-weight: 600; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.items th { background: #1a1a1a; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; }
        table.items th.right, table.items td.right { text-align: right; }
        table.items td { border: 1px solid #ddd; padding: 6px 10px; font-size: 12px; }
        table.items tr:nth-child(even) { background: #f9f9f9; }
        .totals { display: flex; justify-content: flex-end; margin-bottom: 20px; }
        .totals-box { width: 260px; }
        .totals-box .row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 12px; }
        .totals-box .row.total { border-top: 2px solid #1a1a1a; padding-top: 8px; margin-top: 4px; font-weight: 700; font-size: 14px; }
        .notes { margin-bottom: 20px; padding: 10px; background: #f5f5f5; border-radius: 4px; font-size: 12px; }
        .notes h3 { font-size: 12px; font-weight: 700; margin-bottom: 4px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 40px; margin-top: 40px; padding-top: 16px; border-top: 1px solid #ccc; }
        .signatures .col { text-align: center; }
        .signatures .line { height: 60px; border-bottom: 1px solid #999; margin: 0 20px 8px; }
        .signatures .name { font-size: 11px; color: #555; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 10px; color: #999; text-align: center; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .container { padding: 0; }
            .no-print { display: none !important; }
            @page { size: A4; margin: 15mm; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="no-print" style="text-align: right; margin-bottom: 16px;">
            <button onclick="window.print()" style="padding: 8px 20px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">🖨️ Cetak</button>
            <button onclick="window.history.back()" style="padding: 8px 20px; background: #e5e7eb; color: #333; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">← Kembali</button>
        </div>

        <div class="header">
            <div class="header-left">
                <h1>PT. Nama Perusahaan</h1>
                <p>Jl. Contoh No. 123, Kota, Provinsi</p>
                <p>Telp: (021) 123-4567 | Email: info@perusahaan.com</p>
            </div>
            <div class="header-right">
                <h2>Purchase Order</h2>
                <p style="font-size: 11px; color: #666;">{{ $po->po_level === 'SUPPLIER' ? 'Supplier' : 'Project' }}</p>
            </div>
        </div>

        <div class="info-grid">
            <table class="info-table">
                <tr><td class="label">No. PO</td><td class="value">: {{ $po->po_number }}</td></tr>
                <tr><td class="label">Tanggal</td><td class="value">: {{ \Carbon\Carbon::parse($po->date)->translatedFormat('d F Y') }}</td></tr>
                <tr><td class="label">Tipe</td><td class="value">: {{ str_replace('_', ' ', $po->po_type ?? '-') }}</td></tr>
                @if($po->addendum_number)
                <tr><td class="label">No. Addendum</td><td class="value">: {{ $po->addendum_number }}</td></tr>
                @endif
                <tr><td class="label">Status</td><td class="value">: {{ str_replace('_', ' ', $po->status) }}</td></tr>
            </table>
            <table class="info-table">
                <tr><td class="label">Supplier</td><td class="value">: {{ $po->supplier_name }}</td></tr>
                @if($po->supplier_address)
                <tr><td class="label">Alamat Supplier</td><td class="value">: {{ $po->supplier_address }}</td></tr>
                @endif
                @if($po->supplier_phone)
                <tr><td class="label">Telp Supplier</td><td class="value">: {{ $po->supplier_phone }}</td></tr>
                @endif
                @if($po->supplier_contact_person)
                <tr><td class="label">Contact Person</td><td class="value">: {{ $po->supplier_contact_person }}</td></tr>
                @endif
                <tr><td class="label">Proyek</td><td class="value">: {{ $po->project?->project_name ?? '-' }}</td></tr>
                @if($po->project_location)
                <tr><td class="label">Lokasi Proyek</td><td class="value">: {{ $po->project_location }}</td></tr>
                @endif
            </table>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:40px">No</th>
                    <th>Nama Item</th>
                    <th class="right" style="width:80px">Qty</th>
                    <th class="right" style="width:140px">Harga Satuan</th>
                    <th class="right" style="width:140px">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($po->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->item_name }}</td>
                    <td class="right">{{ number_format($item->qty, 2, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($item->total_price, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center; color:#999; padding:16px;">Tidak ada item.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-box">
                <div class="row"><span>Subtotal:</span><span>Rp {{ number_format($po->subtotal, 0, ',', '.') }}</span></div>
                @if($po->discount > 0)
                <div class="row" style="color:#dc2626;"><span>Diskon:</span><span>- Rp {{ number_format($po->discount, 0, ',', '.') }}</span></div>
                @endif
                @if($po->include_ppn)
                <div class="row"><span>PPN 11%:</span><span>Rp {{ number_format($po->tax_amount, 0, ',', '.') }}</span></div>
                @endif
                <div class="row total"><span>Grand Total:</span><span>Rp {{ number_format($po->total_amount, 0, ',', '.') }}</span></div>
            </div>
        </div>

        @if($po->payment_terms)
        <div class="notes">
            <h3>Syarat Pembayaran</h3>
            <p>{{ $po->payment_terms }}</p>
        </div>
        @endif

        @if($po->catatan)
        <div class="notes">
            <h3>Catatan</h3>
            <p>{{ $po->catatan }}</p>
        </div>
        @endif

        @if($po->faktur_pajak_nama)
        <div class="notes">
            <h3>Faktur Pajak</h3>
            <p>Nama: {{ $po->faktur_pajak_nama }}<br>
            @if($po->faktur_pajak_npwp)NPWP: {{ $po->faktur_pajak_npwp }}<br>@endif
            @if($po->faktur_pajak_alamat)Alamat: {{ $po->faktur_pajak_alamat }}</p>@endif
        </div>
        @endif

        <div class="signatures">
            <div class="col">
                <p style="font-size: 12px; color: #666; margin-bottom: 4px;">Disiapkan oleh</p>
                <div class="line"></div>
                <p class="name">(___________________)</p>
                <p style="font-size: 10px; color: #999;">Nama & Tanda Tangan</p>
            </div>
            <div class="col">
                <p style="font-size: 12px; color: #666; margin-bottom: 4px;">Diperiksa oleh</p>
                <div class="line"></div>
                <p class="name">(___________________)</p>
                <p style="font-size: 10px; color: #999;">Nama & Tanda Tangan</p>
            </div>
            <div class="col">
                <p style="font-size: 12px; color: #666; margin-bottom: 4px;">Disetujui oleh</p>
                <div class="line"></div>
                <p class="name">(___________________)</p>
                <p style="font-size: 10px; color: #999;">Nama & Tanda Tangan</p>
            </div>
        </div>

        <div class="footer">
            Dicetak pada {{ now()->translatedFormat('d F Y, H:i') }}
        </div>
    </div>
</body>
</html>
