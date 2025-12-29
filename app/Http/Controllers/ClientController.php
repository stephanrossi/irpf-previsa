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
            ->orderBy('nome')
            ->paginate(12)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'search' => $search,
            'riskOnly' => $riskOnly,
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
