import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Card, Button, LoadingSpinner } from '@/Components/ui';

const fmt = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);

export default function RabImport({ projectId, projects, currentProject, onProjectChange, onAddProject, onEditProject, view = 'import', onImportComplete }) {
    const [file, setFile] = useState(null);
    const [step, setStep] = useState(1);
    const [message, setMessage] = useState('');
    const [rabData, setRabData] = useState([]);
    const [loadingRab, setLoadingRab] = useState(false);
    const [summary, setSummary] = useState(null);
    const [categoryFilter, setCategoryFilter] = useState('');
    const [categories, setCategories] = useState([]);
    const [searchTerm, setSearchTerm] = useState('');

    const [importJob, setImportJob] = useState(null);
    const [importStatus, setImportStatus] = useState('');
    const [importErrors, setImportErrors] = useState([]);
    const [importDiff, setImportDiff] = useState(null);
    const [importStartTime, setImportStartTime] = useState(null);

    const intervalRef = useRef(null);

    const fetchRabData = async (pid) => {
        if (!pid) return;
        setLoadingRab(true);
        try {
            const params = { project_id: pid, per_page: -1 };
            const response = await axios.get('/api/rab', { params });
            const payload = response.data;
            const result = payload?.data;
            const rows = Array.isArray(result) ? result : (Array.isArray(result?.data) ? result.data : (Array.isArray(payload) ? payload : []));
            setRabData(rows);
        } catch {
            setRabData([]);
        } finally {
            setLoadingRab(false);
        }
    };

    const fetchSummary = async (pid) => {
        if (!pid) return;
        try {
            const response = await axios.get('/api/rab/summary', { params: { project_id: pid } });
            const data = response.data?.data ?? response.data;
            setSummary(data);
            const byCategory = data?.by_category ?? [];
            setCategories(Array.isArray(byCategory) ? byCategory.map((c) => c.category_name).filter(Boolean) : []);
        } catch {
            setSummary(null);
        }
    };

    useEffect(() => {
        fetchRabData(projectId);
        fetchSummary(projectId);
    }, [projectId]);

    useEffect(() => {
        return () => { if (intervalRef.current) clearInterval(intervalRef.current); };
    }, []);

    const startPolling = (jobId) => {
        if (intervalRef.current) clearInterval(intervalRef.current);
        intervalRef.current = setInterval(async () => {
            try {
                const response = await axios.get(`/rab/import-job/${jobId}`);
                const job = response.data?.data ?? response.data;
                setImportJob(job);
                setImportStatus(job.status);
                setImportErrors(job.errors || []);
                setImportDiff(job.diff);

                if (job.status === 'IMPORTING') {
                    setImportStartTime((prev) => prev || Date.now());
                } else if (job.status !== 'PENDING' && job.status !== 'PROCESSING') {
                    setImportStartTime(null);
                }

                if (job.status === 'VALIDATED' || job.status === 'FAILED' || job.status === 'COMPLETED') {
                    clearInterval(intervalRef.current);
                }

                if (job.status === 'COMPLETED') {
                    setMessage('Import data RAB berhasil diselesaikan! Stok inventory telah diperbarui.');
                    setFile(null);
                    setStep(1);
                    fetchRabData(projectId);
                    fetchSummary(projectId);
                    if (onImportComplete) onImportComplete();
                }
            } catch {
                clearInterval(intervalRef.current);
            }
        }, 1500);
    };

    const handleFileChange = async (e) => {
        const selectedFile = e.target.files[0];
        setFile(selectedFile);
        if (selectedFile) {
            setStep(2);
            setMessage('Mengupload file dan memvalidasi data...');
            setImportStatus('PENDING');
            setImportErrors([]);
            setImportDiff(null);
            setImportStartTime(null);

            const formData = new FormData();
            formData.append('file', selectedFile);
            formData.append('project_id', projectId);

            try {
                const response = await axios.post('/rab/import-async', formData, {
                    headers: { 'Content-Type': 'multipart/form-data', Accept: 'application/json' },
                });
                const job = response.data?.data ?? response.data;
                setImportJob(job);
                setImportStatus(job.status);
                startPolling(job.id);
            } catch (error) {
                setImportStatus('FAILED');
                const validationErrors = error.response?.data?.errors;
                if (validationErrors && typeof validationErrors === 'object') {
                    setImportErrors(Object.values(validationErrors).flat());
                } else {
                    setImportErrors([error.response?.data?.message || 'Gagal mengupload file untuk validasi.']);
                }
                setMessage('Gagal mengupload file.');
            }
        }
    };

    const handleConfirmImport = async () => {
        if (!importJob) return;
        try {
            setMessage('Memulai eksekusi import di background...');
            setImportStatus('IMPORTING');
            setImportStartTime(Date.now());
            const response = await axios.post(`/rab/import-job/${importJob.id}/confirm`);
            const job = response.data?.data ?? response.data;
            setImportJob(job);
            setImportStatus(job.status);
            startPolling(job.id);
        } catch (error) {
            setImportStatus('FAILED');
            setImportStartTime(null);
            setImportErrors([error.response?.data?.message || 'Gagal memulai eksekusi import.']);
            setMessage('Gagal melakukan konfirmasi import.');
        }
    };

    const handleResetImport = () => {
        setStep(1);
        setFile(null);
        setImportJob(null);
        setImportStatus('');
        setImportErrors([]);
        setImportDiff(null);
    };

    // Import progress
    const totalRows = importJob?.total_rows || 0;
    const processedRows = importJob?.processed_rows || 0;
    const percent = totalRows > 0 ? Math.min(100, Math.round((processedRows / totalRows) * 100)) : 0;

    let remainingTimeText = '';
    if (importStatus === 'IMPORTING' && importStartTime && processedRows > 0 && totalRows > 0) {
        const elapsedMs = Date.now() - importStartTime;
        if (elapsedMs > 500) {
            const rowsPerMs = processedRows / elapsedMs;
            const remainingRows = totalRows - processedRows;
            if (remainingRows <= 0) {
                remainingTimeText = 'Menyelesaikan proses import...';
            } else {
                const remainingSeconds = Math.ceil(remainingRows / rowsPerMs / 1000);
                if (remainingSeconds < 60) {
                    remainingTimeText = `Estimasi sisa waktu: ~${remainingSeconds} detik`;
                } else {
                    remainingTimeText = `Estimasi sisa waktu: ~${Math.floor(remainingSeconds / 60)} menit ${remainingSeconds % 60} detik`;
                }
            }
        }
    } else if (importStatus === 'IMPORTING') {
        remainingTimeText = 'Menghitung sisa waktu...';
    }

    // Filtered data for RAB table
    const filteredData = rabData.filter((item) => {
        if (searchTerm) {
            const term = searchTerm.toLowerCase();
            return (item.description || '').toLowerCase().includes(term) ||
                (item.code_item || '').toLowerCase().includes(term);
        }
        if (categoryFilter && item.category !== categoryFilter) return false;
        return true;
    });

    // ─── RENDER: IMPORT VIEW ───
    if (view === 'import') {
        return (
            <Card title="Import RAB" subtitle="Upload file Excel (.xlsx, .xls) atau CSV (.csv) berisi rencana anggaran biaya">
                <div className="flex items-end gap-4 flex-wrap">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-semibold uppercase tracking-wider text-gray-500">Proyek</label>
                        <div className="flex gap-1 items-center">
                            <select
                                value={projectId}
                                onChange={(e) => onProjectChange(Number(e.target.value))}
                                className="rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm cursor-pointer min-w-[180px]"
                            >
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>{p.project_name || `Project #${p.id}`}</option>
                                ))}
                                {projects.length === 0 && <option value={1}>Project #1</option>}
                            </select>
                            <Button variant="outline" size="sm" onClick={onAddProject} title="Tambah Proyek Baru">➕</Button>
                            <Button variant="outline" size="sm" onClick={onEditProject} title="Edit Proyek Terpilih">✏️</Button>
                        </div>
                    </div>
                    <div className="flex flex-col gap-1 flex-1 min-w-[200px]">
                        <label className="text-xs font-semibold uppercase tracking-wider text-gray-500">File Excel / CSV</label>
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv,.txt"
                            onChange={handleFileChange}
                            className="rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100"
                        />
                    </div>
                </div>

                {message && (
                    <div className={`mt-4 p-3 rounded-lg text-sm font-medium border ${
                        message.includes('berhasil') || message.includes('success')
                            ? 'bg-green-50 border-green-200 text-green-800'
                            : message.includes('Gagal') || message.includes('Error')
                                ? 'bg-red-50 border-red-200 text-red-800'
                                : 'bg-amber-50 border-amber-200 text-amber-800'
                    }`}>
                        {message}
                    </div>
                )}

                {step === 2 && (
                    <div className="mt-5 p-4 border border-gray-200 rounded-lg bg-gray-50">
                        <h4 className="text-sm font-bold text-gray-900 font-serif mb-3">
                            Status Validasi File:{' '}
                            <span className={importStatus === 'FAILED' ? 'text-red-700' : importStatus === 'VALIDATED' ? 'text-green-700' : 'text-amber-600'}>
                                {importStatus}
                            </span>
                        </h4>

                        {(importStatus === 'PENDING' || importStatus === 'PROCESSING') && (
                            <LoadingSpinner size="sm" message="Sistem sedang membaca dan memvalidasi baris data file..." />
                        )}

                        {importStatus === 'FAILED' && (
                            <div className="text-sm text-red-800">
                                <p className="font-bold mb-2">⚠️ Validasi Gagal. Silakan perbaiki kesalahan berikut pada file Anda:</p>
                                <div className="max-h-48 overflow-y-auto bg-red-50 p-3 rounded-lg border border-red-200 font-mono text-xs leading-relaxed">
                                    {importErrors.map((err, i) => <div key={i} className="mb-1">• {err}</div>)}
                                </div>
                                <Button variant="outline" className="mt-4" onClick={handleResetImport}>Upload Ulang</Button>
                            </div>
                        )}

                        {importStatus === 'VALIDATED' && importDiff && (
                            <div>
                                <div className="p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800 mb-4">
                                    <p className="font-bold mb-1">✅ Validasi Berhasil! Seluruh data format sesuai.</p>
                                    <p>Total Baris Valid: <strong>{importJob.total_rows}</strong></p>
                                    <div className="grid grid-cols-3 gap-2 mt-3 p-2 bg-white rounded-lg border border-green-200 text-center">
                                        <div>
                                            <span className="block text-xs font-semibold uppercase tracking-wider text-gray-500">Item Baru</span>
                                            <strong className="text-lg text-green-700">+{importDiff.added_count}</strong>
                                        </div>
                                        <div>
                                            <span className="block text-xs font-semibold uppercase tracking-wider text-gray-500">Item Berubah</span>
                                            <strong className="text-lg text-amber-600">{importDiff.updated_count}</strong>
                                        </div>
                                        <div>
                                            <span className="block text-xs font-semibold uppercase tracking-wider text-gray-500">Item Dihapus</span>
                                            <strong className="text-lg text-red-700">-{importDiff.deleted_count}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button variant="outline" onClick={handleResetImport}>Batal</Button>
                                    <Button variant="primary" onClick={handleConfirmImport}>Konfirmasi & Import Sekarang</Button>
                                </div>
                            </div>
                        )}

                        {importStatus === 'IMPORTING' && (
                            <div className="text-sm text-gray-700">
                                <LoadingSpinner size="sm" message="Memasukkan data ke database..." />
                                <div className="mt-3">
                                    <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div className="h-full bg-red-700 transition-all duration-300" style={{ width: `${percent}%` }} />
                                    </div>
                                    <div className="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Memproses {processedRows} dari {totalRows} baris ({percent}%)</span>
                                        {remainingTimeText && <span className="font-semibold text-orange-700">{remainingTimeText}</span>}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </Card>
        );
    }

    // ─── RENDER: DATA RAB VIEW ───
    return (
        <div id="print-section">
            <Card
                title="Data RAB"
                subtitle={`${filteredData.length} item terdaftar`}
                actions={
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" onClick={() => window.print()}>🖨️ Cetak</Button>
                        <div className="relative">
                            <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                type="text"
                                placeholder="Cari item..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9 pr-3 py-1.5 w-40 rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                            />
                        </div>
                        {categories.length > 0 && (
                            <select
                                value={categoryFilter}
                                onChange={(e) => setCategoryFilter(e.target.value)}
                                className="rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm cursor-pointer"
                            >
                                <option value="">Semua Kategori</option>
                                {categories.map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        )}
                    </div>
                }
            >
                {loadingRab ? (
                    <LoadingSpinner message="Memuat data RAB..." />
                ) : rabData.length === 0 ? (
                    <div className="text-center py-8">
                        <p className="text-gray-500 italic font-serif">Belum ada data RAB.</p>
                        <p className="text-xs text-gray-400 italic mt-1">Upload file Excel di tab Import untuk memulai.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto -mx-6">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-gray-50 border-b-2 border-gray-200">
                                    {['#', 'Kode', 'Uraian Pekerjaan', 'Volume', 'Satuan', 'Harga Satuan', 'Total', 'Kategori'].map((label) => (
                                        <th key={label} className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 text-left">{label}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {filteredData.map((item, i) => (
                                    <tr key={item.id} className="hover:bg-amber-50 transition-colors">
                                        <td className="px-4 py-3 text-gray-500 text-xs">{i + 1}</td>
                                        <td className="px-4 py-3 text-gray-500 text-xs font-mono">{item.code_item || '—'}</td>
                                        <td className="px-4 py-3 text-gray-900 font-semibold">{item.description}</td>
                                        <td className="px-4 py-3 text-gray-700 text-center">{item.volume || '—'}</td>
                                        <td className="px-4 py-3 text-gray-700 text-center">{item.unit || '—'}</td>
                                        <td className="px-4 py-3 text-gray-700 text-right font-mono">{fmt(item.unit_price)}</td>
                                        <td className="px-4 py-3 text-gray-900 text-right font-bold font-mono">{fmt(item.total_price)}</td>
                                        <td className="px-4 py-3 text-center">
                                            {item.category ? (
                                                <span className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                                    {item.category}
                                                </span>
                                            ) : <span className="text-gray-400">—</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <style>{`
                @media print {
                    body * { visibility: hidden; }
                    #print-section, #print-section * { visibility: visible; }
                    #print-section { position: absolute; left: 0; top: 0; width: 100%; background: white !important; color: black !important; border: none !important; box-shadow: none !important; }
                    #print-section button, #print-section select, #print-section input { display: none !important; }
                    table { border-collapse: collapse !important; width: 100% !important; }
                    th, td { border: 1px solid #c4a878 !important; padding: 6px 10px !important; color: black !important; background: none !important; }
                }
            `}</style>
        </div>
    );
}
