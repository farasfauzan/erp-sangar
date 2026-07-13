<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\ChartOfAccount;
use App\Models\FundRequest;
use App\Models\GeneralLedger;
use App\Models\Transaction;
use App\Support\WorkflowState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FundRequestController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->query('per_page', 15), 100);

        return response()->json(FundRequest::with(['project', 'transactions', 'fundReceipts'])->latest()->paginate($perPage));
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
            'requested_by' => $request->user()->id,
        ]);
        $this->log($request, $fundRequest, 'SUBMIT');

        return response()->json(['message' => 'Permohonan dana/LPJ dibuat.', 'data' => $fundRequest], 201);
    }

    public function approve(Request $request, $id)
    {
        $fundRequest = DB::transaction(function () use ($request, $id) {
            $fundRequest = FundRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($fundRequest->status !== 'PENDING_APPROVAL') {
                return response()->json(['message' => 'Permohonan dana harus berstatus PENDING_APPROVAL sebelum disetujui.'], 422);
            }

            $fundRequest->update([
                'status' => 'APPROVED',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $this->log($request, $fundRequest, 'APPROVE');

            return $fundRequest->fresh();
        });

        if ($fundRequest instanceof JsonResponse) {
            return $fundRequest;
        }

        return response()->json(['message' => 'Permohonan dana disetujui.', 'data' => $fundRequest]);
    }

    public function reject(Request $request, $id)
    {
        $result = DB::transaction(function () use ($request, $id) {
            $fundRequest = FundRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($fundRequest->status !== 'PENDING_APPROVAL') {
                return response()->json(['message' => 'Permohonan dana harus berstatus PENDING_APPROVAL sebelum ditolak.'], 422);
            }

            $fundRequest->update(['status' => 'REJECTED']);
            $this->log($request, $fundRequest, 'REJECT');

            return $fundRequest->fresh();
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['message' => 'Permohonan dana ditolak.', 'data' => $result]);
    }

    public function pay(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'payment_date' => 'nullable|date',
            'proof_of_payment' => 'nullable|string',
        ]);

        $fundRequest = DB::transaction(function () use ($validated, $request, $id) {
            $fundRequest = FundRequest::query()->lockForUpdate()->findOrFail($id);
            WorkflowState::require(
                $fundRequest->status,
                ['APPROVED'],
                'Hanya permohonan dana yang sudah disetujui yang dapat dibayar.'
            );
            WorkflowState::requireAmount(
                $validated['amount'] ?? $fundRequest->amount,
                $fundRequest->amount,
                'Nilai pembayaran harus sama dengan nilai permohonan dana karena pembayaran parsial belum didukung.'
            );

            if ($fundRequest->transactions()->exists()) {
                WorkflowState::fail('Permohonan dana ini sudah memiliki transaksi pembayaran.');
            }

            $fundRequest->update(['status' => 'PAID', 'paid_at' => now()]);

            Transaction::create([
                'fund_request_id' => $fundRequest->id,
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'] ?? $fundRequest->amount,
                'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
                'proof_of_payment' => $validated['proof_of_payment'] ?? null,
            ]);
            $this->log($request, $fundRequest, 'PAYMENT');

            // Auto GL posting: Debit Biaya Proyek, Credit Kas/Bank
            $this->postGlJournal($fundRequest, $validated['payment_date'] ?? now()->toDateString());

            return $fundRequest;
        });

        return response()->json(['message' => 'Dana proyek dibayar dan bukti dicatat.', 'data' => $fundRequest->load('transactions')]);
    }

    public function submitLpj(Request $request, $id)
    {
        $validated = $request->validate([
            'lpj_notes' => 'nullable|string',
            'lpj_items' => 'nullable|array',
            'lpj_items.*.description' => 'required|string',
            'lpj_items.*.amount' => 'required|numeric|min:0',
            'lpj_items.*.category' => 'nullable|string',
        ]);
        $fundRequest = FundRequest::findOrFail($id);
        WorkflowState::require(
            $fundRequest->status,
            ['PAID'],
            'LPJ hanya dapat dikirim setelah permohonan dana dibayar.'
        );
        $fundRequest->update([
            'status' => 'LPJ_SUBMITTED',
            'lpj_notes' => $validated['lpj_notes'] ?? null,
            'lpj_items' => $validated['lpj_items'] ?? null,
            'lpj_submitted_at' => now(),
        ]);
        $this->log($request, $fundRequest, 'LPJ_SUBMIT');

        return response()->json(['message' => 'LPJ dikirim untuk verifikasi.', 'data' => $fundRequest]);
    }

    public function verifyLpj(Request $request, $id)
    {
        $fundRequest = FundRequest::findOrFail($id);
        WorkflowState::require(
            $fundRequest->status,
            ['LPJ_SUBMITTED'],
            'LPJ harus berstatus LPJ_SUBMITTED sebelum diverifikasi.'
        );
        $fundRequest->update(['status' => 'LPJ_VERIFIED']);
        $this->log($request, $fundRequest, 'LPJ_VERIFY');

        return response()->json(['message' => 'LPJ diverifikasi.', 'data' => $fundRequest]);
    }

    /**
     * Post double-entry journal for fund request payment.
     * Debit: Biaya Operasional Proyek (5100) — expense recognized
     * Credit: Kas/Bank (1100) — cash outflow
     */
    private function postGlJournal(FundRequest $fundRequest, string $date): void
    {
        $expenseAccount = '5100'; // Biaya Operasional Proyek
        $cashAccount = '1100';    // Kas/Bank

        $expenseExists = ChartOfAccount::where('code', $expenseAccount)->exists();
        $cashExists = ChartOfAccount::where('code', $cashAccount)->exists();

        if (! $expenseExists || ! $cashExists) {
            // Skip GL posting if COA not configured — log warning
            return;
        }

        $journalNumber = $this->generateJournalNumber($date);
        $amount = (float) $fundRequest->amount;
        $description = "Pembayaran permohonan dana {$fundRequest->request_number}";

        GeneralLedger::create([
            'journal_number' => $journalNumber,
            'transaction_date' => $date,
            'account_code' => $expenseAccount,
            'description' => $description,
            'debit' => $amount,
            'credit' => 0,
            'reference_type' => FundRequest::class,
            'reference_id' => $fundRequest->id,
            'project_id' => $fundRequest->project_id,
            'created_by' => Auth::id(),
        ]);

        GeneralLedger::create([
            'journal_number' => $journalNumber,
            'transaction_date' => $date,
            'account_code' => $cashAccount,
            'description' => $description,
            'debit' => 0,
            'credit' => $amount,
            'reference_type' => FundRequest::class,
            'reference_id' => $fundRequest->id,
            'project_id' => $fundRequest->project_id,
            'created_by' => Auth::id(),
        ]);
    }

    private function generateJournalNumber(string $date): string
    {
        $datePart = date('Ymd', strtotime($date));
        $prefix = "JRN-{$datePart}-";
        $last = GeneralLedger::where('journal_number', 'like', $prefix . '%')
            ->orderByDesc('journal_number')
            ->value('journal_number');

        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function log(Request $request, FundRequest $fundRequest, string $action): void
    {
        ApprovalLog::create([
            'record_type' => FundRequest::class,
            'record_id' => $fundRequest->id,
            'user_id' => $request->user()->id,
            'action' => $action,
        ]);
    }
}
