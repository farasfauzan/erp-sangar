import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function InvoiceAdmin() {
    const [invoices, setInvoices] = useState([]);
    const [pos, setPos] = useState([]);
    const [spks, setSpks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [message, setMessage] = useState('');

    const [form, setForm] = useState({
        invoiceable_type: 'App\\Models\\PurchaseOrder',
        invoiceable_id: '',
        invoice_number: 'INV-' + Math.floor(Math.random() * 100000),
        invoice_date: new Date().toISOString().split('T')[0],
        due_date: '',
    });

    useEffect(() => {
        Promise.all([
            axios.get('/api/invoices'),
            axios.get('/api/pos'),
            axios.get('/api/spks'),
        ]).then(([invRes, poRes, spkRes]) => {
            setInvoices(invRes.data);
            // Hanya PO yang statusnya RECEIVED (atau disederhanakan tampilkan semua untuk testing)
            setPos(poRes.data); 
            setSpks(spkRes.data);
            setLoading(false);
        });
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            const res = await axios.post('/api/invoices', form);
            setMessage(res.data.message);
            setShowForm(false);
            const invRes = await axios.get('/api/invoices');
            setInvoices(invRes.data);
            setForm({
                ...form,
                invoice_number: 'INV-' + Math.floor(Math.random() * 100000),
                invoiceable_id: '',
                due_date: '',
            });
        } catch (err) {
            setMessage(err.response?.data?.message || 'Gagal membuat invoice.');
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Drafting Tagihan (Invoicing)</h2>}>
            <Head title="Invoice Admin" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                    {message && (
                        <div className="p-4 bg-green-100 text-green-700 rounded shadow">
                            {message}
                        </div>
                    )}

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-bold">Terbitkan Tagihan Baru</h3>
                                <button
                                    onClick={() => setShowForm(!showForm)}
                                    className="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700"
                                >
                                    {showForm ? 'Tutup Form' : '+ Buat Invoice'}
                                </button>
                            </div>

                            {showForm && (
                                <form onSubmit={handleSubmit} className="space-y-4 mt-4 border-t pt-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tipe Referensi</label>
                                            <select
                                                value={form.invoiceable_type}
                                                onChange={e => setForm({...form, invoiceable_type: e.target.value, invoiceable_id: ''})}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                            >
                                                <option value="App\Models\PurchaseOrder">Purchase Order (Material)</option>
                                                <option value="App\Models\Spk">Kontrak SPK (Subkon)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Pilih Dokumen Acuan</label>
                                            <select
                                                required
                                                value={form.invoiceable_id}
                                                onChange={e => setForm({...form, invoiceable_id: e.target.value})}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                            >
                                                <option value="">-- Pilih --</option>
                                                {form.invoiceable_type === 'App\\Models\\PurchaseOrder' ? 
                                                    pos.map(p => <option key={p.id} value={p.id}>{p.po_number} - Rp {Number(p.total_amount).toLocaleString('id-ID')}</option>)
                                                    :
                                                    spks.map(s => <option key={s.id} value={s.id}>{s.spk_number} - Rp {Number(s.total_amount).toLocaleString('id-ID')}</option>)
                                                }
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">No. Invoice (Faktur)</label>
                                            <input type="text" required value={form.invoice_number} onChange={e => setForm({...form, invoice_number: e.target.value})} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tanggal Invoice</label>
                                            <input type="date" required value={form.invoice_date} onChange={e => setForm({...form, invoice_date: e.target.value})} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" />
                                        </div>
                                    </div>
                                    <button type="submit" className="bg-green-600 text-white px-6 py-2 rounded shadow hover:bg-green-700">
                                        Terbitkan Invoice
                                    </button>
                                </form>
                            )}
                        </div>
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-bold mb-4">Daftar Tagihan (Menunggu Approval)</h3>
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Invoice</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe Tagihan</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nilai (Rp)</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {invoices.length === 0 ? (
                                        <tr><td colSpan="4" className="px-6 py-4 text-center text-gray-500">Belum ada tagihan.</td></tr>
                                    ) : (
                                        invoices.map((inv) => (
                                            <tr key={inv.id}>
                                                <td className="px-6 py-4 text-sm font-medium text-gray-900">{inv.invoice_number}</td>
                                                <td className="px-6 py-4 text-sm text-gray-500">
                                                    {inv.invoiceable_type.includes('PurchaseOrder') ? 'Material (PO)' : 'Subkon (SPK)'}
                                                </td>
                                                <td className="px-6 py-4 text-sm font-bold text-gray-900">
                                                    Rp {Number(inv.amount).toLocaleString('id-ID')}
                                                </td>
                                                <td className="px-6 py-4 text-sm">
                                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                        inv.status === 'PENDING_APPROVAL' ? 'bg-yellow-100 text-yellow-800' : 
                                                        (inv.status === 'PAID' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800')
                                                    }`}>
                                                        {inv.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
