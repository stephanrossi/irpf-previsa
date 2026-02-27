<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Declaration;
use App\Services\ParseDecFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $riskExpression = $this->riskExpression('declarations');
        $latestDeclarationExpression = $this->latestDeclarationExpression('declarations');
        $search = trim((string) $request->string('q'));
        $searchDigits = preg_replace('/\D/', '', $search);
        $complexityRequested = strtolower(trim((string) $request->string('complexity')));
        $allowedComplexities = ['baixa', 'media', 'alta'];
        $complexity = in_array($complexityRequested, $allowedComplexities, true) ? $complexityRequested : '';
        $anoBaseRequested = trim((string) $request->string('ano_base'));
        $anoBase = ctype_digit($anoBaseRequested) ? (int) $anoBaseRequested : null;
        $anoBaseOptions = Declaration::query()
            ->select('ano_base')
            ->distinct()
            ->orderByDesc('ano_base')
            ->pluck('ano_base')
            ->map(fn ($year) => (int) $year)
            ->values();
        $riskOnly = $request->boolean('risk_only');
        $retificadoraOnly = $request->boolean('retificadora_only');
        $perPageRequested = (int) $request->integer('per_page', 20);
        $perPage = in_array($perPageRequested, [20, 50, 100], true) ? $perPageRequested : 20;
        $sort = $request->string('sort', 'nome')->toString();
        $direction = strtolower($request->string('direction', 'asc')->toString()) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = [
            'nome' => 'nome',
            'cpf' => 'cpf',
            'anos' => 'declarations_count',
            'status' => 'latest_risk_declarations_count',
        ];
        $orderColumn = $allowedSorts[$sort] ?? 'nome';

        $clients = Client::withCount([
                'declarations',
                'declarations as latest_risk_declarations_count' => fn ($q) => $q
                    ->whereRaw($latestDeclarationExpression)
                    ->whereRaw($riskExpression),
            ])
            ->when($search !== '', function ($query) use ($search, $searchDigits) {
                $query->where(function ($builder) use ($search, $searchDigits) {
                    $builder->where('nome', 'like', '%'.$search.'%');

                    if ($searchDigits !== '') {
                        $builder->orWhere('cpf', 'like', '%'.$searchDigits.'%');
                    }
                });
            })
            ->when($riskOnly, function ($query) use ($riskExpression, $latestDeclarationExpression) {
                $query->whereHas('declarations', fn ($q) => $q
                    ->whereRaw($latestDeclarationExpression)
                    ->whereRaw($riskExpression));
            })
            ->when($complexity !== '', function ($query) use ($complexity) {
                $query->whereHas('declarations', fn ($q) => $q->where('complexity_level', $complexity));
            })
            ->when($anoBase !== null, function ($query) use ($anoBase) {
                $query->whereHas('declarations', fn ($q) => $q->where('ano_base', $anoBase));
            })
            ->when($retificadoraOnly, function ($query) {
                $query->whereHas('declarations', fn ($q) => $q->where('last_is_retificadora', true));
            })
            ->orderBy($orderColumn, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'search' => $search,
            'complexity' => $complexity,
            'anoBase' => $anoBase,
            'anoBaseOptions' => $anoBaseOptions,
            'riskOnly' => $riskOnly,
            'retificadoraOnly' => $retificadoraOnly,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    private function riskExpression(string $table): string
    {
        $evolucaoPatrimonial = "COALESCE({$table}.total_bens_reais, 0)";
        $caixa = '('
            ."COALESCE({$table}.rend_trib_pj, 0)"
            ." + COALESCE({$table}.rend_trib_pf_exterior, 0)"
            ." + COALESCE({$table}.total_renda_isenta, 0)"
            ." + COALESCE({$table}.total_rend_exclusiva, 0)"
            ." + COALESCE({$table}.total_rend_recebidos_acumuladamente, 0)"
            ." + COALESCE({$table}.total_renda_variavel, 0)"
            ." + COALESCE({$table}.total_atividade_rural_resultado_tributavel, 0)"
            ." - COALESCE({$table}.total_pagamentos_efetuados, 0)"
            ." - COALESCE({$table}.total_doacoes_efetuadas, 0)"
            ." - COALESCE({$table}.total_doacoes_partidos_politicos, 0)"
            ." - COALESCE({$table}.total_dividas_onus_reais, 0)"
            ." - COALESCE({$table}.gastos_estimados, 0)"
            .')';

        return "({$evolucaoPatrimonial} > 0 AND {$caixa} < ({$evolucaoPatrimonial} * 0.2))";
    }

    private function latestDeclarationExpression(string $table): string
    {
        return "{$table}.id = ("
            ."SELECT d2.id FROM declarations d2 "
            ."WHERE d2.client_id = {$table}.client_id "
            ."ORDER BY COALESCE(d2.imported_at, d2.created_at) DESC, d2.id DESC "
            ."LIMIT 1"
            .")";
    }

    public function show(Client $client, ParseDecFileService $parser)
    {
        $client->load(['declarations' => fn ($query) => $query->orderByDesc('ano_base')]);
        $this->backfillComplexity($client, $parser);

        return view('clients.show', [
            'client' => $client,
            'declarations' => $client->declarations,
        ]);
    }

    private function backfillComplexity(Client $client, ParseDecFileService $parser): void
    {
        foreach ($client->declarations as $declaration) {
            if (
                $declaration->complexity_level !== null
                && $declaration->complexity_score !== null
                && is_array($declaration->complexity_breakdown)
            ) {
                continue;
            }

            $path = (string) $declaration->source_file_path;
            if ($path === '') {
                continue;
            }

            try {
                $fullPath = Storage::disk('local')->path($path);
                if (! is_file($fullPath) || ! is_readable($fullPath)) {
                    continue;
                }

                $parsed = $parser->parse($fullPath);
                $declaration->fill([
                    'complexity_score' => $parsed->complexityScore,
                    'complexity_level' => $parsed->complexityLevel,
                    'complexity_breakdown' => $parsed->complexityBreakdown,
                ])->save();
            } catch (Throwable) {
                continue;
            }
        }
    }
}
