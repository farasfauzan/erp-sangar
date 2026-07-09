import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function GoodsReceipt() {
    const [receipts, setReceipts] = useState([]);
    const [pos, setPos] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [message, setMessage] = useState('');

    const [form, setForm] = useState({
        purchase_order_id: '',
        receipt_number: 'GR-' + Math.floor(Math.random() * 100000),
        receipt_date: new Date().toISOString().split('T')[0],
        delivery_note_number: '',
        receiver_name: '',
        notes: '',
    });

    useEffect(() => {
        Promise.all([
            axios.get('/api/goods-receipts'),
            axios.get('/api/pos'),
        ]).then(([grRes, poRes]) => {
            setReceipts(grRes.data);
            setPos(poRes.data);
            setLoading(false);
        });
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            const res = await axios.post('/api/goods-receipts', form);
            setMessage(res.data.message);
            setShowForm(false);
            // Refresh list
            const grRes = await axios.get('/api/goods-receipts');
            setReceipts(grRes.data);
            setForm({
                ...form,
                receipt_number: 'GR-' + Math.floor(Math.random() * 100000),
                delivery_note_number: '',
                receiver_name: '',
                notes: '',
                purchase_order_id: '',
            });
        } catch (err) {
            setMessage(err.response?.data?.message || 'Gagal mencatat penerimaan.');
        }
    };

    const selectedPo = pos.find(p => p.id === parseInt(form.purchase_order_id));

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Penerimaan Barang</h2>}
        >
            <Head title="Penerimaan Barang" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                    {message && (
                        <div className="p-4 bg-green-100 text-green-700 rounded shadow">
                            {message}
                        </div>
                    )}

                    {/* Form Penerimaan */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-bold">Catat Penerimaan Barang Baru</h3>
                                <button
                                    onClick={() => setShowForm(!showForm)}
                                    className="bg-emerald-600 text-white px-4 py-2 rounded shadow hover:bg-emerald-700"
                                >
                                    {showForm ? 'Tutup Form' : '+ Terima Barang'}
                                </button>
                            </div>

                            {showForm && (
                                <form onSubmit={handleSubmit} className="space-y-4 mt-4 border-t pt-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Pilih PO (Purchase Order)</label>
                                            <select
                                                required
                                                value={form.purchase_order_id}
                                                onChange={e => setForm({...form, purchase_order_id: e.target.value})}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <option value="">-- Pilih PO --</option>
                                                {pos.map(po => (
                                                    <option key={po.id} value={po.id}>
                                                        {po.po_number} — {po.supplier_name} (Rp {Number(po.total_amount).toLocaleString('id-ID')})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">No. Penerimaan</label>
                                            <input type="text" required value={form.receipt_number} onChange={e => setForm({...form, receipt_number: e.target.value})} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tanggal Terima</label>
                                            <input type="date" required value={form.receipt_date} onChange={e => setForm({...form, receipt_date: e.target.value})} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">No. Surat Jalan</label>
                                            <input type="text" value={form.delivery_note_number} onChange={e => setForm({...form, delivery_note_number: e.target.value})} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="SJ-xxx" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Nama Penerima (di Lapangan)</label>
                                            <input type="text" required value={form.receiver_name} onChange={e => setForm({...form, receiver_name: e.target.value})} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" />
                                        </div>
                                    </div>

                                    {selectedPo && (
                                        <div className="bg-blue-50 p-3 rounded text-sm">
                                            <strong>Detail PO:</strong> {selectedPo.po_number} | Supplier: {selectedPo.supplier_name} | Items: {selectedPo.items?.length || 0} barang | Total: Rp {Number(selectedPo.total_amount).toLocaleString('id-ID')}
                                        </div>
                                    )}

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Catatan</label>
                                        <textarea value={form.notes} onChange={e => setForm({...form, notes: e.target.value})} rows="2" className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Kondisi barang, catatan khusus..." />
                                    </div>

                                    <button type="submit" className="bg-indigo-600 text-white px-6 py-2 rounded shadow hover:bg-indigo-700">
                                        Simpan Penerimaan
                                    </button>
                                </form>
                            )}
                        </div>
                    </div>

                    {/* Tabel Riwayat Penerimaan */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-bold mb-4">Riwayat Penerimaan Barang</h3>
                            {loading ? (
                                <p>Memuat data...</p>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Penerimaan</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. PO</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Proyek</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Surat Jalan</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penerima</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {receipts.length === 0 ? (
                                            <tr><td colSpan="6" className="px-6 py-4 text-center text-sm text-gray-500">Belum ada data penerimaan.</td></tr>
                                        ) : (
                                            receipts.map((gr, idx) => (
                                                <tr key={idx}>
                                                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{gr.receipt_number}</td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{gr.purchase_order?.po_number ?? '-'}</td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{gr.purchase_order?.project?.project_name ?? '-'}</td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{gr.delivery_note_number || '-'}</td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{gr.receiver_name}</td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{gr.receipt_date}</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
