import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useApi } from '@/hooks/useApi';
import { useToast } from '@/Components/ui/Toast';
import ConfirmModal from '@/Components/ui/ConfirmModal';

const money = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;

export default function InvoiceVerification() {
    const roleName = usePage().props.auth?.user?.role?.role_name || '';
    const canVerify = roleName === 'ADMIN' || roleName === 'ENGINEER';
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selected, setSelected] = useState(null);
    const api = useApi();
    const toast = useToast();

    const load = async () => {
        setLoading(true);
        try {
            const response = await api.get('/api/invoices', {}, { silent: true });
            const list = response?.data || response || [];
            setInvoices(list.filter((invoice) => invoice.status === 'PENDING_ENGINEER'));
        } catch (error) {
            toast.error(error.response?.data?.message || 'Gagal memuat tagihan.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { load(); }, []);

    const verify = async () => {
        const invoice = selected;
        setSelected(null);
        try {
            await api.put(`/api/invoices/${invoice.id}/engineer-verify`);
            await load();
        } catch {
            // Error toast is supplied by useApi.
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Verifikasi Tagihan</h2>}>
            <Head title="Verifikasi Tagihan" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-slate-100 p-6">
                            <h3 className="text-lg font-bold text-slate-900">Invoice menunggu verifikasi Engineer</h3>
                            <p className="mt-1 text-sm text-slate-600">Pastikan pekerjaan atau material telah sesuai sebelum tagihan diteruskan ke Keuangan.</p>
                        </div>
                        {loading ? <p className="p-6 text-sm text-slate-500">Memuat data...</p> : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200">
                                    <thead className="bg-slate-50"><tr>{['Nomor Invoice', 'Referensi', 'Nilai', 'Status', 'Aksi'].map((label) => <th key={label} className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</th>)}</tr></thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {invoices.length === 0 ? <tr><td colSpan="5" className="px-5 py-10 text-center text-sm text-slate-500">Tidak ada invoice yang menunggu verifikasi.</td></tr> : invoices.map((invoice) => (
                                            <tr key={invoice.id}>
                                                <td className="px-5 py-4 text-sm font-semibold text-slate-900">{invoice.invoice_number}</td>
                                                <td className="px-5 py-4 text-sm text-slate-600">{invoice.invoiceable?.po_number || invoice.invoiceable?.spk_number || '-'}</td>
                                                <td className="px-5 py-4 text-sm text-slate-600">{money(invoice.amount)}</td>
                                                <td className="px-5 py-4 text-sm text-slate-600">{invoice.status}</td>
                                                <td className="px-5 py-4 text-sm">{canVerify && <button onClick={() => setSelected(invoice)} className="rounded bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">Verifikasi</button>}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </div>
            <ConfirmModal open={Boolean(selected)} onClose={() => setSelected(null)} onConfirm={verify} title="Verifikasi Invoice" message="Invoice ini sudah sesuai dan akan diteruskan ke Verifikator Keuangan?" confirmText="Verifikasi" />
        </AuthenticatedLayout>
    );
}
