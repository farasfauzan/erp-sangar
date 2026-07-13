import { Head } from '@inertiajs/react';

const formatCurrency = (amount) => `Rp ${Number(amount || 0).toLocaleString('id-ID')}`;
const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        year: 'numeric', month: 'long', day: 'numeric',
    });
};

export default function PurchaseOrderPrint({ po }) {
    if (!po) {
        return <div className="p-8 text-center text-gray-500">Data PO tidak ditemukan.</div>;
    }

    const items = po.items || [];
    const subtotal = items.reduce((sum, item) => sum + (item.total_price || (item.qty * item.unit_price)), 0);
    const ppn = subtotal * 0.11;
    const grandTotal = subtotal + ppn;

    return (
        <>
            <Head title={`Cetak PO - ${po.po_number}`} />

            <div className="print-page">
                {/* Print Button - hidden during print */}
                <div className="no-print fixed top-4 right-4 z-50 flex gap-2">
                    <button
                        onClick={() => window.print()}
                        className="px-5 py-2.5 bg-indigo-600 text-white rounded-lg shadow hover:bg-indigo-700 font-medium"
                    >
                        🖨️ Cetak
                    </button>
                    <button
                        onClick={() => window.history.back()}
                        className="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-lg shadow hover:bg-gray-300 font-medium"
                    >
                        ← Kembali
                    </button>
                </div>

                {/* Document */}
                <div className="max-w-[210mm] mx-auto bg-white p-8 print:p-4 print:shadow-none shadow-lg">
                    {/* Company Header */}
                    <div className="flex items-start justify-between border-b-2 border-gray-800 pb-4 mb-6">
                        <div className="flex items-center gap-4">
                            <div className="w-16 h-16 bg-gray-200 rounded flex items-center justify-center text-xs text-gray-500 font-bold">
                                LOGO
                            </div>
                            <div>
                                <h1 className="text-xl font-bold text-gray-900">PT. Nama Perusahaan</h1>
                                <p className="text-sm text-gray-600">Jl. Contoh No. 123, Kota, Provinsi</p>
                                <p className="text-sm text-gray-600">Telp: (021) 123-4567 | Email: info@perusahaan.com</p>
                            </div>
                        </div>
                        <div className="text-right">
                            <h2 className="text-lg font-bold text-gray-800 uppercase tracking-wide">Purchase Order</h2>
                        </div>
                    </div>

                    {/* PO Info */}
                    <div className="grid grid-cols-2 gap-6 mb-6">
                        <div>
                            <table className="text-sm">
                                <tbody>
                                    <tr>
                                        <td className="pr-3 py-1 text-gray-500 align-top">No. PO</td>
                                        <td className="py-1 font-semibold text-gray-900">: {po.po_number}</td>
                                    </tr>
                                    <tr>
                                        <td className="pr-3 py-1 text-gray-500 align-top">Tanggal</td>
                                        <td className="py-1 font-semibold text-gray-900">: {formatDate(po.date)}</td>
                                    </tr>
                                    <tr>
                                        <td className="pr-3 py-1 text-gray-500 align-top">Status</td>
                                        <td className="py-1 font-semibold text-gray-900">: {po.status?.replace(/_/g, ' ')}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <table className="text-sm">
                                <tbody>
                                    <tr>
                                        <td className="pr-3 py-1 text-gray-500 align-top">Supplier</td>
                                        <td className="py-1 font-semibold text-gray-900">: {po.supplier_name}</td>
                                    </tr>
                                    <tr>
                                        <td className="pr-3 py-1 text-gray-500 align-top">Proyek</td>
                                        <td className="py-1 font-semibold text-gray-900">: {po.project?.project_name || '—'}</td>
                                    </tr>
                                    <tr>
                                        <td className="pr-3 py-1 text-gray-500 align-top">Syarat Bayar</td>
                                        <td className="py-1 font-semibold text-gray-900">: {po.payment_terms || '—'}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Items Table */}
                    <table className="w-full border-collapse mb-6 text-sm">
                        <thead>
                            <tr className="bg-gray-800 text-white">
                                <th className="border border-gray-300 px-3 py-2 text-center w-10">No</th>
                                <th className="border border-gray-300 px-3 py-2 text-left">Nama Item</th>
                                <th className="border border-gray-300 px-3 py-2 text-center w-16">Qty</th>
                                <th className="border border-gray-300 px-3 py-2 text-center w-16">Satuan</th>
                                <th className="border border-gray-300 px-3 py-2 text-right w-28">Harga Satuan</th>
                                <th className="border border-gray-300 px-3 py-2 text-right w-32">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan="6" className="border border-gray-300 px-3 py-4 text-center text-gray-500">
                                        Tidak ada item
                                    </td>
                                </tr>
                            ) : (
                                items.map((item, index) => (
                                    <tr key={item.id || index} className={index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}>
                                        <td className="border border-gray-300 px-3 py-2 text-center">{index + 1}</td>
                                        <td className="border border-gray-300 px-3 py-2">{item.item_name || item.rab_budget?.description || '—'}</td>
                                        <td className="border border-gray-300 px-3 py-2 text-center">{Number(item.qty).toLocaleString('id-ID')}</td>
                                        <td className="border border-gray-300 px-3 py-2 text-center">{item.unit || item.rab_budget?.unit || '—'}</td>
                                        <td className="border border-gray-300 px-3 py-2 text-right">{formatCurrency(item.unit_price)}</td>
                                        <td className="border border-gray-300 px-3 py-2 text-right font-medium">{formatCurrency(item.total_price || (item.qty * item.unit_price))}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {/* Totals */}
                    <div className="flex justify-end mb-8">
                        <div className="w-72">
                            <div className="flex justify-between py-1.5 border-b border-gray-200 text-sm">
                                <span className="text-gray-600">Subtotal</span>
                                <span className="font-medium">{formatCurrency(subtotal)}</span>
                            </div>
                            <div className="flex justify-between py-1.5 border-b border-gray-200 text-sm">
                                <span className="text-gray-600">PPN 11%</span>
                                <span className="font-medium">{formatCurrency(ppn)}</span>
                            </div>
                            <div className="flex justify-between py-2.5 border-b-2 border-gray-800 text-base font-bold">
                                <span>Grand Total</span>
                                <span className="text-indigo-700">{formatCurrency(grandTotal)}</span>
                            </div>
                        </div>
                    </div>

                    {/* Notes */}
                    {po.notes && (
                        <div className="mb-8 text-sm">
                            <p className="text-gray-500 font-medium">Catatan:</p>
                            <p className="text-gray-700">{po.notes}</p>
                        </div>
                    )}

                    {/* Approval Signatures */}
                    <div className="grid grid-cols-3 gap-8 mt-12 pt-4 border-t border-gray-300">
                        <div className="text-center">
                            <p className="text-sm font-medium text-gray-600 mb-1">Disiapkan oleh</p>
                            <div className="h-20 border-b border-gray-400 mx-4 mb-2" />
                            <p className="text-sm text-gray-500">(___________________)</p>
                            <p className="text-xs text-gray-400 mt-1">Nama & Tanda Tangan</p>
                        </div>
                        <div className="text-center">
                            <p className="text-sm font-medium text-gray-600 mb-1">Disetujui oleh</p>
                            <div className="h-20 border-b border-gray-400 mx-4 mb-2" />
                            <p className="text-sm text-gray-500">(___________________)</p>
                            <p className="text-xs text-gray-400 mt-1">Nama & Tanda Tangan</p>
                        </div>
                        <div className="text-center">
                            <p className="text-sm font-medium text-gray-600 mb-1">Diterima oleh</p>
                            <div className="h-20 border-b border-gray-400 mx-4 mb-2" />
                            <p className="text-sm text-gray-500">(___________________)</p>
                            <p className="text-xs text-gray-400 mt-1">Nama & Tanda Tangan</p>
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="mt-8 pt-4 border-t border-gray-200 text-xs text-gray-400 text-center">
                        Dicetak pada {new Date().toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                    </div>
                </div>
            </div>

            <style>{`
                @media print {
                    @page {
                        size: A4;
                        margin: 15mm;
                    }
                    body {
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .no-print {
                        display: none !important;
                    }
                    .print-page {
                        padding: 0;
                        margin: 0;
                    }
                }
            `}</style>
        </>
    );
}
