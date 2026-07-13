<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FundReceipt;
use App\Models\FundRequest;
use App\Support\WorkflowState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FundReceiptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->query('per_page', 15), 100);
        $query = FundReceipt::with(['fundRequest.project', 'receiver']);

        if ($request->filled('fund_request_id')) {
            $query->where('fund_request_id', $request->input('fund_request_id'));
        }

        return response()->json($query->latest()->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fund_request_id' => 'required|exists:fund_requests,id',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $fundRequest = FundRequest::findOrFail($validated['fund_request_id']);

        WorkflowState::require(
            $fundRequest->status,
            ['PAID', 'LPJ_SUBMITTED', 'LPJ_VERIFIED'],
            'Dana harus sudah dicairkan (PAID) sebelum dapat diterima.'
        );

        $receipt = FundReceipt::create([
            'fund_request_id' => $fundRequest->id,
            'receipt_number' => self::generateReceiptNumber(),
            'amount' => $validated['amount'],
            'status' => 'RECEIVED',
            'received_by' => $request->user()->id,
            'received_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Dana diterima. Menunggu konfirmasi.',
            'data' => $receipt->load('fundRequest.project'),
        ], 201);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $receipt = FundReceipt::findOrFail($id);

        WorkflowState::require(
            $receipt->status,
            ['RECEIVED'],
            'Kuitansi harus berstatus RECEIVED untuk dikonfirmasi.'
        );

        $receipt->update(['status' => 'CONFIRMED']);

        return response()->json([
            'message' => 'Penerimaan dana dikonfirmasi.',
            'data' => $receipt,
        ]);
    }

    public function dispute(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'required|string']);
        $receipt = FundReceipt::findOrFail($id);

        WorkflowState::require(
            $receipt->status,
            ['RECEIVED'],
            'Kuitansi harus berstatus RECEIVED untuk dipermasalahkan.'
        );

        $receipt->update(['status' => 'DISPUTED', 'notes' => $request->input('notes')]);

        return response()->json([
            'message' => 'Penerimaan dana dipermasalahkan.',
            'data' => $receipt,
        ]);
    }

    private static function generateReceiptNumber(): string
    {
        $datePart = now()->format('Ymd');
        $prefix = "RCPT-{$datePart}-";
        $last = FundReceipt::where('receipt_number', 'like', $prefix . '%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
