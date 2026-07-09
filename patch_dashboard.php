<?php

$dashboardFile = __DIR__ . '/resources/js/Pages/Dashboard.jsx';

$dashboardCode = <<<'JSX'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';

export default function Dashboard({ auth }) {
    const [file, setFile] = useState(null);
    const [projectId, setProjectId] = useState(1);
    const [message, setMessage] = useState('');

    const handleFileChange = (e) => {
        setFile(e.target.files[0]);
    };

    const handleUpload = async (e) => {
        e.preventDefault();
        if (!file) {
            setMessage('Silakan pilih file Excel (.xlsx) terlebih dahulu.');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('project_id', projectId);
        formData.append('overwrite', 1);

        setMessage('Sedang mengunggah dan memproses data RAB...');

        try {
            const response = await axios.post('/api/rab/import', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            });
            setMessage(response.data.message);
        } catch (error) {
            setMessage('Gagal mengimpor data RAB. Periksa format kolom.');
            console.error(error);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard Proyek
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg mb-6">
                        <div className="p-6 text-gray-900">
                            Selamat datang, {auth.user.name}! Anda login sebagai Role ID: {auth.user.role_id}.
                        </div>
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h3 className="text-lg font-bold mb-4">Upload Master RAB Proyek</h3>
                            <p className="text-sm text-gray-500 mb-4">Unggah file Excel (RAB) untuk mencatat budget item proyek.</p>
                            
                            <form onSubmit={handleUpload} className="space-y-4">
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

                                <button 
                                    type="submit"
                                    className="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    Upload & Proses RAB
                                </button>
                            </form>

                            {message && (
                                <div className="mt-4 p-4 rounded-md bg-blue-50 text-blue-700 text-sm">
                                    {message}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
JSX;

file_put_contents($dashboardFile, $dashboardCode);
echo "Updated Dashboard.jsx\n";
