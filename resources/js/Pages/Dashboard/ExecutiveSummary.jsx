import { useState, useEffect } from 'react';
import axios from 'axios';
import {
    BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
    PieChart, Pie, Cell, CartesianGrid,
} from 'recharts';
import { Card, LoadingSpinner, EmptyState } from '@/Components/ui';

const fmt = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);
const fmtCompact = (n) => new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(n ?? 0);

const statusColorMap = {
    completed: '#6b7a3a',
    active: '#c4942a',
    planning: '#dbb45c',
    on_hold: '#a89272',
    cancelled: '#c4a878',
};

const chartColors = ['#c4942a', '#a0522d', '#8b2e1e', '#6b7a3a', '#6b3a1a', '#dbb45c', '#6b1a10', '#4a5528'];

function KpiCard({ label, value, icon, accent = 'amber' }) {
    const borderColors = {
        amber: 'border-l-amber-500',
        orange: 'border-l-orange-600',
        red: 'border-l-red-700',
        green: 'border-l-green-700',
        yellow: 'border-l-yellow-500',
        brown: 'border-l-amber-800',
    };
    return (
        <div className={`bg-white rounded-lg shadow-sm border border-gray-200 border-l-4 ${borderColors[accent] || borderColors.amber} p-4`}>
            <div className="flex items-center gap-3">
                <span className="text-2xl">{icon}</span>
                <div className="min-w-0">
                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">{label}</p>
                    <p className="text-lg font-extrabold text-gray-900 font-serif leading-tight">{value}</p>
                </div>
            </div>
        </div>
    );
}

export default function ExecutiveSummary({ projectId }) {
    const [execData, setExecData] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setLoading(true);
        axios.get('/api/dashboard/executive', {
            params: { project_id: projectId > 0 ? projectId : undefined },
        })
            .then((res) => setExecData(res.data?.data ?? null))
            .catch(() => setExecData(null))
            .finally(() => setLoading(false));
    }, [projectId]);

    if (loading && !execData) {
        return (
            <Card>
                <LoadingSpinner message="Memuat ringkasan eksekutif..." />
            </Card>
        );
    }

    if (!execData) {
        return <EmptyState message="Data eksekutif tidak tersedia." />;
    }

    const budgetPercent = execData.total_budget > 0
        ? Math.round((execData.total_commitment / execData.total_budget) * 100)
        : 0;

    return (
        <div className="flex flex-col gap-5">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <KpiCard label="Total Anggaran" value={fmt(execData.total_budget)} icon="💼" accent="amber" />
                <KpiCard label="Total Komitmen" value={fmt(execData.total_commitment)} icon="📜" accent="orange" />
                <KpiCard label="Total Difaktur" value={fmt(execData.total_invoiced)} icon="🧾" accent="red" />
                <KpiCard label="Total Dibayar" value={fmt(execData.total_paid)} icon="💳" accent="green" />
                <KpiCard label="Permohonan Dana" value={fmt(execData.total_fund_requests)} icon="🏦" accent="yellow" />
                <KpiCard label="Menunggu Persetujuan" value={execData.pending_approvals} icon="⏳" accent="brown" />
                <KpiCard label="Jumlah Proyek" value={execData.project_count} icon="🏛️" accent="amber" />
                <KpiCard label="Item RAB" value={execData.rab_item_count} icon="📐" accent="orange" />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                <Card title="Distribusi Anggaran per Kategori">
                    <div className="h-64">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={execData.by_category || []} layout="vertical" margin={{ left: 20, right: 30, top: 5, bottom: 5 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                <XAxis type="number" tickFormatter={(v) => fmtCompact(v)} stroke="#6b7280" fontSize={11} />
                                <YAxis type="category" dataKey="category_name" width={100} stroke="#6b7280" fontSize={11} />
                                <Tooltip formatter={(v) => fmt(v)} contentStyle={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8 }} />
                                <Bar dataKey="total" name="Anggaran" fill="#c4942a" radius={[0, 4, 4, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </Card>

                <Card title="Status Proyek">
                    <div className="h-64">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={execData.project_status || []}
                                    dataKey="count"
                                    nameKey="status"
                                    cx="50%"
                                    cy="50%"
                                    outerRadius={80}
                                    label={({ status, count }) => `${status}: ${count}`}
                                    labelLine={false}
                                >
                                    {(execData.project_status || []).map((entry, i) => (
                                        <Cell key={`cell-${i}`} fill={statusColorMap[entry.status?.toLowerCase()] || chartColors[i % chartColors.length]} />
                                    ))}
                                </Pie>
                                <Tooltip contentStyle={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8 }} />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                </Card>
            </div>
        </div>
    );
}
