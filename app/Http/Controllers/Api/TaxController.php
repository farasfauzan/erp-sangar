<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\Request;

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
}
