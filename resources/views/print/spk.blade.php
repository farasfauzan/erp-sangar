<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak SPK - {{ $spk->spk_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.5; }
        .container { max-width: 210mm; margin: 0 auto; padding: 20mm; }
        .header { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; }
        .header h2 { font-size: 14px; font-weight: 600; color: #4f46e5; margin-top: 4px; }
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
            <h1>Surat Perintah Kerja (SPK)</h1>
            <h2>{{ $spk->spk_type === 'SUBKON' ? 'Subkontraktor' : 'Mandor' }}</h2>
        </div>

        <div class="info-grid">
            <table class="info-table">
                <tr><td class="label">No. SPK</td><td class="value">: {{ $spk->spk_number }}</td></tr>
                <tr><td class="label">Tipe SPK</td><td class="value">: {{ $spk->spk_type === 'SUBKON' ? 'Subkontraktor' : 'Mandor' }}</td></tr>
                <tr><td class="label">Status</td><td class="value">: {{ str_replace('_', ' ', $spk->status) }}</td></tr>
                @if($spk->jadwal_kirim)
                <tr><td class="label">Jadwal Kirim</td><td class="value">: {{ $spk->jadwal_kirim->translatedFormat('d F Y') }}</td></tr>
                @endif
            </table>
            <table class="info-table">
                <tr><td class="label">Proyek</td><td class="value">: {{ $spk->project?->project_name ?? '-' }}</td></tr>
                <tr><td class="label">{{ $spk->spk_type === 'SUBKON' ? 'Subkontraktor' : 'Mandor' }}</td><td class="value">: {{ $spk->subcon_name }}</td></tr>
                <tr><td class="label">Syarat Pembayaran</td><td class="value">: {{ $spk->payment_terms ?: '-' }}</td></tr>
            </table>
        </div>

        @if($spk->progress->count() > 0)
        <table class="items">
            <thead>
                <tr>
                    <th style="width:40px">No</th>
                    <th>Pekerjaan</th>
                    <th class="right" style="width:100px">Progress (%)</th>
                    <th class="right" style="width:160px">Nilai (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($spk->progress as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->work_description }}</td>
                    <td class="right">{{ number_format($item->progress_percentage, 2, ',', '.') }}%</td>
                    <td class="right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <div class="totals">
            <div class="totals-box">
                <div class="row"><span>Subtotal:</span><span>Rp {{ number_format($spk->subtotal, 0, ',', '.') }}</span></div>
                @if($spk->include_ppn)
                <div class="row"><span>PPN 11%:</span><span>Rp {{ number_format($spk->tax_amount, 0, ',', '.') }}</span></div>
                @endif
                <div class="row total"><span>Grand Total:</span><span>Rp {{ number_format($spk->total_amount, 0, ',', '.') }}</span></div>
            </div>
        </div>

        @if($spk->payment_terms)
        <div class="notes">
            <h3>Syarat Pembayaran</h3>
            <p>{{ $spk->payment_terms }}</p>
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
                <p style="font-size: 12px; color: #666; margin-bottom: 4px;">Penerima / {{ $spk->spk_type === 'SUBKON' ? 'Subkontraktor' : 'Mandor' }}</p>
                <div class="line"></div>
                <p class="name">({{ $spk->subcon_name }})</p>
                <p style="font-size: 10px; color: #999;">Nama & Tanda Tangan</p>
            </div>
        </div>

        <div class="footer">
            Dicetak pada {{ now()->translatedFormat('d F Y, H:i') }}
        </div>
    </div>
</body>
</html>
