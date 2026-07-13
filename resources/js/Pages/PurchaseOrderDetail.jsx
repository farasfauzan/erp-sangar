import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { useToast } from '@/Components/ui/Toast';
import Card from '@/Components/ui/Card';
import StatusBadge from '@/Components/ui/StatusBadge';
import DataTable from '@/Components/ui/DataTable';
import Button from '@/Components/ui/Button';
import PageHeader from '@/Components/ui/PageHeader';
import LoadingSpinner from '@/Components/ui/LoadingSpinner';
import ConfirmModal from '@/Components/ui/ConfirmModal';

const STATUS_STEPS = ['DRAFT', 'PENDING_APPROVAL', 'APPROVED'];
const REJECTED_STATUSES = ['REJECTED'];

const statusColors = {
    DRAFT: 'bg-gray-400',
    PENDING_APPROVAL: 'bg-yellow-400',
    APPROVED: 'bg-emerald-500',
    REJECTED: 'bg-red-500',
};

function StatusTimeline({ currentStatus }) {
    const steps = currentStatus === 'REJECTED'
        ? [...STATUS_STEPS.slice(0, 2), 'REJECTED']
        : STATUS_STEPS;

    const currentIndex = steps.indexOf(currentStatus);
    const isRejected = currentStatus === 'REJECTED';

    return (
        <div className="flex items-center justify-between w-full max-w-2xl mx-auto py-4">
            {steps.map((step, index) => {
                const isCompleted = index < currentIndex || (isRejected && step === 'PENDING_APPROVAL');
                const isCurrent = index === currentIndex;
                const isRejectedStep = step === 'REJECTED';

                return (
                    <div key={step} className="flex flex-col items-center flex-1 relative">
                        {index < steps.length - 1 && (
                            <div
                                className={`absolute top-4 left-1/2 w-full h-0.5 ${
                                    isCompleted
                                        ? isRejectedStep ? 'bg-red-400' : 'bg-emerald-400'
                                        : 'bg-gray-200'
                                }`}
                                style={{ transform: 'translateX(0%)' }}
                            />
                        )}

                        <div
                            className={`relative z-10 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-colors ${
                                isCurrent
                                    ? isRejectedStep
                                        ? 'bg-red-500 text-white border-red-600'
                                        : 'bg-emerald-500 text-white border-emerald-600'
                                    : isCompleted
                                        ? isRejectedStep
                                            ? 'bg-red-400 text-white border-red-400'
                                            : 'bg-emerald-400 text-white border-emerald-400'
                                        : 'bg-white text-gray-400 border-gray-300'
                            }`}
                        >
                            {isCompleted || isCurrent ? (
                                isRejectedStep ? (
                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                    </svg>
                                ) : (
                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                    </svg>
                                )
                            ) : (
                                index + 1
                            )}
                        </div>

                        <span
                            className={`mt-2 text-xs font-medium text-center whitespace-nowrap ${
                                isCurrent
                                    ? isRejectedStep ? 'text-red-600' : 'text-emerald-600'
                                    : isCompleted
                                        ? 'text-gray-600'
                                        : 'text-gray-400'
                            }`}
                        >
                            {step.replace(/_/g, ' ')}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0)} ${sizes[i]}`;
}

function getFileIcon(fileType) {
    if (!fileType) return '📄';
    if (fileType.startsWith('image/')) return '🖼️';
    if (fileType === 'application/pdf') return '📕';
    if (fileType.includes('spreadsheet') || fileType.includes('excel') || fileType.endsWith('xlsx')) return '📊';
    return '📄';
}

function isImageFile(fileType) {
    return fileType && fileType.startsWith('image/');
}

function AttachmentsSection({ poId, canUpload }) {
    const [attachments, setAttachments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [previewImage, setPreviewImage] = useState(null);
    const fileInputRef = useRef(null);
    const toast = useToast();

    useEffect(() => {
        fetchAttachments();
    }, [poId]);

    const fetchAttachments = async () => {
        setLoading(true);
        try {
            const res = await axios.get(`/api/purchase-orders/${poId}/attachments`);
            setAttachments(res.data);
        } catch (err) {
            toast.error('Gagal memuat daftar lampiran.');
        } finally {
            setLoading(false);
        }
    };

    const handleUpload = async (files) => {
        if (!files || files.length === 0) return;
        setUploading(true);
        try {
            for (const file of files) {
                const formData = new FormData();
                formData.append('file', file);
                await axios.post(`/api/purchase-orders/${poId}/attachments`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            }
            toast.success('File berhasil diunggah.');
            await fetchAttachments();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Gagal mengunggah file.');
        } finally {
            setUploading(false);
        }
    };

    const handleDelete = async (attachment) => {
        try {
            await axios.delete(`/api/attachments/${attachment.id}`);
            toast.success('File berhasil dihapus.');
            setAttachments(prev => prev.filter(a => a.id !== attachment.id));
        } catch (err) {
            toast.error('Gagal menghapus file.');
        } finally {
            setDeleteTarget(null);
        }
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        setDragOver(true);
    };

    const handleDragLeave = () => {
        setDragOver(false);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setDragOver(false);
        const files = Array.from(e.dataTransfer.files);
        handleUpload(files);
    };

    return (
        <Card title="Lampiran">
            {/* Upload Area */}
            {canUpload && (
                <div
                    className={`mb-4 border-2 border-dashed rounded-lg p-6 text-center transition-colors cursor-pointer ${
                        dragOver
                            ? 'border-indigo-500 bg-indigo-50'
                            : 'border-gray-300 hover:border-indigo-400 hover:bg-gray-50'
                    }`}
                    onDragOver={handleDragOver}
                    onDragLeave={handleDragLeave}
                    onDrop={handleDrop}
                    onClick={() => fileInputRef.current?.click()}
                >
                    <input
                        ref={fileInputRef}
                        type="file"
                        multiple
                        accept=".jpg,.jpeg,.png,.pdf,.xlsx"
                        className="hidden"
                        onChange={(e) => handleUpload(Array.from(e.target.files))}
                    />
                    {uploading ? (
                        <div className="flex items-center justify-center gap-2 text-indigo-600">
                            <LoadingSpinner message="" />
                            <span className="text-sm">Mengunggah...</span>
                        </div>
                    ) : (
                        <>
                            <svg className="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p className="text-sm text-gray-600">
                                <span className="font-medium text-indigo-600">Klik untuk upload</span> atau drag & drop
                            </p>
                            <p className="text-xs text-gray-400 mt-1">JPG, PNG, PDF, XLSX (max 10MB)</p>
                        </>
                    )}
                </div>
            )}

            {/* Attachments List */}
            {loading ? (
                <div className="text-center py-4">
                    <LoadingSpinner message="Memuat lampiran..." />
                </div>
            ) : attachments.length === 0 ? (
                <p className="text-sm text-gray-500 text-center py-4">Belum ada lampiran.</p>
            ) : (
                <div className="space-y-2">
                    {attachments.map((att) => (
                        <div
                            key={att.id}
                            className="flex items-center gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100 hover:bg-gray-100 transition-colors"
                        >
                            {/* File Icon or Image Preview */}
                            {isImageFile(att.file_type) ? (
                                <div
                                    className="w-10 h-10 rounded overflow-hidden cursor-pointer flex-shrink-0 border border-gray-200"
                                    onClick={() => setPreviewImage(att)}
                                >
                                    <img
                                        src={`/storage/${att.file_path}`}
                                        alt={att.file_name}
                                        className="w-full h-full object-cover"
                                    />
                                </div>
                            ) : (
                                <span className="text-2xl flex-shrink-0">{getFileIcon(att.file_type)}</span>
                            )}

                            {/* File Info */}
                            <div className="flex-1 min-w-0">
                                <a
                                    href={`/storage/${att.file_path}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800 truncate block"
                                >
                                    {att.file_name}
                                </a>
                                <div className="flex items-center gap-2 text-xs text-gray-400">
                                    <span>{formatFileSize(att.file_size)}</span>
                                    <span>•</span>
                                    <span>{att.uploader?.name || 'Unknown'}</span>
                                    <span>•</span>
                                    <span>{new Date(att.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="flex items-center gap-1 flex-shrink-0">
                                <a
                                    href={`/storage/${att.file_path}`}
                                    download={att.file_name}
                                    className="p-1.5 text-gray-400 hover:text-indigo-600 rounded transition-colors"
                                    title="Download"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </a>
                                {canUpload && (
                                    <button
                                        onClick={() => setDeleteTarget(att)}
                                        className="p-1.5 text-gray-400 hover:text-red-600 rounded transition-colors"
                                        title="Hapus"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Image Preview Modal */}
            {previewImage && (
                <div
                    className="fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4"
                    onClick={() => setPreviewImage(null)}
                >
                    <div className="max-w-4xl max-h-[90vh] relative">
                        <img
                            src={`/storage/${previewImage.file_path}`}
                            alt={previewImage.file_name}
                            className="max-w-full max-h-[85vh] object-contain rounded"
                        />
                        <p className="text-center text-white text-sm mt-2">{previewImage.file_name}</p>
                        <button
                            onClick={() => setPreviewImage(null)}
                            className="absolute top-2 right-2 text-white bg-black bg-opacity-50 rounded-full p-1 hover:bg-opacity-75"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            )}

            {/* Delete Confirmation */}
            <ConfirmModal
                open={!!deleteTarget}
                onClose={() => setDeleteTarget(null)}
                onConfirm={() => handleDelete(deleteTarget)}
                title="Hapus Lampiran"
                message={`Apakah Anda yakin ingin menghapus "${deleteTarget?.file_name}"?`}
                confirmText="Hapus"
            />
        </Card>
    );
}

export default function PurchaseOrderDetail() {
    const [po, setPo] = useState(null);
    const [loading, setLoading] = useState(true);
    const [approving, setApproving] = useState(false);
    const [confirmState, setConfirmState] = useState({ open: false, action: null });
    const toast = useToast();

    const poId = window.location.pathname.split('/').filter(Boolean).pop();

    useEffect(() => {
        fetchPo();
    }, []);

    const fetchPo = async () => {
        setLoading(true);
        try {
            const res = await axios.get(`/api/purchase-orders/${poId}`);
            setPo(res.data);
        } catch (err) {
            toast.error('Gagal memuat data PO.');
        } finally {
            setLoading(false);
        }
    };

    const handleApprove = async () => {
        setApproving(true);
        try {
            await axios.put(`/api/purchase-orders/${poId}/approve`);
            toast.success('PO berhasil di-approve.');
            await fetchPo();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Gagal approve PO.');
        } finally {
            setApproving(false);
            setConfirmState({ open: false, action: null });
        }
    };

    const handleReject = async () => {
        setApproving(true);
        try {
            await axios.put(`/api/purchase-orders/${poId}/reject`);
            toast.success('PO berhasil ditolak.');
            await fetchPo();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Gagal reject PO.');
        } finally {
            setApproving(false);
            setConfirmState({ open: false, action: null });
        }
    };

    const handlePrint = () => {
        window.open(`/purchase-orders/${poId}/print`, '_blank');
    };

    const formatCurrency = (amount) => `Rp ${Number(amount || 0).toLocaleString('id-ID')}`;

    const formatDate = (dateStr) => {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            year: 'numeric', month: 'long', day: 'numeric'
        });
    };

    if (loading) {
        return (
            <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Detail Purchase Order</h2>}>
                <Head title="Detail PO" />
                <div className="py-12 flex justify-center">
                    <LoadingSpinner message="Memuat data PO..." />
                </div>
            </AuthenticatedLayout>
        );
    }

    if (!po) {
        return (
            <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Detail Purchase Order</h2>}>
                <Head title="Detail PO" />
                <div className="py-12 text-center text-gray-500">PO tidak ditemukan.</div>
            </AuthenticatedLayout>
        );
    }

    const itemColumns = [
        { key: 'item_name', label: 'Nama Item', render: (val, row) => val || row.rab_budget?.description || '—' },
        { key: 'qty', label: 'Qty', render: (val) => Number(val).toLocaleString('id-ID') },
        { key: 'unit_price', label: 'Harga Satuan', render: (val) => formatCurrency(val) },
        {
            key: 'total_price',
            label: 'Subtotal',
            render: (val, row) => formatCurrency(val || (row.qty * row.unit_price)),
        },
    ];

    const approvalHistory = po.approval_history || po.approvals || [];

    const subtotal = (po.items || []).reduce((sum, item) => sum + (item.total_price || (item.qty * item.unit_price)), 0);
    const discount = Number(po.discount || 0);
    const subtotalAfterDiscount = subtotal - discount;
    const includePpn = po.include_ppn !== false;
    const ppn = includePpn ? subtotalAfterDiscount * 0.11 : 0;
    const grandTotal = subtotalAfterDiscount + ppn;

    const isDraft = po.status === 'DRAFT';
    const isPending = po.status === 'PENDING_APPROVAL';
    const canUpload = isDraft || isPending;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Detail Purchase Order
                </h2>
            }
        >
            <Head title={`Detail PO - ${po.po_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                    <PageHeader
                        title={`PO: ${po.po_number}`}
                        subtitle={`Dibuat pada ${formatDate(po.created_at)}`}
                        breadcrumbs={[
                            { label: 'Purchase Orders', href: '/po' },
                            { label: po.po_number },
                        ]}
                        actions={
                            <div className="flex items-center gap-2 no-print">
                                {isDraft && (
                                    <Button
                                        variant="primary"
                                        onClick={() => window.location.href = `/purchase-orders/${poId}/edit`}
                                    >
                                        ✏️ Edit
                                    </Button>
                                )}
                                {isPending && (
                                    <>
                                        <Button
                                            variant="success"
                                            loading={approving && confirmState.action === 'approve'}
                                            onClick={() => setConfirmState({ open: true, action: 'approve' })}
                                        >
                                            ✓ Approve
                                        </Button>
                                        <Button
                                            variant="danger"
                                            loading={approving && confirmState.action === 'reject'}
                                            onClick={() => setConfirmState({ open: true, action: 'reject' })}
                                        >
                                            ✕ Reject
                                        </Button>
                                    </>
                                )}
                                <Button variant="outline" onClick={handlePrint}>
                                    🖨️ Cetak
                                </Button>
                            </div>
                        }
                    />

                    {/* Status Timeline */}
                    <Card title="Status PO">
                        <StatusTimeline currentStatus={po.status} />
                    </Card>

                    {/* PO Header Info */}
                    <Card title="Informasi PO">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <p className="text-sm text-gray-500">Nomor PO</p>
                                <p className="text-sm font-semibold text-gray-900">{po.po_number}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Tanggal</p>
                                <p className="text-sm font-semibold text-gray-900">{formatDate(po.date)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Status</p>
                                <StatusBadge status={po.status} />
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Supplier</p>
                                <p className="text-sm font-semibold text-gray-900">{po.supplier_name}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Proyek</p>
                                <p className="text-sm font-semibold text-gray-900">{po.project?.project_name || '—'}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Syarat Pembayaran</p>
                                <p className="text-sm font-semibold text-gray-900">{po.payment_terms || '—'}</p>
                            </div>
                        </div>
                        {po.notes && (
                            <div className="mt-4 pt-4 border-t border-gray-100">
                                <p className="text-sm text-gray-500">Catatan</p>
                                <p className="text-sm text-gray-900">{po.notes}</p>
                            </div>
                        )}
                    </Card>

                    {/* Line Items */}
                    <Card title="Item Barang">
                        <DataTable
                            columns={itemColumns}
                            data={po.items || []}
                            emptyMessage="Tidak ada item."
                        />

                        {/* Totals */}
                        <div className="flex justify-end mt-4">
                            <div className="w-72 space-y-1">
                                <div className="flex justify-between py-1">
                                    <span className="text-sm text-gray-600">Subtotal:</span>
                                    <span className="text-sm font-semibold">{formatCurrency(subtotal)}</span>
                                </div>
                                {discount > 0 && (
                                    <div className="flex justify-between py-1 text-red-600">
                                        <span className="text-sm">Diskon:</span>
                                        <span className="text-sm font-semibold">- {formatCurrency(discount)}</span>
                                    </div>
                                )}
                                {includePpn && (
                                    <div className="flex justify-between py-1 border-b border-gray-200">
                                        <span className="text-sm text-gray-600">PPN 11%:</span>
                                        <span className="text-sm font-semibold">{formatCurrency(ppn)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between py-2">
                                    <span className="text-base font-bold text-gray-900">Grand Total:</span>
                                    <span className="text-base font-bold text-indigo-700">{formatCurrency(grandTotal)}</span>
                                </div>
                            </div>
                        </div>
                    </Card>

                    {/* Attachments */}
                    <AttachmentsSection poId={poId} canUpload={canUpload} />

                    {/* Approval History */}
                    <Card title="Riwayat Approval">
                        {approvalHistory.length === 0 ? (
                            <p className="text-sm text-gray-500 text-center py-4">Belum ada riwayat approval.</p>
                        ) : (
                            <div className="space-y-3">
                                {approvalHistory.map((entry, index) => (
                                    <div
                                        key={entry.id || index}
                                        className="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100"
                                    >
                                        <div className={`flex-shrink-0 w-2 h-2 mt-2 rounded-full ${
                                            entry.action === 'approved' ? 'bg-emerald-500'
                                                : entry.action === 'rejected' ? 'bg-red-500'
                                                : 'bg-yellow-500'
                                        }`} />
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between">
                                                <p className="text-sm font-medium text-gray-900">
                                                    {entry.user?.name || 'System'}
                                                </p>
                                                <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                                    entry.action === 'approved' ? 'bg-green-100 text-green-700'
                                                        : entry.action === 'rejected' ? 'bg-red-100 text-red-700'
                                                        : 'bg-yellow-100 text-yellow-700'
                                                }`}>
                                                    {entry.action?.toUpperCase()}
                                                </span>
                                            </div>
                                            {entry.comment && (
                                                <p className="text-sm text-gray-600 mt-1">{entry.comment}</p>
                                            )}
                                            <p className="text-xs text-gray-400 mt-1">
                                                {formatDate(entry.created_at)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>
            </div>

            {/* Confirm Modal for Approve/Reject */}
            <ConfirmModal
                open={confirmState.open}
                onClose={() => setConfirmState({ open: false, action: null })}
                onConfirm={confirmState.action === 'approve' ? handleApprove : handleReject}
                title={confirmState.action === 'approve' ? 'Approve PO' : 'Reject PO'}
                message={
                    confirmState.action === 'approve'
                        ? 'Apakah Anda yakin ingin menyetujui PO ini?'
                        : 'Apakah Anda yakin ingin menolak PO ini?'
                }
                confirmText={confirmState.action === 'approve' ? 'Approve' : 'Reject'}
            />

            <style>{`
                @media print {
                    .no-print { display: none !important; }
                    .py-12 { padding-top: 0 !important; padding-bottom: 0 !important; }
                }
            `}</style>
        </AuthenticatedLayout>
    );
}
