<?php

namespace App\Http\Controllers;

use App\Actions\ImportDecFileAction;
use App\Http\Requests\ImportDecRequest;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\View\View;

class ImportDeclarationController extends Controller
{
    public function create(): View
    {
        return view('import');
    }

    public function store(ImportDecRequest $request, ImportDecFileAction $action): Response
    {
        @set_time_limit(0);
        @ini_set('max_file_uploads', '1000');

        $files = $request->file('files', []);
        $declarations = [];

        foreach ($files as $file) {
            $declaration = $action->execute($file);
            $declaration->load('client');
            $declarations[] = $declaration;
        }

        $firstClient = $declarations[0]->client ?? null;
        $message = sprintf('%d declarações importadas com sucesso.', count($declarations));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'client_url' => $firstClient ? route('clients.show', $firstClient) : route('clients.index'),
            ]);
        }

        if ($firstClient) {
            return redirect()
                ->route('clients.show', $firstClient)
                ->with('status', $message);
        }

        return redirect()->route('clients.index')->with('status', $message);
    }
}
