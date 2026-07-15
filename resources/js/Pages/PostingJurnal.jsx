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

export default function PostingJurnal() {
    const toast = useToast();
    const [entries, setEntries] = useState([]);
    const [accounts, setAccounts] = useState([]);
    const [trialBalance, setTrialBalance] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [showTrialBalance, setShowTrialBalance] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [form, setForm] = useState({
        transaction_date: new Date().toISOString().split('T')[0],
        description: '',
        reference: '',
        lines: [
            { account_id: '', debit: '', credit: '', description: '' },
            { account_id: '', debit: '', credit: '', description: '' },
        ],
    });

    const fetchEntries = useCallback(async () => {
        setLoading(true);
        try {
            const res = await axios.get('/api/general-ledger');
            setEntries(Array.isArray(res.data) ? res.data : res.data?.data || []);
        } catch (err) {
            toast.error('Gagal memuat jurnal: ' + (err.response?.data?.message || err.message));
        } finally {
            setLoading(false);
        }
    }, [toast]);

    const fetchAccounts = useCallback(async () => {
        try {
            const res = await axios.get('/api/chart-of-accounts');
            setAccounts(Array.isArray(res.data) ? res.data : res.data?.data || []);
        } catch (err) {
            toast.error('Gagal memuat akun: ' + (err.response?.data?.message || err.message));
        }
    }, [toast]);

    const fetchTrialBalance = useCallback(async () => {
        try {
            const res = await axios.get('/api/general-ledger/trial-balance');
            setTrialBalance(Array.isArray(res.data) ? res.data : res.data?.data || []);
        } catch (err) {
            toast.error('Gagal memuat neraca saldo: ' + (err.response?.data?.message || err.message));
        }
    }, [toast]);

    useEffect(() => {
        fetchEntries();
        fetchAccounts();
    }, [fetchEntries, fetchAccounts]);

    const handleChange = (field, value) => {
        setForm((prev) => ({ ...prev, [field]: value }));
    };

    const handleLineChange = (index, field, value) => {
        setForm((prev) => {
            const lines = [...prev.lines];
            lines[index] = { ...lines[index], [field]: value };
            return { ...prev, lines };
        });
    };

    const addLine = () => {
        setForm((prev) => ({
            ...prev,
            lines: [...prev.lines, { account_id: '', debit: '', credit: '', description: '' }],
        }));
    };

    const removeLine = (index) => {
        if (form.lines.length <= 2) {
            toast.error('Minimal harus ada 2 baris jurnal (debit dan kredit)');
            return;
        }
        setForm((prev) => ({
            ...prev,
            lines: prev.lines.filter((_, i) => i !== index),
        }));
    };

    const totalDebit = form.lines.reduce((sum, line) => sum + parseFloat(line.debit || 0), 0);
    const totalCredit = form.lines.reduce((sum, line) => sum + parseFloat(line.credit || 0), 0);
    const isBalanced = Math.abs(totalDebit - totalCredit) < 0.01 && totalDebit > 0;

    const handleSubmit = async (e) => {
        e.preventDefault();

        // Validate double-entry
        if (!isBalanced) {
            toast.error(`Jurnal tidak balance! Debit: ${fmt(totalDebit)} ≠ Kredit: ${fmt(totalCredit)}`);
            return;
        }

        // Validate all lines have accounts
        const emptyAccount = form.lines.find((l) => !l.account_id);
        if (emptyAccount) {
            toast.error('Semua baris harus memiliki akun');
            return;
        }

        // Validate at least one debit and one credit
        const hasDebit = form.lines.some((l) => parseFloat(l.debit || 0) > 0);
        const hasCredit = form.lines.some((l) => parseFloat(l.credit || 0) > 0);
        if (!hasDebit || !hasCredit) {
            toast.error('Jurnal harus memiliki minimal satu baris debit dan satu baris kredit');
            return;
        }

        setSubmitting(true);
        try {
            await axios.post('/api/general-ledger', {
                transaction_date: form.transaction_date,
                description: form.description,
                reference: form.reference,
                entries: form.lines
                    .filter((l) => l.account_id)
                    .map((l) => ({
                        account_id: parseInt(l.account_id),
                        debit: parseFloat(l.debit || 0),
                        credit: parseFloat(l.credit || 0),
                        description: l.description || form.description,
                    })),
            });
            toast.success('Jurnal berhasil diposting');
            setShowForm(false);
            setForm({
                transaction_date: new Date().toISOString().split('T')[0],
                description: '',
                reference: '',
                lines: [
                    { account_id: '', debit: '', credit: '', description: '' },
                    { account_id: '', debit: '', credit: '', description: '' },
                ],
            });
            fetchEntries();
        } catch (err) {
            toast.error('Gagal posting jurnal: ' + (err.response?.data?.message || err.message));
        } finally {
            setSubmitting(false);
        }
    };

    const entryColumns = [
        { key: 'transaction_date', label: 'Tanggal', sortable: true },
        { key: 'account_code', label: 'Kode Akun', render: (v, row) => v || row.account?.code || '—' },
        { key: 'account_name', label: 'Nama Akun', render: (v, row) => v || row.account?.name || '—' },
        { key: 'description', label: 'Keterangan', render: (v) => v || '—' },
        { key: 'reference', label: 'Referensi', render: (v) => v || '—' },
        { key: 'debit', label: 'Debit', className: 'text-right', headerClassName: 'text-right', render: (v) => v > 0 ? fmt(v) : '—' },
        { key: 'credit', label: 'Kredit', className: 'text-right', headerClassName: 'text-right', render: (v) => v > 0 ? fmt(v) : '—' },
    ];

    const trialBalanceColumns = [
        { key: 'account_code', label: 'Kode Akun', render: (v, row) => v || row.account?.code || '—' },
        { key: 'account_name', label: 'Nama Akun', render: (v, row) => v || row.account?.name || '—' },
        { key: 'total_debit', label: 'Total Debit', className: 'text-right', headerClassName: 'text-right', render: (v) => fmt(v) },
        { key: 'total_credit', label: 'Total Kredit', className: 'text-right', headerClassName: 'text-right', render: (v) => fmt(v) },
        { key: 'balance', label: 'Saldo', className: 'text-right', headerClassName: 'text-right', render: (v, row) => {
            const bal = parseFloat(v ?? (parseFloat(row.total_debit || 0) - parseFloat(row.total_credit || 0)));
            return <span className={bal >= 0 ? 'text-emerald-600' : 'text-red-600'}>{fmt(bal)}</span>;
        }},
    ];

    const accountOptions = accounts.map((acc) => ({
        value: acc.id,
        label: `${acc.code} - ${acc.name}`,
    }));

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Posting Jurnal</h2>}>
            <Head title="Posting Jurnal" />
            <div className="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title="Posting Jurnal Umum"
                    subtitle="Catat jurnal dengan sistem double-entry (debit = kredit)"
                    actions={
                        <div className="flex gap-3">
                            <Button variant="outline" onClick={() => { setShowTrialBalance(!showTrialBalance); if (!showTrialBalance) fetchTrialBalance(); }}>
                                {showTrialBalance ? 'Tutup Neraca' : 'Neraca Saldo'}
                            </Button>
                            <Button onClick={() => setShowForm(!showForm)}>
                                {showForm ? 'Tutup Form' : '+ Posting Jurnal Baru'}
                            </Button>
                        </div>
                    }
                />

                {/* Trial Balance */}
                {showTrialBalance && (
                    <Card title="Neraca Saldo (Trial Balance)" subtitle="Ringkasan debit dan kredit per akun">
                        <DataTable
                            columns={trialBalanceColumns}
                            data={trialBalance}
                            emptyMessage="Belum ada data neraca saldo"
                        />
                        {trialBalance.length > 0 && (
                            <div className="mt-4 flex justify-end gap-6 border-t pt-4">
                                <div className="text-right">
                                    <p className="text-sm text-gray-500">Total Debit</p>
                                    <p className="text-lg font-bold text-gray-900">
                                        {fmt(trialBalance.reduce((sum, r) => sum + parseFloat(r.total_debit || 0), 0))}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm text-gray-500">Total Kredit</p>
                                    <p className="text-lg font-bold text-gray-900">
                                        {fmt(trialBalance.reduce((sum, r) => sum + parseFloat(r.total_credit || 0), 0))}
                                    </p>
                                </div>
                            </div>
                        )}
                    </Card>
                )}

                {/* Journal Entry Form */}
                {showForm && (
                    <Card title="Form Posting Jurnal" subtitle="Isi data jurnal dengan double-entry booking">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <FormField label="Tanggal Transaksi" required>
                                    <input
                                        type="date"
                                        value={form.transaction_date}
                                        onChange={(e) => handleChange('transaction_date', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        required
                                    />
                                </FormField>
                                <FormField label="Keterangan" required>
                                    <input
                                        type="text"
                                        value={form.description}
                                        onChange={(e) => handleChange('description', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="Deskripsi jurnal..."
                                        required
                                    />
                                </FormField>
                                <FormField label="Referensi">
                                    <input
                                        type="text"
                                        value={form.reference}
                                        onChange={(e) => handleChange('reference', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="No. dokumen referensi..."
                                    />
                                </FormField>
                            </div>

                            {/* Journal Lines */}
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <h4 className="text-sm font-semibold text-gray-700">Detail Jurnal</h4>
                                    <Button variant="outline" size="sm" type="button" onClick={addLine}>
                                        + Tambah Baris
                                    </Button>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Akun</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Kredit</th>
                                                <th className="px-3 py-2 w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {form.lines.map((line, index) => (
                                                <tr key={index} className="border-t">
                                                    <td className="px-3 py-2">
                                                        <select
                                                            value={line.account_id}
                                                            onChange={(e) => handleLineChange(index, 'account_id', e.target.value)}
                                                            className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                            required
                                                        >
                                                            <option value="">Pilih Akun...</option>
                                                            {accountOptions.map((opt) => (
                                                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                            ))}
                                                        </select>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="text"
                                                            value={line.description}
                                                            onChange={(e) => handleLineChange(index, 'description', e.target.value)}
                                                            className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                            placeholder="Keterangan baris..."
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="number"
                                                            value={line.debit}
                                                            onChange={(e) => handleLineChange(index, 'debit', e.target.value)}
                                                            className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm text-right"
                                                            placeholder="0"
                                                            min="0"
                                                            step="0.01"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="number"
                                                            value={line.credit}
                                                            onChange={(e) => handleLineChange(index, 'credit', e.target.value)}
                                                            className="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm text-right"
                                                            placeholder="0"
                                                            min="0"
                                                            step="0.01"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2 text-center">
                                                        <button
                                                            type="button"
                                                            onClick={() => removeLine(index)}
                                                            className="text-red-400 hover:text-red-600"
                                                            title="Hapus baris"
                                                        >
                                                            <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot>
                                            <tr className="border-t-2 bg-gray-50 font-semibold">
                                                <td colSpan={2} className="px-3 py-2 text-right text-sm">Total:</td>
                                                <td className="px-3 py-2 text-right text-sm">{fmt(totalDebit)}</td>
                                                <td className="px-3 py-2 text-right text-sm">{fmt(totalCredit)}</td>
                                                <td></td>
                                            </tr>
                                            <tr className={isBalanced ? 'bg-emerald-50' : 'bg-red-50'}>
                                                <td colSpan={5} className="px-3 py-2 text-center text-sm font-medium">
                                                    {isBalanced ? (
                                                        <span className="text-emerald-700">Jurnal seimbang — Selisih: {fmt(0)}</span>
                                                    ) : (
                                                        <span className="text-red-700">Jurnal tidak seimbang — Selisih: {fmt(Math.abs(totalDebit - totalCredit))}</span>
                                                    )}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <div className="flex gap-3 pt-2 border-t">
                                <Button type="submit" loading={submitting} disabled={!isBalanced}>
                                    Posting Jurnal
                                </Button>
                                <Button variant="outline" type="button" onClick={() => setShowForm(false)}>
                                    Batal
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}

                {/* General Ledger Entries */}
                <Card title="Buku Besar / General Ledger" subtitle="Semua jurnal yang telah diposting">
                    <DataTable
                        columns={entryColumns}
                        data={entries}
                        loading={loading}
                        emptyMessage="Belum ada jurnal diposting. Klik '+ Posting Jurnal Baru' untuk memulai."
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
