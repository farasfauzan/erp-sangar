<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\FundRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;

class FundRequestController extends Controller
{
    public function index()
    {
        return response()->json(FundRequest::with(['project', 'transactions'])->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'request_number' => 'required|string|unique:fund_requests,request_number',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $fundRequest = FundRequest::create($validated + [
            'status' => 'PENDING_APPROVAL',
            'requested_by' => $request->user()->id ?? 1,
        ]);
        $this->log($request, $fundRequest, 'SUBMIT');

        return response()->json(['message' => 'Permohonan dana/LPJ dibuat.', 'data' => $fundRequest], 201);
    }

    public function approve(Request $request, $id)
    {
        $fundRequest = FundRequest::findOrFail($id);
        $fundRequest->update([
            'status' => 'APPROVED',
            'approved_by' => $request->user()->id ?? 1,
            'approved_at' => now(),
        ]);
        $this->log($request, $fundRequest, 'APPROVE');

        return response()->json(['message' => 'Permohonan dana disetujui.', 'data' => $fundRequest]);
    }

    public function pay(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'payment_date' => 'nullable|date',
            'proof_of_payment' => 'nullable|string',
        ]);

        $fundRequest = FundRequest::findOrFail($id);
        $fundRequest->update(['status' => 'PAID', 'paid_at' => now()]);

        Transaction::create([
            'fund_request_id' => $fundRequest->id,
            'payment_method' => $validated['payment_method'],
            'amount' => $validated['amount'] ?? $fundRequest->amount,
            'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
            'proof_of_payment' => $validated['proof_of_payment'] ?? null,
        ]);
        $this->log($request, $fundRequest, 'PAYMENT');

        return response()->json(['message' => 'Dana proyek dibayar dan bukti dicatat.', 'data' => $fundRequest->load('transactions')]);
    }

    public function submitLpj(Request $request, $id)
    {
        $validated = $request->validate(['lpj_notes' => 'nullable|string']);
        $fundRequest = FundRequest::findOrFail($id);
        $fundRequest->update([
            'status' => 'LPJ_SUBMITTED',
            'lpj_notes' => $validated['lpj_notes'] ?? null,
            'lpj_submitted_at' => now(),
        ]);
        $this->log($request, $fundRequest, 'LPJ_SUBMIT');

        return response()->json(['message' => 'LPJ dikirim untuk verifikasi.', 'data' => $fundRequest]);
    }

    public function verifyLpj(Request $request, $id)
    {
        $fundRequest = FundRequest::findOrFail($id);
        $fundRequest->update(['status' => 'LPJ_VERIFIED']);
        $this->log($request, $fundRequest, 'LPJ_VERIFY');

        return response()->json(['message' => 'LPJ diverifikasi.', 'data' => $fundRequest]);
    }

    private function log(Request $request, FundRequest $fundRequest, string $action): void
    {
        ApprovalLog::create([
            'record_type' => FundRequest::class,
            'record_id' => $fundRequest->id,
            'user_id' => $request->user()->id ?? 1,
            'action' => $action,
        ]);
    }
}