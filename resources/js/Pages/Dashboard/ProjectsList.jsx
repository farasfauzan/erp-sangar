import { useState, useEffect } from 'react';
import axios from 'axios';
import { Card, Button, StatusBadge, LoadingSpinner, EmptyState } from '@/Components/ui';

const fmt = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);

export default function ProjectsList({ projectId }) {
    const [projData, setProjData] = useState(null);
    const [projPage, setProjPage] = useState(1);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setLoading(true);
        axios.get('/api/dashboard/projects', {
            params: {
                project_id: projectId > 0 ? projectId : undefined,
                page: projPage,
                per_page: 10,
            },
        })
            .then((res) => setProjData(res.data ?? null))
            .catch(() => setProjData(null))
            .finally(() => setLoading(false));
    }, [projectId, projPage]);

    if (loading && !projData) {
        return (
            <Card>
                <LoadingSpinner message="Memuat laporan proyek..." />
            </Card>
        );
    }

    if (!projData?.data?.length) {
        return <EmptyState message="Belum ada data proyek untuk ditampilkan." />;
    }

    return (
        <div className="flex flex-col gap-5">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                {projData.data.map((p) => (
                    <Card key={p.id}>
                        <div className="flex justify-between items-start mb-3">
                            <div>
                                <h4 className="text-base font-bold text-gray-900 font-serif">{p.project_name}</h4>
                                <p className="text-xs text-gray-500 italic">📍 {p.location || '—'}</p>
                            </div>
                            <StatusBadge status={p.status} />
                        </div>

                        <div className="grid grid-cols-2 gap-2 text-xs mb-3">
                            <div className="bg-amber-50 p-2 rounded-lg border border-amber-100">
                                <p className="text-gray-500">Anggaran</p>
                                <p className="font-bold text-gray-900">{fmt(p.budget)}</p>
                            </div>
                            <div className="bg-amber-50 p-2 rounded-lg border border-amber-100">
                                <p className="text-gray-500">Komitmen</p>
                                <p className="font-bold text-orange-700">{fmt(p.commitment)}</p>
                            </div>
                            <div className="bg-amber-50 p-2 rounded-lg border border-amber-100">
                                <p className="text-gray-500">Dibayar</p>
                                <p className="font-bold text-green-700">{fmt(p.paid)}</p>
                            </div>
                            <div className="bg-amber-50 p-2 rounded-lg border border-amber-100">
                                <p className="text-gray-500">Sisa</p>
                                <p className="font-bold text-amber-800">{fmt(p.remaining_budget)}</p>
                            </div>
                        </div>

                        <div className="mb-2">
                            <div className="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Progress Fisik</span>
                                <span>{p.progress_percentage}%</span>
                            </div>
                            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div
                                    className="h-full bg-gradient-to-r from-amber-500 to-amber-700 transition-all duration-300"
                                    style={{ width: `${Math.min(100, p.progress_percentage)}%` }}
                                />
                            </div>
                        </div>

                        <div className="flex justify-between text-xs text-gray-500">
                            <span>Realisasi: {p.realization_percentage}%</span>
                            <span>Komitmen: {p.commitment_percentage}%</span>
                        </div>
                    </Card>
                ))}
            </div>

            {projData.meta && projData.meta.last_page > 1 && (
                <Card>
                    <div className="flex justify-center items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setProjPage((p) => Math.max(1, p - 1))}
                            disabled={projPage <= 1}
                        >
                            ← Sebelumnya
                        </Button>
                        <span className="text-sm text-gray-600 font-semibold">
                            Halaman {projPage} dari {projData.meta.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setProjPage((p) => Math.min(projData.meta.last_page, p + 1))}
                            disabled={projPage >= projData.meta.last_page}
                        >
                            Berikutnya →
                        </Button>
                    </div>
                </Card>
            )}
        </div>
    );
}
