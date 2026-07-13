import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import { useApi } from '@/hooks/useApi';
import { useToast } from '@/Components/ui/Toast';
import Card from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import DataTable from '@/Components/ui/DataTable';
import PageHeader from '@/Components/ui/PageHeader';

const fmt = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(v ?? 0);

export default function EFakturCsv() {
    const toast = useToast();
    const api = useApi();
    const [taxes, setTaxes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedIds, setSelectedIds] = useState([]);
    const [generating, setGenerating] = useState(false);
    const [exportMode, setExportMode] = useState('client'); // 'client' or 'server'

    const fetchTaxes = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/api/taxes');
            setTaxes(Array.isArray(res.data) ? res.data : res.data?.data || []);
        } catch (err) {
            toast.error('Gagal memuat data pajak: ' + (err.response?.data?.message || err.message));
        } finally {
            setLoading(false);
        }
    }, [toast]);

    useEffect(() => {
        fetchTaxes();
    }, [fetchTaxes]);

    const toggle = (id) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
        );
    };

    const toggleAll = () => {
        if (selectedIds.length === taxes.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(taxes.map((t) => t.id));
        }
    };

    const buildEfaKturRow = (tax) => {
        const dpp = parseFloat(tax.base_amount || 0);
        const ppn = parseFloat(tax.tax_amount || 0);
        const tanggal = tax.tax_date || '';
        const date = tanggal ? new Date(tanggal) : new Date();
        const bulan = date.getMonth() + 1;
        const tahun = date.getFullYear();

        return [
            'FK',
            '01',                          // KD_JENIS_TRANSAKSI
            '0',                           // FG_PENGGANTI
            tax.nomor_faktur || '',        // NOMOR_FAKTUR
            bulan,                         // MASA_PAJAK
            tahun,                         // TAHUN_PAJAK
            tanggal,                       // TANGGAL_FAKTUR
            tax.npwp || '0000000000000000', // NPWP
            tax.nama_penjual || '',        // NAMA
            '',                            // ALAMAT_LENGKAP
            dpp,                           // JUMLAH_DPP
            ppn,                           // JUMLAH_PPN
            '0',                           // JUMLAH_PPNBM
            '',                            // ID_KETERANGAN_TAMBAHAN
            '',                            // FG_UANG_MUKA
            '0',                           // UANG_MUKA_DPP
            '0',                           // UANG_MUKA_PPN
            '0',                           // UANG_MUKA_PPNBM
            tax.description || '',         // REFERENSI
        ].join(',');
    };

    const handleExportClient = () => {
        if (selectedIds.length === 0) {
            toast.error('Pilih data terlebih dahulu');
            return;
        }
        setGenerating(true);
        try {
            const selected = taxes.filter((t) => selectedIds.includes(t.id));
            const header = 'FK,KD_JENIS_TRANSAKSI,FG_PENGGANTI,NOMOR_FAKTUR,MASA_PAJAK,TAHUN_PAJAK,TANGGAL_FAKTUR,NPWP,NAMA,ALAMAT_LENGKAP,JUMLAH_DPP,JUMLAH_PPN,JUMLAH_PPNBM,ID_KETERANGAN_TAMBAHAN,FG_UANG_MUKA,UANG_MUKA_DPP,UANG_MUKA_PPN,UANG_MUKA_PPNBM,REFERENSI';
            const rows = selected.map(buildEfaKturRow);
            const csv = [header, ...rows].join('\n');
            const bom = '\uFEFF'; // UTF-8 BOM for Excel compatibility
            const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `e-faktur-export-${new Date().toISOString().slice(0, 10)}.csv`;
            a.click();
            URL.revokeObjectURL(url);
            toast.success(`Berhasil export ${selected.length} baris ke CSV (client-side)`);
        } catch {
            toast.error('Gagal export CSV');
        } finally {
            setGenerating(false);
        }
    };

    const handleExportServer = async () => {
        if (selectedIds.length === 0) {
            toast.error('Pilih data terlebih dahulu');
            return;
        }
        setGenerating(true);
        try {
            const res = await api.get('/api/taxes', {
                params: { ids: selectedIds.join(',') },
                responseType: 'blob',
            });

            // If server returns CSV blob, download it directly
            if (res.headers?.['content-type']?.includes('csv') || res.headers?.['content-type']?.includes('text')) {
                const url = URL.createObjectURL(new Blob([res.data]));
                const a = document.createElement('a');
                a.href = url;
                a.download = `e-faktur-export-${new Date().toISOString().slice(0, 10)}.csv`;
                a.click();
                URL.revokeObjectURL(url);
                toast.success(`Berhasil export ${selectedIds.length} baris dari server`);
            } else {
                // Fallback: server returned JSON data, format client-side
                const data = Array.isArray(res.data) ? res.data : res.data?.data || [];
                const header = 'FK,KD_JENIS_TRANSAKSI,FG_PENGGANTI,NOMOR_FAKTUR,MASA_PAJAK,TAHUN_PAJAK,TANGGAL_FAKTUR,NPWP,NAMA,ALAMAT_LENGKAP,JUMLAH_DPP,JUMLAH_PPN,JUMLAH_PPNBM,ID_KETERANGAN_TAMBAHAN,FG_UANG_MUKA,UANG_MUKA_DPP,UANG_MUKA_PPN,UANG_MUKA_PPNBM,REFERENSI';
                const rows = data.map(buildEfaKturRow);
                const csv = [header, ...rows].join('\n');
                const bom = '\uFEFF';
                const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `e-faktur-export-${new Date().toISOString().slice(0, 10)}.csv`;
                a.click();
                URL.revokeObjectURL(url);
                toast.success(`Berhasil export ${data.length} baris (server data, client format)`);
            }
        } catch (err) {
            // Final fallback: use already-loaded data client-side
            toast.info('Server export tidak tersedia, menggunakan data lokal');
            handleExportClient();
        } finally {
            setGenerating(false);
        }
    };

    const handleExport = exportMode === 'server' ? handleExportServer : handleExportClient;

    const columns = [
        {
            key: 'select',
            label: (
                <input
                    type="checkbox"
                    checked={selectedIds.length === taxes.length && taxes.length > 0}
                    onChange={toggleAll}
                    aria-label="Select all"
                />
            ),
            render: (_, row) => (
                <input
                    type="checkbox"
                    checked={selectedIds.includes(row.id)}
                    onChange={() => toggle(row.id)}
                    aria-label={`Select ${row.nomor_faktur}`}
                />
            ),
            className: 'w-10 text-center',
        },
        { key: 'nomor_faktur', label: 'No. Faktur', render: (v) => v || '—' },
        { key: 'tax_date', label: 'Tanggal', sortable: true },
        { key: 'npwp', label: 'NPWP', render: (v) => v || '—' },
        { key: 'nama_penjual', label: 'Nama Penjual', render: (v) => v || '—' },
        { key: 'base_amount', label: 'DPP', className: 'text-right', headerClassName: 'text-right', render: (v) => fmt(v) },
        { key: 'tax_amount', label: 'PPN (11%)', className: 'text-right', headerClassName: 'text-right', render: (v) => fmt(v) },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">E-Faktur CSV</h2>}>
            <Head title="E-Faktur CSV" />
            <div className="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title="Export E-Faktur CSV"
                    subtitle="Format CSV sesuai template DJP e-Faktur untuk upload ke sistem pajak"
                    actions={
                        <div className="flex items-center gap-3">
                            <select
                                value={exportMode}
                                onChange={(e) => setExportMode(e.target.value)}
                                className="border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                <option value="client">Client-side Export</option>
                                <option value="server">Server-side Export</option>
                            </select>
                            <Button
                                variant="success"
                                onClick={handleExport}
                                loading={generating}
                                disabled={selectedIds.length === 0}
                            >
                                {generating ? 'Mengexport...' : `Export CSV (${selectedIds.length})`}
                            </Button>
                        </div>
                    }
                />

                {/* Info Card */}
                <Card>
                    <div className="flex items-start gap-3">
                        <div className="flex-shrink-0 mt-0.5">
                            <svg className="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <p className="text-sm text-gray-700">
                                <strong>Client-side:</strong> Format CSV langsung dari data di browser. Cepat dan tidak memerlukan server.
                            </p>
                            <p className="text-sm text-gray-700 mt-1">
                                <strong>Server-side:</strong> Minta server memformat data. Cocok untuk data besar atau format khusus dari backend.
                            </p>
                        </div>
                    </div>
                </Card>

                {/* Tax Data Table */}
                <Card title="Pilih Data untuk Export" subtitle="Centang baris yang ingin di-export ke format e-Faktur CSV">
                    <DataTable
                        columns={columns}
                        data={taxes}
                        loading={loading}
                        emptyMessage="Belum ada data pajak. Buat faktur pajak terlebih dahulu di halaman Faktur Pajak."
                    />
                </Card>

                {/* Preview Section */}
                {selectedIds.length > 0 && (
                    <Card title="Preview CSV" subtitle={`Menampilkan ${selectedIds.length} baris yang dipilih`}>
                        <div className="bg-gray-900 text-green-400 rounded-lg p-4 overflow-x-auto">
                            <pre className="text-xs font-mono whitespace-pre">
                                {(() => {
                                    const selected = taxes.filter((t) => selectedIds.includes(t.id));
                                    const header = 'FK,KD_JENIS_TRANSAKSI,FG_PENGGANTI,NOMOR_FAKTUR,MASA_PAJAK,TAHUN_PAJAK,TANGGAL_FAKTUR,NPWP,NAMA,ALAMAT_LENGKAP,JUMLAH_DPP,JUMLAH_PPN,JUMLAH_PPNBM,ID_KETERANGAN_TAMBAHAN,FG_UANG_MUKA,UANG_MUKA_DPP,UANG_MUKA_PPN,UANG_MUKA_PPNBM,REFERENSI';
                                    const rows = selected.map(buildEfaKturRow);
                                    return [header, ...rows].join('\n');
                                })()}
                            </pre>
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
