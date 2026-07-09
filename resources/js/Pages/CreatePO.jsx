import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function CreatePO() {
    const [projects, setProjects] = useState([]);
    const [rabBudgets, setRabBudgets] = useState([]);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');
    const [selectedProject, setSelectedProject] = useState('');

    const { data, setData, post, processing, errors, reset } = useForm({
        project_id: '',
        po_number: 'PO-' + Math.floor(Math.random() * 100000),
        date: new Date().toISOString().split('T')[0],
        supplier_name: '',
        payment_terms: '',
        items: []
    });

    useEffect(() => {
        axios.get('/api/projects').then(res => setProjects(res.data));
    }, []);

    useEffect(() => {
        if (selectedProject) {
            axios.get(`/api/projects/${selectedProject}`).then(res => {
                setRabBudgets(res.data.rab_budgets || []);
            });
        }
    }, [selectedProject]);

    const handleProjectChange = (e) => {
        setSelectedProject(e.target.value);
        setData('project_id', e.target.value);
    };

    const addItem = () => {
        setData('items', [...data.items, { rab_budget_id: '', item_name: '', qty: 1, unit_price: 0, total_price: 0 }]);
    };

    const updateItem = (index, field, value) => {
        const newItems = [...data.items];
        
        if (field === 'rab_budget_id') {
            const selectedRab = rabBudgets.find(r => r.id === parseInt(value));
            if (selectedRab) {
                newItems[index].rab_budget_id = selectedRab.id;
                newItems[index].item_name = selectedRab.description;
                newItems[index].unit_price = selectedRab.unit_price;
                newItems[index].total_price = newItems[index].qty * selectedRab.unit_price;
            }
        } else {
            newItems[index][field] = value;
            if (field === 'qty' || field === 'unit_price') {
                newItems[index].total_price = newItems[index].qty * newItems[index].unit_price;
            }
        }
        
        setData('items', newItems);
    };

    const removeItem = (index) => {
        const newItems = [...data.items];
        newItems.splice(index, 1);
        setData('items', newItems);
    };

    const subtotal = data.items.reduce((acc, item) => acc + item.total_price, 0);
    const tax = subtotal * 0.11;
    const grandTotal = subtotal + tax;

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            const res = await axios.post('/api/pos', data);
            setMessage(res.data.message);
            reset('supplier_name', 'items');
            setData('po_number', 'PO-' + Math.floor(Math.random() * 100000));
        } catch (err) {
            setMessage(err.response?.data?.message || 'Gagal membuat PO.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Buat Purchase Order Baru</h2>}
        >
            <Head title="Buat PO" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            
                            {message && (
                                <div className="mb-4 p-4 bg-green-100 text-green-700 rounded">
                                    {message}
                                </div>
                            )}

                            <form onSubmit={handleSubmit}>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Proyek</label>
                                        <select 
                                            required
                                            value={selectedProject}
                                            onChange={handleProjectChange}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <option value="">-- Pilih Proyek --</option>
                                            {projects.map(p => (
                                                <option key={p.id} value={p.id}>{p.project_name}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Nomor PO</label>
                                        <input type="text" value={data.po_number} onChange={e => setData('po_number', e.target.value)} required className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Supplier</label>
                                        <input type="text" value={data.supplier_name} onChange={e => setData('supplier_name', e.target.value)} required className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Tanggal</label>
                                        <input type="date" value={data.date} onChange={e => setData('date', e.target.value)} required className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    </div>
                                </div>

                                <hr className="my-6" />

                                <div className="flex justify-between items-center mb-4">
                                    <h4 className="text-lg font-bold">Item Barang</h4>
                                    <button type="button" onClick={addItem} className="bg-blue-600 text-white px-3 py-1 rounded shadow hover:bg-blue-700">
                                        + Tambah Baris
                                    </button>
                                </div>

                                <div className="overflow-x-auto mb-6">
                                    <table className="min-w-full divide-y divide-gray-200 border">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item RAB</th>
                                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Harga Satuan</th>
                                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                                <th className="px-4 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {data.items.map((item, index) => (
                                                <tr key={index}>
                                                    <td className="p-2">
                                                        <select 
                                                            required
                                                            value={item.rab_budget_id}
                                                            onChange={e => updateItem(index, 'rab_budget_id', e.target.value)}
                                                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                        >
                                                            <option value="">Pilih Item...</option>
                                                            {rabBudgets.map(rab => (
                                                                <option key={rab.id} value={rab.id}>{rab.code_item} - {rab.description} (Vol: {rab.volume} {rab.unit})</option>
                                                            ))}
                                                        </select>
                                                    </td>
                                                    <td className="p-2">
                                                        <input type="number" step="0.01" min="0" required value={item.qty} onChange={e => updateItem(index, 'qty', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" />
                                                    </td>
                                                    <td className="p-2">
                                                        <input type="number" step="0.01" min="0" required value={item.unit_price} onChange={e => updateItem(index, 'unit_price', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" />
                                                    </td>
                                                    <td className="p-2 align-middle text-sm font-medium">
                                                        Rp {item.total_price.toLocaleString('id-ID')}
                                                    </td>
                                                    <td className="p-2 text-center">
                                                        <button type="button" onClick={() => removeItem(index)} className="text-red-600 hover:text-red-900 font-bold">X</button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {data.items.length === 0 && (
                                                <tr><td colSpan="5" className="p-4 text-center text-sm text-gray-500">Belum ada item ditambahkan.</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="flex justify-end mb-6">
                                    <div className="w-64">
                                        <div className="flex justify-between py-1">
                                            <span className="text-sm font-medium">Subtotal:</span>
                                            <span className="text-sm font-bold">Rp {subtotal.toLocaleString('id-ID')}</span>
                                        </div>
                                        <div className="flex justify-between py-1 border-b">
                                            <span className="text-sm font-medium">PPN 11%:</span>
                                            <span className="text-sm font-bold text-gray-600">Rp {tax.toLocaleString('id-ID')}</span>
                                        </div>
                                        <div className="flex justify-between py-2">
                                            <span className="text-base font-bold">Grand Total:</span>
                                            <span className="text-base font-bold text-indigo-700">Rp {grandTotal.toLocaleString('id-ID')}</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end">
                                    <button 
                                        type="submit" 
                                        disabled={loading || data.items.length === 0}
                                        className="bg-green-600 text-white px-6 py-2 rounded shadow hover:bg-green-700 disabled:opacity-50"
                                    >
                                        {loading ? 'Menyimpan...' : 'Simpan Draft PO'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}