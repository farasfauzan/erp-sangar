<?php

$pagesDir = __DIR__ . '/resources/js/Pages/';
$webRoutesFile = __DIR__ . '/routes/web.php';

$poPageCode = <<<'JSX'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function PurchaseOrder() {
    const [pos, setPos] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        axios.get('/api/pos').then(res => {
            setPos(res.data);
            setLoading(false);
        }).catch(err => {
            console.error(err);
            setLoading(false);
        });
    }, []);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Draft PO (Purchase Order)
                </h2>
            }
        >
            <Head title="Purchase Orders" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-lg font-bold">Daftar Purchase Order</h3>
                                <button className="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700">
                                    + Buat PO Baru
                                </button>
                            </div>
                            
                            {loading ? (
                                <p>Memuat data...</p>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. PO</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proyek</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {pos.length === 0 ? (
                                            <tr><td colSpan="5" className="px-6 py-4 text-center text-sm text-gray-500">Belum ada data PO.</td></tr>
                                        ) : (
                                            pos.map((po, idx) => (
                                                <tr key={idx}>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{po.po_number}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{po.project?.project_name ?? 'N/A'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{po.supplier_name}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Rp {Number(po.total_amount).toLocaleString('id-ID')}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                            {po.status}
                                                        </span>
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
JSX;

file_put_contents($pagesDir . 'PurchaseOrder.jsx', $poPageCode);
echo "Created PurchaseOrder.jsx\n";

// Update routes/web.php
$webRoutesContent = file_get_contents($webRoutesFile);
if (!str_contains($webRoutesContent, "Route::get('/po'")) {
    $webRoutesContent .= "\nuse Inertia\\Inertia;\nRoute::get('/po', function () {\n    return Inertia::render('PurchaseOrder');\n})->middleware(['auth', 'verified'])->name('po');\n";
    file_put_contents($webRoutesFile, $webRoutesContent);
    echo "Updated routes/web.php\n";
}

// Update AuthenticatedLayout.jsx sidebar links to point to /po instead of /dashboard for PO menus
$layoutFile = __DIR__ . '/resources/js/Layouts/AuthenticatedLayout.jsx';
$layoutContent = file_get_contents($layoutFile);
$layoutContent = str_replace(
    "{ name: 'Draft PO', route: 'dashboard', icon: '📝' }",
    "{ name: 'Draft PO', route: 'po', icon: '📝' }",
    $layoutContent
);
$layoutContent = str_replace(
    "{ name: 'Purchase Orders', route: 'dashboard', icon: '🛒' }",
    "{ name: 'Purchase Orders', route: 'po', icon: '🛒' }",
    $layoutContent
);
file_put_contents($layoutFile, $layoutContent);
echo "Updated Sidebar Links in AuthenticatedLayout.jsx\n";
