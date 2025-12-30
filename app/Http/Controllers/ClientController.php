<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('q'));
        $searchDigits = preg_replace('/\D/', '', $search);
        $riskOnly = $request->boolean('risk_only');
        $retificadoraOnly = $request->boolean('retificadora_only');
        $sort = $request->string('sort', 'nome')->toString();
        $direction = strtolower($request->string('direction', 'asc')->toString()) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = [
            'nome' => 'nome',
            'cpf' => 'cpf',
            'anos' => 'declarations_count',
            'status' => 'risk_declarations_count',
        ];
        $orderColumn = $allowedSorts[$sort] ?? 'nome';

        $clients = Client::withCount([
                'declarations',
                'declarations as risk_declarations_count' => fn ($q) => $q->where('risco_variacao_patrimonial', true),
            ])
            ->when($search !== '', function ($query) use ($search, $searchDigits) {
                $query->where(function ($builder) use ($search, $searchDigits) {
                    $builder->where('nome', 'like', '%'.$search.'%');

                    if ($searchDigits !== '') {
                        $builder->orWhere('cpf', 'like', '%'.$searchDigits.'%');
                    }
                });
            })
            ->when($riskOnly, function ($query) {
                $query->whereHas('declarations', fn ($q) => $q->where('risco_variacao_patrimonial', true));
            })
            ->when($retificadoraOnly, function ($query) {
                $query->whereHas('declarations', fn ($q) => $q->where('last_is_retificadora', true));
            })
            ->orderBy($orderColumn, $direction)
            ->paginate(12)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'search' => $search,
            'riskOnly' => $riskOnly,
            'retificadoraOnly' => $retificadoraOnly,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function show(Client $client)
    {
        $client->load(['declarations' => fn ($query) => $query->orderByDesc('ano_base')]);

        return view('clients.show', [
            'client' => $client,
            'declarations' => $client->declarations,
        ]);
    }
}
