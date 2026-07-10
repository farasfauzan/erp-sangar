<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\Opname;
use App\Models\Spk;
use App\Support\WorkflowState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'progress_percentage' => 'required|numeric|min:0.01|max:100',
            'amount'              => 'required|numeric|min:0.01',
        ]);

        $opname = DB::transaction(function () use ($validated) {
            $spk = Spk::query()->lockForUpdate()->findOrFail($validated['spk_id']);
            WorkflowState::require(
                $spk->status,
                ['APPROVED'],
                'Opname hanya dapat dibuat untuk SPK yang sudah disetujui.'
            );

            $reserved = Opname::query()
                ->where('spk_id', $spk->id)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->selectRaw('COALESCE(SUM(progress_percentage), 0) AS progress, COALESCE(SUM(amount), 0) AS amount')
                ->first();

            if ((float) $reserved->progress + (float) $validated['progress_percentage'] > 100) {
                WorkflowState::fail('Akumulasi progres opname tidak boleh melebihi 100%.');
            }

            if ((float) $reserved->amount + (float) $validated['amount'] > (float) $spk->total_amount) {
                WorkflowState::fail('Akumulasi nilai opname tidak boleh melebihi nilai total SPK.');
            }

            return Opname::create($validated + ['status' => 'PENDING']);
        });

        return response()->json([
            'message' => 'Opname berhasil dicatat.',
            'data' => $opname->load('spk')
        ], 201);
    }

    public function approve(Request $request, $id)
    {
        $opname = Opname::findOrFail($id);
        WorkflowState::require(
            $opname->status,
            ['PENDING'],
            'Opname harus berstatus PENDING sebelum disetujui.'
        );

        $opname->update([
            'status' => 'APPROVED',
            'approved_by' => $request->user()?->id,
        ]);
        $this->log($request, $opname, 'APPROVE');

        return response()->json(['message' => 'Opname disetujui dan siap dibuatkan invoice.', 'data' => $opname]);
    }

    public function reject(Request $request, $id)
    {
        $opname = Opname::findOrFail($id);
        WorkflowState::require(
            $opname->status,
            ['PENDING'],
            'Opname harus berstatus PENDING sebelum ditolak.'
        );

        $opname->update(['status' => 'REJECTED']);
        $this->log($request, $opname, 'REJECT', $request->input('notes'));

        return response()->json(['message' => 'Opname ditolak.', 'data' => $opname]);
    }

    private function log(Request $request, Opname $opname, string $action, ?string $notes = null): void
    {
        ApprovalLog::create([
            'record_type' => Opname::class,
            'record_id' => $opname->id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'notes' => $notes,
        ]);
    }
}
