import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';

const money = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;

export default function RabControl() {
    const [projects, setProjects] = useState([]);
    const [projectId, setProjectId] = useState('');
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [message, setMessage] = useState('');

    const loadProjects = async () => {
        const response = await axios.get('/api/projects');
        const list = response.data?.data ?? response.data ?? [];
        setProjects(list);
        setProjectId((current) => current || String(list[0]?.id || ''));
    };

    const loadItems = async (id) => {
        if (!id) {
            setItems([]);
            return;
        }

        setLoading(true);
        try {
            const response = await axios.get('/api/rab', { params: { project_id: id, per_page: 500 } });
            const payload = response.data?.data;
            setItems(payload?.data ?? payload ?? []);
        } catch (error) {
            setItems([]);
            setMessage(error.response?.data?.message || 'Gagal memuat item RAB.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadProjects().catch(() => {
            setMessage('Gagal memuat daftar proyek.');
            setLoading(false);
        });
    }, []);

    useEffect(() => {
        loadItems(projectId);
    }, [projectId]);

    const counts = useMemo(() => items.reduce((result, item) => {
        const status = item.status || 'DRAFT';
        result[status] = (result[status] || 0) + 1;
        return result;
    }, {}), [items]);

    const runAction = async (endpoint, confirmation) => {
        if (!projectId || !confirm(confirmation)) return;

        setSubmitting(true);
        setMessage('');
        try {
            const response = await axios.post(endpoint, { project_id: projectId });
            setMessage(response.data?.message || 'Status RAB berhasil diperbarui.');
            await loadItems(projectId);
        } catch (error) {
            setMessage(error.response?.data?.message || 'Aksi RAB gagal dilakukan.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Kontrol RAB</h2>}>
            <Head title="Kontrol RAB" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <label className="block min-w-64 text-sm font-medium text-gray-700">
                                Proyek
                                <select value={projectId} onChange={(event) => setProjectId(event.target.value)} className="mt-1 block w-full rounded border-gray-300">
                                    {projects.map((project) => <option key={project.id} value={project.id}>{project.project_name}</option>)}
                                </select>
                            </label>
                            <div className="flex flex-wrap gap-2">
                                <button disabled={submitting || !counts.DRAFT} onClick={() => runAction('/rab/submit-for-approval', 'Ajukan seluruh item RAB draft untuk approval?')} className="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50">Ajukan Approval ({counts.DRAFT || 0})</button>
                                <button disabled={submitting || !counts.PENDING} onClick={() => runAction('/rab/approve', 'Setujui seluruh RAB yang menunggu approval? Setelah disetujui, item terkunci.')} className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50">Setujui ({counts.PENDING || 0})</button>
                                <button disabled={submitting || !counts.PENDING} onClick={() => runAction('/rab/reject', 'Tolak seluruh RAB yang menunggu approval?')} className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50">Tolak ({counts.PENDING || 0})</button>
                            </div>
                        </div>

                        <p className="mt-4 text-sm text-gray-600">RAB harus berstatus <strong>APPROVED</strong> sebelum dapat digunakan untuk membuat PO. Item yang sudah disetujui dikunci agar nilai pengadaan tetap terjaga.</p>
                        {message && <div className="mt-4 rounded border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">{message}</div>}
                    </section>

                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="mb-4 text-lg font-bold">Item RAB</h3>
                            {loading ? <p>Memuat data...</p> : (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Kode</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Uraian</th>
                                            <th className="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Nilai</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {items.length ? items.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-4 py-3 text-sm text-gray-600">{item.code_item || '-'}</td>
                                                <td className="px-4 py-3 text-sm font-medium text-gray-900">{item.description}</td>
                                                <td className="px-4 py-3 text-right text-sm text-gray-600">{money(item.total_price)}</td>
                                                <td className="px-4 py-3"><Status status={item.status || 'DRAFT'} /></td>
                                            </tr>
                                        )) : <tr><td colSpan="4" className="px-4 py-5 text-center text-sm text-gray-500">Belum ada item RAB pada proyek ini.</td></tr>}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Status({ status }) {
    const colors = {
        DRAFT: 'bg-gray-100 text-gray-700',
        PENDING: 'bg-amber-100 text-amber-800',
        APPROVED: 'bg-emerald-100 text-emerald-800',
        REJECTED: 'bg-red-100 text-red-800',
    };

    return <span className={`rounded-full px-2 py-1 text-xs font-semibold ${colors[status] || 'bg-gray-100 text-gray-700'}`}>{status}</span>;
}
