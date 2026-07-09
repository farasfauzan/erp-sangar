import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';

const fmt = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);

export default function Dashboard({ auth }) {
    const [file, setFile] = useState(null);
    const [projectId, setProjectId] = useState(1);
    const [message, setMessage] = useState('');
    const [previewRows, setPreviewRows] = useState([]);
    const [step, setStep] = useState(1);
    const [quickImporting, setQuickImporting] = useState(false);
    const [rabData, setRabData] = useState([]);
    const [loadingRab, setLoadingRab] = useState(false);
    const [summary, setSummary] = useState(null);
    const [categoryFilter, setCategoryFilter] = useState('');
    const [categories, setCategories] = useState([]);

    const fetchRabData = async (pid) => {
        if (!pid) return;
        setLoadingRab(true);
        try {
            const params = { project_id: pid, per_page: 500 };
            if (categoryFilter) params.category = categoryFilter;
            const response = await axios.get('/api/rab', { params });
            const paginator = response.data.data;
            const rows = paginator?.data ?? paginator ?? [];
            setRabData(Array.isArray(rows) ? rows : []);
        } catch (error) {
            console.error('Failed to fetch RAB data', error);
            setRabData([]);
        } finally {
            setLoadingRab(false);
        }
    };

    const fetchSummary = async (pid) => {
        if (!pid) return;
        try {
            const response = await axios.get('/api/rab/summary', { params: { project_id: pid } });
            setSummary(response.data.data);
            // Extract unique categories for filter
            const cats = Object.keys(response.data.data?.by_category ?? {});
            setCategories(cats);
        } catch { setSummary(null); }
    };

    useEffect(() => {
        fetchRabData(projectId);
        fetchSummary(projectId);
    }, [projectId, categoryFilter]);

    const previewHeaders = ['No', 'Uraian Pekerjaan', 'Volume', 'Satuan', 'Harga Satuan (Rp)', 'Jumlah (Rp)'];
    const toPreviewRows = (rows = []) => rows.map(row => Array.isArray(row) ? row : [
        row.no ?? '',
        row.uraian ?? '',
        row.volume ?? '',
        row.satuan ?? '',
        row.harga_satuan ?? '',
        row.jumlah ?? '',
    ]);

    const handleFileChange = async (e) => {
        const selectedFile = e.target.files[0];
        setFile(selectedFile);
        if (selectedFile) {
            setMessage('Membaca preview file...');
            const formData = new FormData();
            formData.append('file', selectedFile);
            try {
                const response = await axios.post('/rab/preview', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                setPreviewRows(toPreviewRows(response.data.data?.rows ?? response.data.rows ?? []));
                setStep(2);
                setMessage('Preview siap. Klik Import RAB jika data benar.');
            } catch (error) {
                setMessage('Gagal preview. ' + (error.response?.data?.message || ''));
            }
        }
    };

    const handleQuickImport = async () => {
        if (!file) { setMessage('Pilih file terlebih dahulu.'); return; }
        setQuickImporting(true);
        setMessage('Import otomatis berjalan...');
        const formData = new FormData();
        formData.append('file', file);
        formData.append('project_id', projectId);
        formData.append('overwrite', 1);
        try {
            const response = await axios.post('/rab/auto-import', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            setMessage(response.data.message);
            setFile(null);
            setStep(1);
            fetchRabData(projectId);
            fetchSummary(projectId);
        } catch (error) {
            setMessage(`${error.response?.data?.message || 'Gagal import.'} ${error.response?.data?.error || ''}`);
        } finally { setQuickImporting(false); }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Smart RAB Importer</h2>}
        >
            <Head title="Dashboard" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                    {/* Welcome */}
                    <div className="bg-white shadow-sm sm:rounded-lg p-6">
                        <div className="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            Selamat datang, <strong>{auth.user.name}</strong>!
                            Role: <strong className="text-indigo-600">{auth.user.role?.role_name || auth.user.role_id}</strong>.
                        </div>

                        {/* Upload Section */}
                        {step === 1 && (
                            <div className="space-y-4">
                                <h3 className="text-lg font-bold">Upload RAB Excel</h3>
                                <div className="flex items-end gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Project ID</label>
                                        <input type="number" value={projectId} onChange={(e) => setProjectId(e.target.value)}
                                            className="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    </div>
                                    <div className="flex-1">
                                        <label className="block text-sm font-medium text-gray-700">File Excel</label>
                                        <input type="file" accept=".xlsx,.xls,.csv" onChange={handleFileChange}
                                            className="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                                    </div>
                                    <button onClick={handleQuickImport} disabled={!file || quickImporting}
                                        className="px-6 py-2 bg-green-600 text-white rounded-md text-sm font-medium shadow-sm hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                        {quickImporting ? '⏳ Import...' : '⚡ Import Otomatis'}
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Preview Section */}
                        {step === 2 && (
                            <div className="space-y-4">
                                <h3 className="text-lg font-bold">Preview RAB</h3>
                                <div className="overflow-x-auto border border-gray-200 rounded max-h-64 overflow-y-auto">
                                    <table className="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead className="bg-gray-50 sticky top-0">
                                            <tr>
                                                <th className="px-2 py-2 text-left text-gray-500 font-semibold w-8">#</th>
                                                {previewHeaders.map(h => (
                                                    <th key={h} className="px-3 py-2 text-left text-gray-500 font-semibold border-l border-gray-100">{h}</th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {previewRows.slice(0, 20).map((row, i) => (
                                                <tr key={i} className="hover:bg-indigo-50">
                                                    <td className="px-2 py-2 text-gray-400 font-mono w-8">{i + 1}</td>
                                                    {row.map((cell, j) => (
                                                        <td key={j} className="px-3 py-2 whitespace-nowrap text-gray-700 border-l border-gray-100">
                                                            {String(cell ?? '').substring(0, 30)}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="flex justify-end gap-3">
                                    <button onClick={() => { setStep(1); setFile(null); setPreviewRows([]); }}
                                        className="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-100">Batal</button>
                                    <button onClick={handleQuickImport} disabled={quickImporting}
                                        className="px-6 py-2 bg-green-600 text-white rounded-md text-sm font-medium shadow-sm hover:bg-green-700 disabled:opacity-50">
                                        {quickImporting ? 'Mengimport...' : 'Import RAB'}
                                    </button>
                                </div>
                            </div>
                        )}

                        {message && (
                            <div className="mt-4 p-4 rounded-md bg-blue-50 text-blue-700 text-sm border border-blue-200">{message}</div>
                        )}
                    </div>

                    {/* Summary Cards */}
                    {summary && (
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="bg-white shadow-sm rounded-lg p-5">
                                <div className="text-sm text-gray-500">Total Budget</div>
                                <div className="text-2xl font-bold text-indigo-700 mt-1">{fmt(summary.total_budget)}</div>
                            </div>
                            <div className="bg-white shadow-sm rounded-lg p-5">
                                <div className="text-sm text-gray-500">Jumlah Item</div>
                                <div className="text-2xl font-bold text-gray-800 mt-1">{summary.total_items} item</div>
                            </div>
                            <div className="bg-white shadow-sm rounded-lg p-5">
                                <div className="text-sm text-gray-500">Kategori</div>
                                <div className="text-2xl font-bold text-gray-800 mt-1">{categories.length} kategori</div>
                            </div>
                        </div>
                    )}

                    {/* Category Filter */}
                    {categories.length > 0 && (
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <label className="text-sm font-medium text-gray-700 mr-3">Filter Kategori:</label>
                            <select value={categoryFilter} onChange={(e) => setCategoryFilter(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">Semua</option>
                                {categories.map(c => <option key={c} value={c}>{c}</option>)}
                            </select>
                        </div>
                    )}

                    {/* RAB Data Table */}
                    <div className="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-lg font-bold mb-4">Data RAB — Project {projectId}</h3>
                        {loadingRab ? (
                            <p className="text-sm text-gray-500">Memuat data...</p>
                        ) : rabData.length === 0 ? (
                            <p className="text-sm text-gray-500">Belum ada data RAB. Upload Excel di atas.</p>
                        ) : (
                            <div className="overflow-x-auto border border-gray-200 rounded">
                                <table className="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-2 py-2 text-left text-gray-500 font-semibold w-8">#</th>
                                            <th className="px-3 py-2 text-left text-gray-500 font-semibold">Kode</th>
                                            <th className="px-3 py-2 text-left text-gray-500 font-semibold">Uraian</th>
                                            <th className="px-3 py-2 text-center text-gray-500 font-semibold">Volume</th>
                                            <th className="px-3 py-2 text-center text-gray-500 font-semibold">Satuan</th>
                                            <th className="px-3 py-2 text-right text-gray-500 font-semibold">Harga Satuan</th>
                                            <th className="px-3 py-2 text-right text-gray-500 font-semibold">Total</th>
                                            <th className="px-3 py-2 text-center text-gray-500 font-semibold">Kategori</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {rabData.map((item, i) => (
                                            <tr key={item.id} className="hover:bg-indigo-50 transition-colors">
                                                <td className="px-2 py-2 text-gray-400 font-mono w-8">{i + 1}</td>
                                                <td className="px-3 py-2 whitespace-nowrap text-gray-700 font-mono text-xs">{item.code_item}</td>
                                                <td className="px-3 py-2 text-gray-700">{item.description}</td>
                                                <td className="px-3 py-2 text-center text-gray-700">{item.volume}</td>
                                                <td className="px-3 py-2 text-center text-gray-700">{item.unit}</td>
                                                <td className="px-3 py-2 text-right text-gray-700 font-mono">{fmt(item.unit_price)}</td>
                                                <td className="px-3 py-2 text-right text-gray-800 font-semibold font-mono">{fmt(item.total_price)}</td>
                                                <td className="px-3 py-2 text-center">
                                                    <span className="inline-block px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-xs">{item.category || '-'}</span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}