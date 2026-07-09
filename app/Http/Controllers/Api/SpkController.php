<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Spk;
use Illuminate\Http\Request;

class SpkController extends Controller
{
    public function index()
    {
        return response()->json(Spk::with(['project'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'spk_number' => 'required|string|unique:spks,spk_number',
            'subcon_name' => 'required|string',
            'subtotal' => 'required|numeric',
            'payment_terms' => 'nullable|string',
        ]);

        $tax = $validated['subtotal'] * 0.11;

        $spk = Spk::create([
            'project_id' => $validated['project_id'],
            'spk_number' => $validated['spk_number'],
            'subcon_name' => $validated['subcon_name'],
            'subtotal' => $validated['subtotal'],
            'tax_amount' => $tax,
            'total_amount' => $validated['subtotal'] + $tax,
            'payment_terms' => $validated['payment_terms'],
            'status' => 'DRAFT',
            'created_by' => $request->user()->id ?? 1,
        ]);

        return response()->json([
            'message' => 'Draft Surat Perintah Kerja (SPK) berhasil dibuat.',
            'data' => $spk
        ], 201);
    }
}