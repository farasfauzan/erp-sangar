import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import { useToast } from '@/Components/ui/Toast';
import Card from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import FormField from '@/Components/ui/FormField';
import DataTable from '@/Components/ui/DataTable';
import StatusBadge from '@/Components/ui/StatusBadge';
import PageHeader from '@/Components/ui/PageHeader';

const fmt = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(v ?? 0);

export default function FakturPajak() {
    const toast = useToast();
    const [taxes, setTaxes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [calculating, setCalculating] = useState(false);
    const [calcResult, setCalcResult] = useState(null);
    const [form, setForm] = useState({
        tax_type: 'PPN',
        rate: '11',
        base_amount: '',
        description: '',
        tax_date: new Date().toISOString().split('T')[0],
        npwp: '',
        nama_penjual: '',
        nomor_faktur: '',
    });

    const fetchTaxes = useCallback(async () => {
        setLoading(true);
        try {
            const res = await axios.get('/api/taxes');
            const items = res.data?.data;
            setTaxes(Array.isArray(items) ? items : items?.data || []);
        } catch (err) {
            toast.error('Gagal memuat data pajak: ' + (err.response?.data?.message || err.message));
        } finally {
            setLoading(false);
        }
    }, [toast]);

    useEffect(() => {
        fetchTaxes();
    }, [fetchTaxes]);

    const handleChange = (field, value) => {
        setForm((prev) => ({ ...prev, [field]: value }));
    };

    const handleCalculate = async () => {
        if (!form.base_amount || parseFloat(form.base_amount) <= 0) {
            toast.error('Masukkan DPP terlebih dahulu');
            return;
        }
        setCalculating(true);
        setCalcResult(null);
        try {
            const res = await axios.post('/api/taxes/calculate', {
                base_amount: parseFloat(form.base_amount),
                rate: parseFloat(form.rate) || 11,
                tax_type: form.tax_type,
            });
            setCalcResult(res.data);
            toast.success('Perhitungan pajak berhasil');
        } catch (err) {
            toast.error('Gagal menghitung pajak: ' + (err.response?.data?.message || err.message));
        } finally {
            setCalculating(false);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.base_amount || parseFloat(form.base_amount) <= 0) {
            toast.error('Masukkan DPP yang valid');
            return;
        }
        setSubmitting(true);
        try {
            await axios.post('/api/taxes', {
                tax_type: form.tax_type,
                rate: parseFloat(form.rate) || 11,
                base_amount: parseFloat(form.base_amount),
                tax_amount: calcResult?.tax_amount || parseFloat(form.base_amount) * (parseFloat(form.rate) || 11) / 100,
                description: form.description,
                tax_date: form.tax_date,
                npwp: form.npwp,
                nama_penjual: form.nama_penjual,
                nomor_faktur: form.nomor_faktur,
            });
            toast.success('Faktur pajak berhasil disimpan');
            setShowForm(false);
            setForm({
                tax_type: 'PPN',
                rate: '11',
                base_amount: '',
                description: '',
                tax_date: new Date().toISOString().split('T')[0],
                npwp: '',
                nama_penjual: '',
                nomor_faktur: '',
            });
            setCalcResult(null);
            fetchTaxes();
        } catch (err) {
            toast.error('Gagal menyimpan faktur pajak: ' + (err.response?.data?.message || err.message));
        } finally {
            setSubmitting(false);
        }
    };

    const columns = [
        { key: 'nomor_faktur', label: 'No. Faktur', render: (v) => v || '—' },
        { key: 'tax_date', label: 'Tanggal', sortable: true },
        { key: 'npwp', label: 'NPWP', render: (v) => v || '—' },
        { key: 'nama_penjual', label: 'Nama Penjual', render: (v) => v || '—' },
        { key: 'tax_type', label: 'Jenis Pajak', render: (v) => <StatusBadge status={v || 'PPN'} /> },
        { key: 'rate', label: 'Tarif (%)', className: 'text-right', headerClassName: 'text-right', render: (v) => `${v || 0}%` },
        { key: 'base_amount', label: 'DPP', className: 'text-right', headerClassName: 'text-right', render: (v) => fmt(v) },
        { key: 'tax_amount', label: 'PPN', className: 'text-right', headerClassName: 'text-right', render: (v) => fmt(v) },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v || 'draft'} /> },
    ];

    const totalDpp = taxes.reduce((sum, t) => sum + parseFloat(t.base_amount || 0), 0);
    const totalPpn = taxes.reduce((sum, t) => sum + parseFloat(t.tax_amount || 0), 0);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Faktur Pajak</h2>}>
            <Head title="Faktur Pajak" />
            <div className="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title="Faktur Pajak"
                    subtitle="Kelola faktur pajak dan hitung PPN"
                    actions={
                        <Button onClick={() => setShowForm(!showForm)}>
                            {showForm ? 'Tutup Form' : '+ Buat Faktur Pajak'}
                        </Button>
                    }
                />

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <div className="text-center">
                            <p className="text-sm text-gray-500">Total Faktur</p>
                            <p className="text-2xl font-bold text-gray-900">{taxes.length}</p>
                        </div>
                    </Card>
                    <Card>
                        <div className="text-center">
                            <p className="text-sm text-gray-500">Total DPP</p>
                            <p className="text-2xl font-bold text-indigo-600">{fmt(totalDpp)}</p>
                        </div>
                    </Card>
                    <Card>
                        <div className="text-center">
                            <p className="text-sm text-gray-500">Total PPN</p>
                            <p className="text-2xl font-bold text-emerald-600">{fmt(totalPpn)}</p>
                        </div>
                    </Card>
                </div>

                {/* Create Form */}
                {showForm && (
                    <Card title="Form Faktur Pajak Baru" subtitle="Isi data untuk membuat faktur pajak baru">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <FormField label="No. Faktur Pajak" required>
                                    <input
                                        type="text"
                                        value={form.nomor_faktur}
                                        onChange={(e) => handleChange('nomor_faktur', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="010.000-XX.XXXXXXXX"
                                        required
                                    />
                                </FormField>
                                <FormField label="Tanggal Faktur">
                                    <input
                                        type="date"
                                        value={form.tax_date}
                                        onChange={(e) => handleChange('tax_date', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </FormField>
                                <FormField label="NPWP Penjual" required>
                                    <input
                                        type="text"
                                        value={form.npwp}
                                        onChange={(e) => handleChange('npwp', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="00.000.000.0-000.000"
                                        required
                                    />
                                </FormField>
                                <FormField label="Nama Penjual" required>
                                    <input
                                        type="text"
                                        value={form.nama_penjual}
                                        onChange={(e) => handleChange('nama_penjual', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        required
                                    />
                                </FormField>
                                <FormField label="Jenis Pajak">
                                    <select
                                        value={form.tax_type}
                                        onChange={(e) => handleChange('tax_type', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="PPN">PPN</option>
                                        <option value="PPH21">PPh 21</option>
                                        <option value="PPH23">PPh 23</option>
                                        <option value="PPH4">PPh 4(2)</option>
                                    </select>
                                </FormField>
                                <FormField label="Tarif (%)">
                                    <input
                                        type="number"
                                        value={form.rate}
                                        onChange={(e) => handleChange('rate', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                    />
                                </FormField>
                                <FormField label="DPP (Dasar Pengenaan Pajak)" required>
                                    <input
                                        type="number"
                                        value={form.base_amount}
                                        onChange={(e) => handleChange('base_amount', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="0"
                                        required
                                    />
                                </FormField>
                                <FormField label="Keterangan">
                                    <input
                                        type="text"
                                        value={form.description}
                                        onChange={(e) => handleChange('description', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="Keterangan tambahan..."
                                    />
                                </FormField>
                            </div>

                            {/* Tax Calculation Preview */}
                            <div className="flex items-center gap-3 pt-2">
                                <Button variant="outline" onClick={handleCalculate} loading={calculating}>
                                    Hitung Pajak
                                </Button>
                                {calcResult && (
                                    <div className="flex items-center gap-4 text-sm bg-gray-50 rounded-lg px-4 py-2">
                                        <span className="text-gray-500">DPP: <strong className="text-gray-900">{fmt(calcResult.base_amount)}</strong></span>
                                        <span className="text-gray-400">|</span>
                                        <span className="text-gray-500">PPN ({calcResult.rate}%): <strong className="text-emerald-600">{fmt(calcResult.tax_amount)}</strong></span>
                                        <span className="text-gray-400">|</span>
                                        <span className="text-gray-500">Total: <strong className="text-indigo-600">{fmt(calcResult.total_amount || (parseFloat(calcResult.base_amount) + parseFloat(calcResult.tax_amount)))}</strong></span>
                                    </div>
                                )}
                            </div>

                            <div className="flex gap-3 pt-2 border-t">
                                <Button type="submit" loading={submitting}>
                                    Simpan Faktur Pajak
                                </Button>
                                <Button variant="outline" type="button" onClick={() => { setShowForm(false); setCalcResult(null); }}>
                                    Batal
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}

                {/* Tax List */}
                <Card title="Daftar Faktur Pajak" subtitle="Semua faktur pajak yang tercatat">
                    <DataTable
                        columns={columns}
                        data={taxes}
                        loading={loading}
                        emptyMessage="Belum ada faktur pajak tercatat"
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
