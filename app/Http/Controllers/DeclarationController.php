<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEstimatedExpensesRequest;
use App\Models\Declaration;
use App\Services\IrpfInconsistencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DeclarationController extends Controller
{
    public function updateExpenses(UpdateEstimatedExpensesRequest $request, Declaration $declaration, IrpfInconsistencyService $service): JsonResponse
    {
        $value = $request->validated('gastos_estimados');
        $topIsentos = $declaration->isentos()
            ->orderByDesc('valor')
            ->limit(5)
            ->get(['cod_isento as codigo', 'valor'])
            ->toArray();

        $result = $service->applyToDeclaration($declaration, $value, $topIsentos);

        return response()->json([
            'message' => 'Gastos estimados atualizados.',
            'risco' => $result->risco,
            'variacao' => number_format($result->variacaoDescoberto, 2, ',', '.'),
            'status' => $result->risco ? 'EM RISCO' : 'OK',
        ]);
    }

    public function showReport(Declaration $declaration): View
    {
        $declaration->load(['client', 'isentos' => fn ($q) => $q->orderByDesc('valor')]);
        $payload = $declaration->inconsistencia_payload ?? [];

        return view('declarations.report', [
            'declaration' => $declaration,
            'payload' => $payload,
            'topIsentos' => $declaration->isentos,
        ]);
    }
}
