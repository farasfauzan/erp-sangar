<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bast;
use App\Models\Opname;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BastController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->query('per_page', 15), 100);

        $query = Bast::with('opname.spk.project');

        if ($request->has('opname_id')) {
            $query->where('opname_id', $request->query('opname_id'));
        }

        return response()->json($query->latest()->paginate($perPage));
    }

    public function show($id)
    {
        $bast = Bast::with('opname.spk.project')->findOrFail($id);
        return response()->json(['data' => $bast]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'opname_id' => 'required|exists:opnames,id',
            'bast_number' => 'required|string|unique:basts,bast_number',
            'bast_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $opname = Opname::findOrFail($validated['opname_id']);
        if ($opname->status !== 'APPROVED') {
            return response()->json(['message' => 'BAST hanya bisa dibuat untuk opname yang sudah disetujui.'], 422);
        }

        $bast = Bast::create($validated);

        return response()->json([
            'message' => 'BAST berhasil dibuat.',
            'data' => $bast->load('opname.spk.project'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $bast = Bast::findOrFail($id);

        $validated = $request->validate([
            'bast_date' => 'sometimes|date',
            'notes' => 'nullable|string',
        ]);

        $bast->update($validated);

        return response()->json([
            'message' => 'BAST berhasil diperbarui.',
            'data' => $bast->fresh('opname.spk.project'),
        ]);
    }

    public function destroy($id)
    {
        $bast = Bast::findOrFail($id);
        $bast->delete();

        return response()->json(['message' => 'BAST berhasil dihapus.']);
    }

    public function print($id)
    {
        $bast = Bast::with('opname.spk.project')->findOrFail($id);
        return view('print.bast', ['bast' => $bast]);
    }
}
