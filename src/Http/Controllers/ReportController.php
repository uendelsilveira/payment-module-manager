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
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function transactionSummary(TransactionSummaryRequest $request): JsonResponse
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $summary = $this->reportService->getTransactionSummary($startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Resumo de transações obtido com sucesso.',
            'data' => $summary,
        ]);
    }

    public function transactionsByMethod(TransactionSummaryRequest $request): JsonResponse
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $transactions = $this->reportService->getTransactionsByMethod($startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Transações por método de pagamento obtidas com sucesso.',
            'data' => $transactions,
        ]);
    }
}
