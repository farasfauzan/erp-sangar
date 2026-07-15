import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Card, StatusBadge } from '@/Components/ui';

const fmt = (n) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
}).format(n ?? 0);

const fmtDate = (d) => d
    ? new Date(d).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' })
    : '—';

const tableHead = 'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600';
const tableCell = 'px-4 py-3 text-sm text-slate-700';

function Pagination({ links = [] }) {
    if (links.length <= 3) return null;

    return (
        <div className="flex flex-wrap justify-end gap-1 border-t border-slate-200 px-4 py-3">
            {links.map((link, index) => {
                const label = link.label.replace('&laquo;', '«').replace('&raquo;', '»');
                if (link.label === '...') return <span key={index} className="px-2 py-1 text-sm text-slate-400">...</span>;

                return link.url ? (
                    <button
                        key={index}
                        type="button"
                        onClick={() => router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                        className={`rounded border px-2.5 py-1 text-xs font-semibold transition-colors ${
                            link.active
                                ? 'border-blue-700 bg-blue-700 text-white'
                                : 'border-slate-300 bg-white text-slate-600 hover:border-blue-400 hover:text-blue-800'
                        }`}
                    >
                        {label}
                    </button>
                ) : (
                    <span key={index} className="rounded border border-slate-200 px-2.5 py-1 text-xs text-slate-400">
                        {label}
                    </span>
                );
            })}
        </div>
    );
}

export default function RabStorage({ projects, selectedProject, importJobs, budgets, totals, storage }) {
    const [projectId, setProjectId] = useState(selectedProject?.id || (projects[0]?.id ?? ''));
    const [searchTerm, setSearchTerm] = useState('');
    const [activeTab, setActiveTab] = useState('files');

    const handleProjectChange = (event) => {
        const id = event.target.value;
        setProjectId(id);
        router.get('/rab-storage', { project_id: id }, { preserveState: true, preserveScroll: true });
    };

    const filteredBudgets = useMemo(() => {
        const rows = budgets?.data || [];
        if (!searchTerm) return rows;
        const term = searchTerm.toLowerCase();
        return rows.filter((item) =>
            (item.description || '').toLowerCase().includes(term)
            || (item.code_item || '').toLowerCase().includes(term)
        );
    }, [budgets, searchTerm]);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-xl font-semibold tracking-tight text-slate-900">Penyimpanan RAB</h1>
                    <p className="mt-1 text-sm text-slate-500">Riwayat file import dan data anggaran proyek</p>
                </div>
            }
        >
            <Head title="Penyimpanan RAB" />

            <div className="mx-auto flex max-w-7xl flex-col gap-5 px-4 py-5 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                    <span className="font-semibold text-slate-800">Lokasi penyimpanan</span>
                    <span>{storage.description}</span>
                    <span className="text-slate-300">/</span>
                    <span>Data tabel <code className="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">rab_budgets</code></span>
                </div>

                <Card>
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="min-w-[260px] flex-1">
                            <label className="app-label" htmlFor="rab-storage-project">Proyek</label>
                            <select id="rab-storage-project" value={projectId} onChange={handleProjectChange} className="app-field">
                                <option value="">Pilih proyek...</option>
                                {projects.map((project) => (
                                    <option key={project.id} value={project.id}>{project.project_name || `Project #${project.id}`}</option>
                                ))}
                            </select>
                        </div>
                        {selectedProject && (
                            <div className="pb-2 text-sm text-slate-500">
                                <span>{selectedProject.location || 'Lokasi belum diisi'}</span>
                                <span className="mx-2 text-slate-300">/</span>
                                <span className="capitalize">{selectedProject.status}</span>
                            </div>
                        )}
                    </div>
                </Card>

                {totals && (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div className="app-panel border-l-2 border-l-blue-700 p-4">
                            <p className="app-label">Total Item</p>
                            <p className="text-xl font-semibold text-slate-900">{totals.total_items}</p>
                        </div>
                        <div className="app-panel border-l-2 border-l-blue-700 p-4">
                            <p className="app-label">Total Anggaran</p>
                            <p className="text-xl font-semibold text-slate-900">{fmt(totals.total_budget)}</p>
                        </div>
                        <div className="app-panel border-l-2 border-l-blue-700 p-4">
                            <p className="app-label">Versi RAB</p>
                            <p className="text-xl font-semibold text-slate-900">{totals.versions.length ? totals.versions.join(', ') : '—'}</p>
                        </div>
                    </div>
                )}

                <div className="flex gap-6 border-b border-slate-300">
                    {[
                        { id: 'files', label: 'File Import' },
                        { id: 'data', label: 'Data RAB' },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setActiveTab(tab.id)}
                            className={`border-b-2 px-1 py-2.5 text-sm font-semibold transition-colors ${
                                activeTab === tab.id
                                    ? 'border-blue-700 text-blue-800'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {activeTab === 'files' && (
                    <Card title="File Import RAB" subtitle={`${importJobs.total} file`} className="overflow-hidden">
                        <div className="-mx-5 -my-4 overflow-x-auto">
                            {importJobs.data.length === 0 ? (
                                <p className="px-5 py-10 text-center text-sm text-slate-500">Belum ada file import.</p>
                            ) : (
                                <table className="min-w-full divide-y divide-slate-200">
                                    <thead className="bg-slate-50">
                                        <tr>{['Nama File', 'Proyek', 'Status', 'Baris', 'Tanggal', 'Aksi'].map((heading) => <th key={heading} className={tableHead}>{heading}</th>)}</tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 bg-white">
                                        {importJobs.data.map((job) => (
                                            <tr key={job.id} className="hover:bg-slate-50">
                                                <td className={`${tableCell} font-medium text-slate-900`}>
                                                    <div>{job.file_name}</div>
                                                    <div className="mt-0.5 text-xs font-normal uppercase text-slate-400">{job.file_type}</div>
                                                </td>
                                                <td className={tableCell}>{job.project_name}</td>
                                                <td className={tableCell}><StatusBadge status={job.status} /></td>
                                                <td className={`${tableCell} tabular-nums`}>{job.processed_rows || 0} / {job.total_rows || 0}</td>
                                                <td className={`${tableCell} whitespace-nowrap`}>{fmtDate(job.created_at)}</td>
                                                <td className={tableCell}>
                                                    {job.download_url
                                                        ? <a href={job.download_url} className="font-semibold text-blue-700 hover:text-blue-900">Unduh</a>
                                                        : <span className="text-xs text-slate-400">File tidak tersedia</span>}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                            <Pagination links={importJobs.links} />
                        </div>
                    </Card>
                )}

                {activeTab === 'data' && (
                    <Card
                        title="Data RAB"
                        subtitle={`${filteredBudgets.length} item pada halaman ini`}
                        actions={
                            <input
                                type="search"
                                placeholder="Cari kode atau uraian..."
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                className="app-field w-64"
                            />
                        }
                        className="overflow-hidden"
                    >
                        <div className="-mx-5 -my-4 overflow-x-auto">
                            {!budgets || budgets.data.length === 0 ? (
                                <p className="px-5 py-10 text-center text-sm text-slate-500">Belum ada data RAB untuk proyek ini.</p>
                            ) : (
                                <table className="min-w-full divide-y divide-slate-200">
                                    <thead className="bg-slate-50">
                                        <tr>{['#', 'Kode', 'Uraian', 'Volume', 'Satuan', 'Harga Satuan', 'Total', 'Kategori', 'Kategori Otomatis'].map((heading) => <th key={heading} className={tableHead}>{heading}</th>)}</tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 bg-white">
                                        {filteredBudgets.map((item, index) => (
                                            <tr key={item.id} className="hover:bg-slate-50">
                                                <td className={tableCell}>{index + 1}</td>
                                                <td className={`${tableCell} font-mono text-xs`}>{item.code_item || '—'}</td>
                                                <td className={`${tableCell} min-w-[280px] font-medium text-slate-900`}>{item.description}</td>
                                                <td className={`${tableCell} text-right tabular-nums`}>{item.volume || '—'}</td>
                                                <td className={tableCell}>{item.unit || '—'}</td>
                                                <td className={`${tableCell} text-right tabular-nums`}>{fmt(item.unit_price)}</td>
                                                <td className={`${tableCell} text-right font-semibold tabular-nums text-slate-900`}>{fmt(item.total_price)}</td>
                                                <td className={tableCell}>{item.category || '—'}</td>
                                                <td className={tableCell}>{item.ai_category || '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                            <Pagination links={budgets?.links} />
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
