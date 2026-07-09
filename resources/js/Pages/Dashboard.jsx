import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function Dashboard({ auth }) {
    const [file, setFile] = useState(null);
    const [projectId, setProjectId] = useState(1);
    const [message, setMessage] = useState('');
    const [previewRows, setPreviewRows] = useState([]);
    const [step, setStep] = useState(1); // 1: Upload, 2: Preview
    const [quickImporting, setQuickImporting] = useState(false);
    const [rabData, setRabData] = useState([]);
    const [loadingRab, setLoadingRab] = useState(false);

    const fetchRabData = async (pid) => {
        if (!pid) return;
        setLoadingRab(true);
        try {
            const response = await axios.get('/api/rab', {
                params: { project_id: pid, per_page: 100 } // adjust per_page as needed
            });
            setRabData(response.data.data || []);
        } catch (error) {
            console.error('Failed to fetch RAB data', error);
            setRabData([]);
        } finally {
            setLoadingRab(false);
        }
    };

    // Fetch RAB data on initial load and after import
    useEffect(() => {
        fetchRabData(projectId);
    }, [projectId]); // Note: this will also fetch when projectId changes via input

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
                setMessage('Preview siap. Kalau data sudah benar, klik Import RAB.');
            } catch (error) {
                setMessage('Gagal membaca preview Excel. ' + (error.response?.data?.message || ''));
            }
        }
    };

    const handleQuickImport = async () => {
        if (!file) {
            setMessage('Pilih file terlebih dahulu.');
            return;
        }
        setQuickImporting(true);
        setMessage('Import otomatis sedang berjalan... Mendeteksi header & mapping kolom...');

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
            fetchRabData(projectId); // refresh RAB table after import
        } catch (error) {
            const serverMsg = error.response?.data?.message || 'Gagal import otomatis.';
            const serverErr = error.response?.data?.error || '';
            setMessage(`${serverMsg} ${serverErr}`);
        } finally {
            setQuickImporting(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Smart RAB Importer
                </h2>
            }
        >
            <Head title="Smart Importer" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                Selamat datang, <strong>{auth.user.name}</strong>! Anda login sebagai peran: <strong className="text-indigo-600">{auth.user.role?.role_name || auth.user.role_id}</strong>.
                            </div>

                            {step === 1 && (
                                <div className="space-y-4">
                                    <h3 className="text-lg font-bold mb-4">Langkah 1: Upload File Excel</h3>
                                    <p className="text-sm text-gray-500 mb-4">Unggah file Excel (RAB) Anda. Format apa saja didukung.</p>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Pilih Proyek (ID)</label>
                                        <input
                                            type="number"
                                            value={projectId}
                                            onChange={(e) => setProjectId(e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:max-w-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">File Excel (.xlsx)</label>
                                        <input
                                            type="file"
                                            accept=".xlsx, .xls, .csv"
                                            onChange={handleFileChange}
                                            className="mt-1 block w-full text-sm text-slate-500
                                              file:mr-4 file:py-2 file:px-4
                                              file:rounded-full file:border-0
                                              file:text-sm file:font-semibold
                                              file:bg-indigo-50 file:text-indigo-700
                                              hover:file:bg-indigo-100"
                                        />
                                    </div>

                                    <div className="flex items-center gap-4 pt-2">
                                        <button
                                            onClick={handleQuickImport}
                                            disabled={!file || quickImporting}
                                            className="px-6 py-2 bg-green-600 text-white rounded-md text-sm font-medium shadow-sm hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {quickImporting ? '⏳ Mendeteksi & Import...' : '⚡ Import Otomatis (Deteksi Cerdas)'}
                                        </button>
                                        <span className="text-sm text-gray-500">atau pilih file untuk lihat preview dulu</span>
                                    </div>
                                </div>
                            )}

                            {step === 2 && (
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-lg font-bold">Langkah 2: Preview RAB</h3>
                                        <p className="text-sm text-gray-600">Sistem sudah mendeteksi header dan mapping kolom otomatis. Cek data di bawah, lalu klik <strong>Import RAB</strong>.</p>
                                    </div>

                                    <div className="overflow-x-auto border border-gray-200 rounded">
                                        <table className="min-w-full divide-y divide-gray-200 text-xs">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-2 py-2 text-left text-gray-500 font-semibold w-8">#</th>
                                                    {previewHeaders.map((header) => (
                                                        <th key={header} className="px-3 py-2 text-left text-gray-500 font-semibold border-l border-gray-100">
                                                            {header}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {previewRows.map((row, rowIndex) => (
                                                    <tr
                                                        key={rowIndex}
                                                        className="hover:bg-indigo-50 transition-colors"
                                                    >
                                                        <td className="px-2 py-2 text-gray-400 font-mono w-8">{rowIndex + 1}</td>
                                                        {row.map((cell, cellIndex) => (
                                                            <td key={cellIndex} className="px-3 py-2 whitespace-nowrap text-gray-700 border-l border-gray-100">
                                                                {String(cell ?? '').substring(0, 30) + (String(cell ?? '').length > 30 ? '...' : '')}
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    <div className="flex justify-end gap-3">
                                        <button
                                            onClick={() => { setStep(1); setFile(null); setPreviewRows([]); }}
                                            disabled={quickImporting}
                                            className="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-100 disabled:opacity-50"
                                        >
                                            Batal
                                        </button>
                                        <button
                                            onClick={handleQuickImport}
                                            disabled={quickImporting}
                                            className="px-6 py-2 bg-green-600 text-white rounded-md text-sm font-medium shadow-sm hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {quickImporting ? 'Mengimport...' : 'Import RAB'}
                                        </button>
                                    </div>
                                </div>
                            )}

                            {message && (
                                <div className="mt-6 p-4 rounded-md bg-blue-50 text-blue-700 text-sm border border-blue-200">
                                    {message}
                                </div>
                            )}

                            {/* RAB Data Table */}
                            <div className="mt-8">
                                <h3 className="text-lg font-bold mb-4">Data RAB (Project {projectId})</h3>
                                {loadingRab ? (
                                    <p className="text-sm text-gray-500">Memuat data RAB...</p>
                                ) : rabData.length === 0 ? (
                                    <p className="text-sm text-gray-500">Tidak ada data RAB. Import Excel terlebih dahulu.</p>
                                ) : (
                                    <div className="overflow-x-auto border border-gray-200 rounded">
                                        <table className="min-w-full divide-y divide-gray-200 text-xs">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-2 py-2 text-left text-gray-500 font-semibold w-8">#</th>
                                                    <th className="px-3 py-2 text-left text-gray-500 font-semibold">Kode</th>
                                                    <th className="px-3 py-2 text-left text-gray-500 font-semibold">Uraian</th>
                                                    <th className="px-3 py-2 text-left text-gray-500 font-semibold">Volume</th>
                                                    <th className="px-3 py-2 text-left text-gray-500 font-semibold">Satuan</th>
                                                    <th className="px-3 py-2 text-right text-gray-500 font-semibold">Harga Satuan</th>
                                                    <th className="px-3 py-2 text-right text-gray-500 font-semibold">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {rabData.map((item, index) => (
                                                    <tr key={item.id} className="hover:bg-indigo-50 transition-colors">
                                                        <td className="px-2 py-2 text-gray-400 font-mono w-8">{index + 1}</td>
                                                        <td className="px-3 py-2 whitespace-nowrap text-gray-700">{item.code_item}</td>
                                                        <td className="px-3 py-2 text-gray-700">{item.description}</td>
                                                        <td className="px-3 py-2 whitespace-nowrap text-gray-700">{item.volume}</td>
                                                        <td className="px-3 py-2 whitespace-nowrap text-gray-700">{item.unit}</td>
                                                        <td className="px-3 py-2 whitespace-nowrap text-gray-700 text-right">
                                                            {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(item.unit_price)}
                                                        </td>
                                                        <td className="px-3 py-2 whitespace-nowrap text-gray-700 text-right">
                                                            {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(item.total_price)}
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}