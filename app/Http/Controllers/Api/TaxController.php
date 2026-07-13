<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaxController extends Controller
{
    /**
     * List all tax types (paginated).
     */
    public function index(Request $request)
    {
        $perPage = min($request->query('per_page', 15), 100);
        $search = $request->query('search');

        $query = Tax::query()->orderBy('id', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        // Optional filter by type
        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        // Optional filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $taxes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $taxes,
        ]);
    }

    /**
     * Get a single tax type.
     */
    public function show($id)
    {
        $tax = Tax::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $tax,
        ]);
    }

    /**
     * Create a new tax type.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:1',
            'type' => 'required|in:ppn,pph,other',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $tax = Tax::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pajak berhasil dibuat.',
            'data' => $tax,
        ], 201);
    }

    /**
     * Update a tax type.
     */
    public function update(Request $request, $id)
    {
        $tax = Tax::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'rate' => 'sometimes|required|numeric|min:0|max:1',
            'type' => 'sometimes|required|in:ppn,pph,other',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $tax->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pajak berhasil diperbarui.',
            'data' => $tax->fresh(),
        ]);
    }

    /**
     * Soft-delete a tax type.
     */
    public function destroy($id)
    {
        $tax = Tax::findOrFail($id);
        $tax->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pajak berhasil dihapus.',
        ]);
    }

    /**
     * Calculate tax for a given amount.
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'tax_id' => 'required|integer|exists:taxes,id',
        ]);

        $tax = Tax::findOrFail($validated['tax_id']);
        $result = $tax->calculateTax((float) $validated['amount']);

        return response()->json([
            'success' => true,
            'data' => array_merge($result, [
                'tax' => [
                    'id' => $tax->id,
                    'name' => $tax->name,
                    'rate' => $tax->rate,
                    'type' => $tax->type,
                ],
            ]),
        ]);
    }

    /**
     * Submit restitusi request for a tax.
     */
    public function submitRestitusi(Request $request, int $id): JsonResponse
    {
        $tax = Tax::findOrFail($id);

        $validated = $request->validate([
            'restitusi_amount' => 'required|numeric|min:0.01',
            'restitusi_notes' => 'nullable|string|max:1000',
        ]);

        if ($tax->restitusi_status !== 'none') {
            return response()->json([
                'success' => false,
                'message' => 'Restitusi sudah pernah diajukan untuk pajak ini.',
            ], 422);
        }

        $tax->update([
            'restitusi_status' => 'pending',
            'restitusi_amount' => $validated['restitusi_amount'],
            'restitusi_notes' => $validated['restitusi_notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Restitusi berhasil diajukan.',
            'data' => $tax->fresh(),
        ]);
    }

    /**
     * Approve or reject a pending restitusi.
     */
    public function approveRestitusi(Request $request, int $id): JsonResponse
    {
        $tax = Tax::findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'restitusi_notes' => 'nullable|string|max:1000',
        ]);

        if ($tax->restitusi_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya restitusi dengan status pending yang bisa diproses.',
            ], 422);
        }

        $newStatus = $validated['action'] === 'approve' ? 'approved' : 'rejected';

        $tax->update([
            'restitusi_status' => $newStatus,
            'restitusi_notes' => $validated['restitusi_notes'] ?? $tax->restitusi_notes,
            'restitusi_approved_at' => now(),
            'restitusi_approved_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Restitusi ' . ($newStatus === 'approved' ? 'disetujui' : 'ditolak') . '.',
            'data' => $tax->fresh(),
        ]);
    }

    /**
     * Mark restitusi as paid.
     */
    public function payRestitusi(int $id): JsonResponse
    {
        $tax = Tax::findOrFail($id);

        if ($tax->restitusi_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya restitusi yang sudah disetujui yang bisa ditandai lunas.',
            ], 422);
        }

        $tax->update(['restitusi_status' => 'paid']);

        return response()->json([
            'success' => true,
            'message' => 'Restitusi ditandai lunas.',
            'data' => $tax->fresh(),
        ]);
    }
}
