<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opname;
use Illuminate\Http\Request;

class OpnameController extends Controller
{
    public function index()
    {
        return response()->json(
            Opname::with(['spk.project'])->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'spk_id'              => 'required|exists:spks,id',
            'opname_number'       => 'required|string|unique:opnames,opname_number',
            'date'                => 'required|date',
            'progress_percentage' => 'required|numeric|min:0|max:100',
            'amount'              => 'required|numeric|min:0',
        ]);

        $validated['status'] = 'PENDING';

        $opname = Opname::create($validated);

        return response()->json([
            'message' => 'Opname berhasil dicatat.',
            'data' => $opname->load('spk')
        ], 201);
    }
}