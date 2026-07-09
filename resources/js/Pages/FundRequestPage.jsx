import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';

const money = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
const requestNumber = () => `PD-${new Date().toISOString().slice(0, 10).replaceAll('-', '')}-${Date.now().toString().slice(-4)}`;

export default function FundRequestPage() {
    const [projects, setProjects] = useState([]);
    const [funds, setFunds] = useState([]);
    const [loading, setLoading] = useState(true);
    const [form, setForm] = useState({ project_id: '', request_number: requestNumber(), amount: '', description: '' });

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        setLoading(true);
        const [projectRes, fundRes] = await Promise.all([
            axios.get('/api/projects'),
            axios.get('/api/fund-requests'),
        ]);
        setProjects(projectRes.data);
        setFunds(fundRes.data);
        setForm((current) => ({ ...current, project_id: current.project_id || projectRes.data[0]?.id || '' }));
        setLoading(false);
    };

    const updateForm = (field, value) => setForm((current) => ({ ...current, [field]: value }));

    const submit = async (e) => {
        e.preventDefault();
        try {
            await axios.post('/api/fund-requests', form);
            setForm({ project_id: projects[0]?.id || '', request_number: requestNumber(), amount: '', description: '' });
            await fetchData();
            alert('Permohonan dana dikirim untuk approval.');
        } catch (err) {
            alert(err.response?.data?.message || 'Gagal membuat permohonan dana.');
        }
    };

    const submitLpj = async (fund) => {
        const lpj_notes = prompt('Catatan LPJ:', fund.lpj_notes || 'LPJ sudah lengkap.');
        if (lpj_notes === null) return;
        try {
            await axios.put(`/api/fund-requests/${fund.id}/lpj`, { lpj_notes });
            await fetchData();
        } catch (err) {
            alert(err.response?.data?.message || 'Gagal mengirim LPJ.');
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">LPJ & Permohonan Dana</h2>}>
            <Head title="LPJ & Permohonan Dana" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="mb-4 text-lg font-bold">Buat Permohonan Dana</h3>
                        <div className="grid gap-4 md:grid-cols-2">
                            <label className="text-sm font-medium text-gray-700">
                                Proyek
                                <select value={form.project_id} onChange={(e) => updateForm('project_id', e.target.value)} className="mt-1 w-full rounded border-gray-300" required>
                                    <option value="">Pilih proyek</option>
                                    {projects.map((project) => <option key={project.id} value={project.id}>{project.project_name}</option>)}
                                </select>
                            </label>
                            <label className="text-sm font-medium text-gray-700">
                                No Permohonan
                                <input value={form.request_number} onChange={(e) => updateForm('request_number', e.target.value)} className="mt-1 w-full rounded border-gray-300" required />
                            </label>
                            <label className="text-sm font-medium text-gray-700">
                                Nilai
                                <input type="number" min="0" value={form.amount} onChange={(e) => updateForm('amount', e.target.value)} className="mt-1 w-full rounded border-gray-300" required />
                            </label>
                            <label className="text-sm font-medium text-gray-700">
                                Keterangan
                                <input value={form.description} onChange={(e) => updateForm('description', e.target.value)} className="mt-1 w-full rounded border-gray-300" placeholder="Operasional proyek / kas kecil" />
                            </label>
                        </div>
                        <button className="mt-4 rounded bg-indigo-600 px-4 py-2 text-white shadow hover:bg-indigo-700">Kirim Approval</button>
                    </form>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="mb-4 text-lg font-bold">Daftar Permohonan Dana & LPJ</h3>
                            {loading ? <p>Memuat...</p> : (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nomor</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Proyek</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nilai</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {funds.length ? funds.map((fund) => (
                                            <tr key={fund.id}>
                                                <td className="px-4 py-3 text-sm font-semibold text-gray-900">{fund.request_number}</td>
                                                <td className="px-4 py-3 text-sm text-gray-600">{fund.project?.project_name ?? 'N/A'}</td>
                                                <td className="px-4 py-3 text-sm font-semibold text-gray-900">{money(fund.amount)}</td>
                                                <td className="px-4 py-3 text-sm text-gray-600">{fund.status}</td>
                                                <td className="px-4 py-3 text-sm">
                                                    {fund.status === 'PAID' ? (
                                                        <button onClick={() => submitLpj(fund)} className="rounded bg-emerald-600 px-3 py-1 text-sm text-white shadow hover:bg-emerald-700">Kirim LPJ</button>
                                                    ) : '-'}
                                                </td>
                                            </tr>
                                        )) : <tr><td colSpan="5" className="px-4 py-4 text-center text-sm text-gray-500">Belum ada permohonan dana.</td></tr>}
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