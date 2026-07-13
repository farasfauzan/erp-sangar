<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    /**
     * Neraca (Balance Sheet) — Assets, Liabilities, Equity as of a date.
     */
    public function neraca(Request $request): JsonResponse
    {
        $asOf = $request->input('date_to', date('Y-m-d'));
        $projectId = $request->input('project_id');

        $balances = $this->getAccountBalances($asOf, $projectId);

        $assets = $balances->where('type', 'asset');
        $liabilities = $balances->where('type', 'liability');
        $equity = $balances->where('type', 'equity');

        // Revenue & Expense affect equity via retained earnings
        $revenueBalance = $balances->where('type', 'revenue')->sum('balance');
        $expenseBalance = $balances->where('type', 'expense')->sum('balance');
        $retainedEarnings = $revenueBalance - $expenseBalance;

        $totalAssets = $assets->sum('balance');
        $totalLiabilities = $liabilities->sum('balance');
        $totalEquity = $equity->sum('balance') + $retainedEarnings;

        return response()->json([
            'success' => true,
            'data' => [
                'as_of' => $asOf,
                'aset' => [
                    'items' => $assets->values()->all(),
                    'total' => round($totalAssets, 2),
                ],
                'kewajiban' => [
                    'items' => $liabilities->values()->all(),
                    'total' => round($totalLiabilities, 2),
                ],
                'ekuitas' => [
                    'items' => $equity->values()->all(),
                    'laba_ditahan' => round($retainedEarnings, 2),
                    'total' => round($totalEquity, 2),
                ],
                'total_ekuitas_kewajiban' => round($totalLiabilities + $totalEquity, 2),
                'is_balanced' => bccomp(
                    number_format($totalAssets, 2, '.', ''),
                    number_format($totalLiabilities + $totalEquity, 2, '.', ''),
                    2
                ) === 0,
            ],
        ]);
    }

    /**
     * Laba Rugi (Income Statement) — Revenue vs Expense for a period.
     */
    public function labaRugi(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', date('Y-01-01'));
        $dateTo = $request->input('date_to', date('Y-m-d'));
        $projectId = $request->input('project_id');

        $query = GeneralLedger::select(
            'account_code',
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(credit) as total_credit')
        )
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $revenueAccounts = ChartOfAccount::where('type', 'revenue')->pluck('code')->toArray();
        $expenseAccounts = ChartOfAccount::where('type', 'expense')->pluck('code')->toArray();
        $allAccounts = array_merge($revenueAccounts, $expenseAccounts);

        $entries = $query->whereIn('account_code', $allAccounts)
            ->groupBy('account_code')
            ->get();

        $pendapatan = [];
        $beban = [];
        $totalPendapatan = 0;
        $totalBeban = 0;

        foreach ($entries as $e) {
            $coa = ChartOfAccount::where('code', $e->account_code)->first();
            $item = [
                'account_code' => $e->account_code,
                'account_name' => $coa?->name,
                'total_debit' => (float) $e->total_debit,
                'total_credit' => (float) $e->total_credit,
            ];

            if (in_array($e->account_code, $revenueAccounts)) {
                $balance = (float) $e->total_credit - (float) $e->total_debit;
                $item['balance'] = $balance;
                $pendapatan[] = $item;
                $totalPendapatan += $balance;
            } else {
                $balance = (float) $e->total_debit - (float) $e->total_credit;
                $item['balance'] = $balance;
                $beban[] = $item;
                $totalBeban += $balance;
            }
        }

        $labaBersih = $totalPendapatan - $totalBeban;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['from' => $dateFrom, 'to' => $dateTo],
                'pendapatan' => [
                    'items' => $pendapatan,
                    'total' => round($totalPendapatan, 2),
                ],
                'beban' => [
                    'items' => $beban,
                    'total' => round($totalBeban, 2),
                ],
                'laba_bersih' => round($labaBersih, 2),
            ],
        ]);
    }

    /**
     * Arus Kas (Cash Flow) — cash movements grouped by type.
     */
    public function arusKas(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', date('Y-01-01'));
        $dateTo = $request->input('date_to', date('Y-m-d'));
        $projectId = $request->input('project_id');

        // Cash/bank accounts typically start with 11xx
        $cashAccounts = ChartOfAccount::where('type', 'asset')
            ->where('code', 'like', '11%')
            ->pluck('code')
            ->toArray();

        $query = GeneralLedger::select(
            'account_code',
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(credit) as total_credit')
        )
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $operatingRevenue = ChartOfAccount::where('type', 'revenue')->pluck('code')->toArray();
        $operatingExpense = ChartOfAccount::where('type', 'expense')->pluck('code')->toArray();

        // Operating activities
        $operatingDebit = GeneralLedger::whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->whereIn('account_code', array_merge($operatingRevenue, $operatingExpense))
            ->select(
                DB::raw('SUM(CASE WHEN account_code IN (' . implode(',', array_map(fn($c) => "'$c'", $operatingRevenue)) . ') THEN credit - debit ELSE 0 END) as pendapatan'),
                DB::raw('SUM(CASE WHEN account_code IN (' . implode(',', array_map(fn($c) => "'$c'", $operatingExpense)) . ') THEN debit - credit ELSE 0 END) as beban')
            )->first();

        // Cash inflow/outflow
        $cashIn = GeneralLedger::whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->whereIn('account_code', $cashAccounts)
            ->sum('debit');

        $cashOut = GeneralLedger::whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->whereIn('account_code', $cashAccounts)
            ->sum('credit');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['from' => $dateFrom, 'to' => $dateTo],
                'aktivitas_operasi' => [
                    'pendapatan_diterima' => round((float) ($operatingDebit->pendapatan ?? 0), 2),
                    'beban_dibayar' => round((float) ($operatingDebit->beban ?? 0), 2),
                    'bersih_operasi' => round((float) ($operatingDebit->pendapatan ?? 0) - (float) ($operatingDebit->beban ?? 0), 2),
                ],
                'kas_masuk' => round((float) $cashIn, 2),
                'kas_keluar' => round((float) $cashOut, 2),
                'kas_bersih' => round((float) $cashIn - (float) $cashOut, 2),
            ],
        ]);
    }

    /**
     * Aggregate account balances up to a given date.
     */
    private function getAccountBalances(string $asOf, ?int $projectId = null)
    {
        $query = GeneralLedger::select(
            'account_code',
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(credit) as total_credit'),
            DB::raw('SUM(debit) - SUM(credit) as balance')
        )
            ->where('transaction_date', '<=', $asOf)
            ->groupBy('account_code');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->get()->map(function ($row) {
            $coa = ChartOfAccount::where('code', $row->account_code)->first();
            return [
                'account_code' => $row->account_code,
                'account_name' => $coa?->name,
                'type' => $coa?->type,
                'balance' => (float) $row->balance,
            ];
        });
    }
}
