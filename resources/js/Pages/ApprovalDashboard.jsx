import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';

const money = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
const docType = (invoice) => invoice.invoiceable_type?.includes('PurchaseOrder') ? 'PO Material' : 'SPK Subkon';

export default function ApprovalDashboard() {
    const [data, setData] = useState({ pos: [], spks: [], invoices: [], funds: [] });
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        setLoading(true);
        const [pos, spks, invoices, funds] = await Promise.all([
            axios.get('/api/pos'),
            axios.get('/api/spks'),
            axios.get('/api/invoices'),
            axios.get('/api/fund-requests'),
        ]);
        setData({ pos: pos.data, spks: spks.data, invoices: invoices.data, funds: funds.data });
        setLoading(false);
    };

    const run = async (method, url, message, payload = {}) => {
        if (!confirm(message)) return;
        try {
            await axios[method](url, payload);
            await fetchData();
        } catch (err) {
            alert(err.response?.data?.message || 'Aksi gagal.');
        }
    };

    const reject = async (type, id) => {
        const notes = prompt('Catatan penolakan:', 'Dokumen belum sesuai.');
        if (notes === null) return;
        await run('put', `/api/${type}/${id}/reject`, 'Tolak dokumen ini?', { notes });
    };

    const pendingPos = data.pos.filter((po) => po.status === 'PENDING_APPROVAL');
    const pendingSpks = data.spks.filter((spk) => spk.status === 'PENDING_APPROVAL');
    const pendingInvoices = data.invoices.filter((invoice) => ['PENDING_ENGINEER', 'ENGINEER_VERIFIED', 'PENDING_APPROVAL'].includes(invoice.status));
    const pendingFunds = data.funds.filter((fund) => ['PENDING_APPROVAL', 'LPJ_SUBMITTED'].includes(fund.status));

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Approval & Verifikasi</h2>}>
            <Head title="Approval Dashboard" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {loading ? <p>Memuat...</p> : (
                        <>
                            <Section title="Approval PO" empty="Tidak ada PO menunggu approval.">
                                {pendingPos.map((po) => (
                                    <tr key={po.id}>
                                        <Td strong>{po.po_number}</Td>
                                        <Td>{po.project?.project_name ?? 'N/A'}</Td>
                                        <Td>{po.supplier_name}</Td>
                                        <Td strong>{money(po.total_amount)}</Td>
                                        <Td>{po.status}</Td>
                                        <Td>
                                            <Button onClick={() => run('put', `/api/pos/${po.id}/approve`, 'Setujui PO ini?')}>Setujui</Button>
                                            <Button danger onClick={() => reject('pos', po.id)}>Tolak</Button>
                                        </Td>
                                    </tr>
                                ))}
                            </Section>

                            <Section title="Approval SPK" empty="Tidak ada SPK menunggu approval.">
                                {pendingSpks.map((spk) => (
                                    <tr key={spk.id}>
                                        <Td strong>{spk.spk_number}</Td>
                                        <Td>{spk.project?.project_name ?? 'N/A'}</Td>
                                        <Td>{spk.subcon_name}</Td>
                                        <Td strong>{money(spk.total_amount)}</Td>
                                        <Td>{spk.status}</Td>
                                        <Td>
                                            <Button onClick={() => run('put', `/api/spks/${spk.id}/approve`, 'Setujui SPK ini?')}>Setujui</Button>
                                            <Button danger onClick={() => reject('spks', spk.id)}>Tolak</Button>
                                        </Td>
                                    </tr>
                                ))}
                            </Section>

                            <Section title="Verifikasi & Approval Invoice" empty="Tidak ada invoice menunggu proses.">
                                {pendingInvoices.map((invoice) => (
                                    <tr key={invoice.id}>
                                        <Td strong>{invoice.invoice_number}</Td>
                                        <Td>{docType(invoice)}</Td>
                                        <Td>{invoice.invoiceable?.po_number || invoice.invoiceable?.spk_number || '-'}</Td>
                                        <Td strong>{money(invoice.amount)}</Td>
                                        <Td>{invoice.status}</Td>
                                        <Td>
                                            {invoice.status === 'PENDING_ENGINEER' && (
                                                <Button onClick={() => run('put', `/api/invoices/${invoice.id}/engineer-verify`, 'Verifikasi engineer invoice ini?')}>Engineer OK</Button>
                                            )}
                                            {invoice.status === 'ENGINEER_VERIFIED' && (
                                                <Button onClick={() => run('put', `/api/invoices/${invoice.id}/finance-verify`, 'Verifikasi finance invoice ini?')}>Finance OK</Button>
                                            )}
                                            {invoice.status === 'PENDING_APPROVAL' && (
                                                <Button onClick={() => run('put', `/api/invoices/${invoice.id}/manager-approve`, 'Setujui invoice ini?')}>Setujui</Button>
                                            )}
                                        </Td>
                                    </tr>
                                ))}
                            </Section>

                            <Section title="Approval Dana & Verifikasi LPJ" empty="Tidak ada permohonan dana/LPJ menunggu proses.">
                                {pendingFunds.map((fund) => (
                                    <tr key={fund.id}>
                                        <Td strong>{fund.request_number}</Td>
                                        <Td>{fund.project?.project_name ?? 'N/A'}</Td>
                                        <Td>{fund.description || '-'}</Td>
                                        <Td strong>{money(fund.amount)}</Td>
                                        <Td>{fund.status}</Td>
                                        <Td>
                                            {fund.status === 'PENDING_APPROVAL' && (
                                                <Button onClick={() => run('put', `/api/fund-requests/${fund.id}/approve`, 'Setujui permohonan dana ini?')}>Setujui</Button>
                                            )}
                                            {fund.status === 'LPJ_SUBMITTED' && (
                                                <Button onClick={() => run('put', `/api/fund-requests/${fund.id}/lpj-verify`, 'Verifikasi LPJ ini?')}>Verifikasi LPJ</Button>
                                            )}
                                        </Td>
                                    </tr>
                                ))}
                            </Section>
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Section({ title, empty, children }) {
    const rows = Array.isArray(children) ? children.filter(Boolean) : [children].filter(Boolean);

    return (
        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div className="p-6">
                <h3 className="mb-4 text-lg font-bold">{title}</h3>
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nomor</th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Proyek/Tipe</th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Pihak/Referensi</th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nilai</th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200 bg-white">
                        {rows.length ? rows : <tr><td colSpan="6" className="px-4 py-4 text-center text-sm text-gray-500">{empty}</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function Td({ children, strong = false }) {
    return <td className={`px-4 py-3 text-sm ${strong ? 'font-semibold text-gray-900' : 'text-gray-600'}`}>{children}</td>;
}

function Button({ children, onClick, danger = false }) {
    return (
        <button onClick={onClick} className={`mr-2 rounded px-3 py-1 text-sm text-white shadow ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'}`}>
            {children}
        </button>
    );
}
