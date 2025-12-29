<?php

namespace App\Http\Controllers;

use App\Actions\ImportDecFileAction;
use App\Http\Requests\ImportDecRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ImportDeclarationController extends Controller
{
    public function create(): View
    {
        return view('import');
    }

    public function store(ImportDecRequest $request, ImportDecFileAction $action): RedirectResponse
    {
        $declaration = $action->execute($request->file('file'));
        $declaration->load('client');

        return redirect()
            ->route('clients.show', $declaration->client)
            ->with('status', 'Declaração importada com sucesso.');
    }
}
