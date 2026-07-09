import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function ApprovalDashboard() {
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchInvoices();
    }, []);

    const fetchInvoices = () => {
        axios.get('/api/invoices').then((res) => {
            setInvoices(res.data);
            setLoading(false);
        });
    };

    const handleApprove = async (id) => {
        if (!confirm('Apakah Anda yakin menyetujui tagihan ini?')) return;
        try {
            await axios.put(`/api/invoices/${id}/manager-approve`);
            alert('Tagihan berhasil disetujui.');
            fetchInvoices();
        } catch (err) {
            alert(err.response?.data?.message || 'Gagal menyetujui tagihan.');
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard Approval Manajer</h2>}>
            <Head title="Approval Dashboard" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-bold mb-4">Daftar Tagihan Menunggu Persetujuan</h3>
                            {loading ? <p>Memuat...</p> : (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Invoice</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nilai Tagihan</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {invoices.filter(i => i.status === 'PENDING_APPROVAL').length === 0 ? (
                                            <tr><td colSpan="5" className="px-6 py-4 text-center text-gray-500">Tidak ada tagihan yang butuh persetujuan.</td></tr>
                                        ) : (
                                            invoices.filter(i => i.status === 'PENDING_APPROVAL').map(inv => (
                                                <tr key={inv.id}>
                                                    <td className="px-6 py-4 text-sm font-medium">{inv.invoice_number}</td>
                                                    <td className="px-6 py-4 text-sm">{inv.invoiceable_type.includes('PurchaseOrder') ? 'Material' : 'Subkon'}</td>
                                                    <td className="px-6 py-4 text-sm font-bold text-red-600">Rp {Number(inv.amount).toLocaleString('id-ID')}</td>
                                                    <td className="px-6 py-4 text-sm"><span className="bg-yellow-100 text-yellow-800 px-2 rounded-full text-xs font-semibold">{inv.status}</span></td>
                                                    <td className="px-6 py-4 text-sm">
                                                        <button onClick={() => handleApprove(inv.id)} className="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded shadow text-sm">
                                                            Setujui Tagihan
                                                        </button>
                                                    </td>
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
