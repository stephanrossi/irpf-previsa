<?php

namespace App\Http\Controllers;

use App\Actions\ImportDecFileAction;
use App\Http\Requests\ImportDecRequest;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

        $totalImported = count($declarations);
        $firstClient = $declarations[0]->client ?? null;
        $redirectUrl = ($totalImported === 1 && $firstClient)
            ? route('clients.show', $firstClient)
            : route('clients.index');
        $message = $totalImported === 1
            ? '1 declaração importada com sucesso.'
            : sprintf('%d declarações importadas com sucesso.', $totalImported);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'client_url' => $redirectUrl,
            ]);
        }

        if ($totalImported === 1 && $firstClient) {
            return redirect()
                ->route('clients.show', $firstClient)
                ->with('status', $message);
        }

        return redirect()->route('clients.index')->with('status', $message);
    }
}
