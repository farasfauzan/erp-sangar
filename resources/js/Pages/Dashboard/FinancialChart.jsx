import { useState, useEffect } from 'react';
import axios from 'axios';
import {
    BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
    AreaChart, Area, CartesianGrid, Legend,
} from 'recharts';
import { Card, LoadingSpinner, EmptyState } from '@/Components/ui';

const fmt = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);
const fmtCompact = (n) => new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(n ?? 0);

function KpiCard({ label, value, sub, icon, accent = 'amber' }) {
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
                    {sub && <p className="text-xs text-gray-500 mt-0.5">{sub}</p>}
                </div>
            </div>
        </div>
    );
}

export default function FinancialChart({ projectId }) {
    const [finData, setFinData] = useState(null);
    const [finRange, setFinRange] = useState('year');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setLoading(true);
        axios.get('/api/dashboard/financial', {
            params: {
                project_id: projectId > 0 ? projectId : undefined,
                range: finRange,
            },
        })
            .then((res) => setFinData(res.data?.data ?? null))
            .catch(() => setFinData(null))
            .finally(() => setLoading(false));
    }, [projectId, finRange]);

    return (
        <div className="flex flex-col gap-5">
            <Card>
                <div className="flex items-center justify-between gap-4">
                    <span className="text-sm font-bold text-gray-900 font-serif">Rentang Waktu</span>
                    <select
                        value={finRange}
                        onChange={(e) => setFinRange(e.target.value)}
                        className="rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm cursor-pointer min-w-[150px]"
                    >
                        <option value="year">12 Bulan Terakhir</option>
                        <option value="quarter">Per Triwulan</option>
                        <option value="month">30 Hari Terakhir</option>
                    </select>
                </div>
            </Card>

            {loading && !finData ? (
                <Card>
                    <LoadingSpinner message="Memuat laporan keuangan..." />
                </Card>
            ) : finData ? (
                <>
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <KpiCard label="Anggaran" value={fmt(finData.summary?.budget)} icon="💼" accent="amber" />
                        <KpiCard label="Komitmen" value={fmt(finData.summary?.committed)} sub={`${finData.summary?.commitment_percentage ?? 0}%`} icon="📜" accent="orange" />
                        <KpiCard label="Dibayar" value={fmt(finData.summary?.paid)} sub={`${finData.summary?.realization_percentage ?? 0}% realisasi`} icon="💳" accent="green" />
                        <KpiCard label="Sisa Anggaran" value={fmt(finData.summary?.remaining_budget)} icon="🛡️" accent="brown" />
                        <KpiCard label="Dana Diminta" value={fmt(finData.fund_request?.requested)} icon="🏦" accent="red" />
                        <KpiCard label="Dana Cair" value={fmt(finData.fund_request?.paid)} icon="💸" accent="yellow" />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <Card title="Realisasi Anggaran">
                            <div className="h-64">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart
                                        data={[
                                            { name: 'Anggaran', value: finData.summary?.budget ?? 0, fill: '#c4942a' },
                                            { name: 'Komitmen', value: finData.summary?.committed ?? 0, fill: '#a0522d' },
                                            { name: 'Dibayar', value: finData.summary?.paid ?? 0, fill: '#6b7a3a' },
                                            { name: 'Sisa', value: finData.summary?.remaining_budget ?? 0, fill: '#6b3a1a' },
                                        ]}
                                        layout="vertical"
                                        margin={{ left: 20, right: 30, top: 5, bottom: 5 }}
                                    >
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis type="number" tickFormatter={(v) => fmtCompact(v)} stroke="#6b7280" fontSize={11} />
                                        <YAxis type="category" dataKey="name" width={70} stroke="#6b7280" fontSize={11} />
                                        <Tooltip formatter={(v) => fmt(v)} contentStyle={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8 }} />
                                        <Bar dataKey="value" radius={[0, 4, 4, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </Card>

                        <Card title="Arus Kas">
                            <div className="h-64">
                                <ResponsiveContainer width="100%" height="100%">
                                    <AreaChart data={finData.cashflow || []} margin={{ left: 5, right: 20, top: 5, bottom: 5 }}>
                                        <defs>
                                            <linearGradient id="colorPaid" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#6b7a3a" stopOpacity={0.35} />
                                                <stop offset="95%" stopColor="#6b7a3a" stopOpacity={0.05} />
                                            </linearGradient>
                                            <linearGradient id="colorCommitted" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#a0522d" stopOpacity={0.35} />
                                                <stop offset="95%" stopColor="#a0522d" stopOpacity={0.05} />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis dataKey="period" stroke="#6b7280" fontSize={10} angle={-30} textAnchor="end" height={50} />
                                        <YAxis tickFormatter={(v) => fmtCompact(v)} stroke="#6b7280" fontSize={11} />
                                        <Tooltip formatter={(v) => fmt(v)} contentStyle={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8 }} />
                                        <Legend />
                                        <Area type="monotone" dataKey="paid" name="Dibayar" stroke="#6b7a3a" fillOpacity={1} fill="url(#colorPaid)" strokeWidth={2} />
                                        <Area type="monotone" dataKey="committed" name="Komitmen" stroke="#a0522d" fillOpacity={1} fill="url(#colorCommitted)" strokeWidth={2} />
                                    </AreaChart>
                                </ResponsiveContainer>
                            </div>
                        </Card>
                    </div>
                </>
            ) : (
                <EmptyState message="Data keuangan tidak tersedia." />
            )}
        </div>
    );
}
