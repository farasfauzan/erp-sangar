import { useState, useEffect } from 'react';
import axios from 'axios';
import { Card, LoadingSpinner, EmptyState } from '@/Components/ui';

const fmt = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);

function SummaryCard({ label, value, accent = 'amber' }) {
    const borderColors = {
        amber: 'border-l-amber-500',
        orange: 'border-l-orange-600',
        red: 'border-l-red-700',
    };
    return (
        <div className={`rounded-md border border-slate-200 border-l-2 bg-white p-4 shadow-sm ${borderColors[accent] || borderColors.amber}`}>
            <div>
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">{label}</p>
                    <p className="mt-1 text-xl font-semibold leading-tight text-slate-900">{value}</p>
                </div>
            </div>
        </div>
    );
}

export default function RoleOverview({ projectId }) {
    const [summary, setSummary] = useState(null);
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!projectId) return;
        setLoading(true);
        axios.get('/api/rab/summary', { params: { project_id: projectId } })
            .then((res) => {
                const data = res.data?.data ?? res.data;
                setSummary(data);
                const byCategory = data?.by_category ?? [];
                setCategories(Array.isArray(byCategory) ? byCategory.map((c) => c.category_name).filter(Boolean) : []);
            })
            .catch(() => {
                setSummary(null);
                setCategories([]);
            })
            .finally(() => setLoading(false));
    }, [projectId]);

    if (loading) {
        return (
            <Card>
                <LoadingSpinner message="Memuat data ringkasan..." />
            </Card>
        );
    }

    if (!summary || summary.total_items === 0) {
        return <EmptyState message="Belum ada data ringkasan." />;
    }

    const catList = Array.isArray(summary.by_category)
        ? summary.by_category
        : Object.entries(summary.by_category || {}).map(([cat, data]) => ({
            category_name: cat,
            ...(typeof data === 'object' ? data : {}),
        }));

    return (
        <div className="flex flex-col gap-5">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <SummaryCard label="Total Anggaran" value={fmt(summary.total_budget)} accent="amber" />
                <SummaryCard label="Jumlah Item" value={summary.total_items} accent="orange" />
                <SummaryCard label="Kategori" value={categories.length} accent="red" />
            </div>

            {catList.length > 0 && (
                <Card title="Rincian per Kategori">
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        {catList.map((c, i) => (
                            <div key={c.category_name || i} className="p-3 bg-amber-50 rounded-lg border border-amber-100">
                                <p className="text-sm font-bold text-amber-900">{c.category_name || 'Umum'}</p>
                                <p className="text-xs text-gray-500">{c.count ?? 0} item — {fmt(c.total ?? 0)}</p>
                            </div>
                        ))}
                    </div>
                </Card>
            )}
        </div>
    );
}
