<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use UendelSilveira\PaymentModuleManager\Http\Requests\TransactionSummaryRequest;
use UendelSilveira\PaymentModuleManager\Services\ReportService;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService)
    {
    }

    public function transactionSummary(TransactionSummaryRequest $transactionSummaryRequest): JsonResponse
    {
        $validated = $transactionSummaryRequest->validated();
        assert(is_array($validated));
        $startDate = is_string($validated['start_date'] ?? null) ? $validated['start_date'] : null;
        $endDate = is_string($validated['end_date'] ?? null) ? $validated['end_date'] : null;

        $summary = $this->reportService->getTransactionSummary($startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Resumo de transações obtido com sucesso.',
            'data' => $summary,
        ]);
    }

    public function transactionsByMethod(TransactionSummaryRequest $transactionSummaryRequest): JsonResponse
    {
        $validated = $transactionSummaryRequest->validated();
        assert(is_array($validated));
        $startDate = is_string($validated['start_date'] ?? null) ? $validated['start_date'] : null;
        $endDate = is_string($validated['end_date'] ?? null) ? $validated['end_date'] : null;

        $transactions = $this->reportService->getTransactionsByMethod($startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Transações por método de pagamento obtidas com sucesso.',
            'data' => $transactions,
        ]);
    }
}
