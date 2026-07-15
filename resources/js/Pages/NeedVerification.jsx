import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useApi } from '@/hooks/useApi';
import { useToast } from '@/Components/ui/Toast';
import ConfirmModal from '@/Components/ui/ConfirmModal';

export default function NeedVerification() {
    const roleName = usePage().props.auth?.user?.role?.role_name || '';
    const canRoute = roleName === 'ADMIN' || roleName === 'ENGINEER';
    const [pos, setPos] = useState([]);
    const [loading, setLoading] = useState(true);
    const [confirmState, setConfirmState] = useState({ open: false, po: null, target: null });
    const api = useApi();
    const toast = useToast();

    const load = async () => {
        setLoading(true);
        try {
            const response = await api.get('/api/pos', {}, { silent: true });
            const list = response?.data || response || [];
            setPos(list.filter((po) => po.po_level === 'PROJECT' && po.status === 'DRAFT'));
        } catch (error) {
            toast.error(error.response?.data?.message || 'Gagal memuat PO Proyek.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { load(); }, []);

    const routePo = async () => {
        const { po, target } = confirmState;
        setConfirmState({ open: false, po: null, target: null });
        try {
            await api.put(`/api/pos/${po.id}/route`, { routed_to: target });
            await load();
        } catch {
            // Error toast is supplied by useApi.
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Verifikasi Kebutuhan</h2>}>
            <Head title="Verifikasi Kebutuhan" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-slate-100 p-6">
                            <h3 className="text-lg font-bold text-slate-900">PO Proyek menunggu routing</h3>
                            <p className="mt-1 text-sm text-slate-600">Verifikasi kebutuhan proyek, lalu teruskan ke PO Supplier atau Kontrak SPK.</p>
                        </div>
                        {loading ? <p className="p-6 text-sm text-slate-500">Memuat data...</p> : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            {['Nomor PO', 'Proyek', 'Jumlah Item', 'Status', 'Aksi'].map((label) => <th key={label} className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</th>)}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {pos.length === 0 ? <tr><td colSpan="5" className="px-5 py-10 text-center text-sm text-slate-500">Tidak ada PO Proyek yang menunggu verifikasi.</td></tr> : pos.map((po) => (
                                            <tr key={po.id}>
                                                <td className="px-5 py-4 text-sm font-semibold text-slate-900">{po.po_number}</td>
                                                <td className="px-5 py-4 text-sm text-slate-600">{po.project?.project_name || '-'}</td>
                                                <td className="px-5 py-4 text-sm text-slate-600">{po.items?.length || 0} item</td>
                                                <td className="px-5 py-4 text-sm text-slate-600">{po.status}</td>
                                                <td className="px-5 py-4 text-sm">
                                                    {canRoute ? <>
                                                        <button onClick={() => setConfirmState({ open: true, po, target: 'PURCHASE_ORDER' })} className="mr-2 rounded bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">Ke PO Supplier</button>
                                                        <button onClick={() => setConfirmState({ open: true, po, target: 'SPK' })} className="rounded bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">Ke SPK</button>
                                                    </> : <span className="text-slate-500">Menunggu Engineer</span>}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </div>
            <ConfirmModal
                open={confirmState.open}
                onClose={() => setConfirmState({ open: false, po: null, target: null })}
                onConfirm={routePo}
                title="Teruskan PO Proyek"
                message={confirmState.target === 'SPK' ? 'Teruskan PO ini ke pembuatan Kontrak SPK?' : 'Teruskan PO ini ke pembuatan PO Supplier?'}
                confirmText="Teruskan"
            />
        </AuthenticatedLayout>
    );
}
