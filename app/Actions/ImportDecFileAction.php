<?php

namespace App\Actions;

use App\DataTransferObjects\ParsedDeclarationData;
use App\Models\Client;
use App\Models\Declaration;
use App\Models\DeclarationIsento;
use App\Models\DeclarationImport;
use App\Services\IrpfInconsistencyService;
use App\Services\ParseDecFileService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportDecFileAction
{
    public function __construct(
        private readonly ParseDecFileService $parser,
        private readonly IrpfInconsistencyService $inconsistencyService
    )
    {
    }

    public function execute(UploadedFile $uploadedFile): Declaration
    {
        $sha = hash_file('sha256', $uploadedFile->getRealPath());
        $header = $this->parser->parseHeader($uploadedFile->getRealPath());
        $parsed = $this->parser->parse($uploadedFile->getRealPath());

        return DB::transaction(function () use ($parsed, $uploadedFile, $sha, $header) {
            $client = Client::updateOrCreate(
                ['cpf' => $header['cpf']],
                ['nome' => $header['nome']],
            );

            $existingDeclaration = Declaration::where('client_id', $client->id)
                ->where('ano_base', $header['ano_base'])
                ->first();

            if ($existingDeclaration) {
                $duplicate = DeclarationImport::where('declaration_id', $existingDeclaration->id)
                    ->where('source_sha256', $sha)
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'files' => ['Este mesmo arquivo já foi importado anteriormente.'],
                    ]);
                }

                if (! $header['is_retificadora']) {
                    throw ValidationException::withMessages([
                        'files' => ["Já existe declaração importada para o ano-base {$header['ano_base']}. Para atualizar, importe uma declaração retificadora."],
                    ]);
                }
            }

            $storedPath = $this->storeFile($uploadedFile, $client, $parsed);

            $declaration = Declaration::updateOrCreate(
                ['client_id' => $client->id, 'ano_base' => $parsed->anoBase],
                [
                    'exercicio' => $parsed->exercicio,
                    'tipo' => $parsed->tipo,
                    'total_rend_tributaveis' => $parsed->totalRendTributaveis,
                    'total_renda_isenta' => $parsed->totalRendaIsenta,
                    'total_planos_saude' => $parsed->totalPlanosSaude,
                    'total_despesas_medicas_odont' => $parsed->totalDespesasMedicasOdont,
                    'total_despesas_instrucao' => $parsed->totalDespesasInstrucao,
                    'total_pensao_judicial' => $parsed->totalPensaoJudicial,
                    'total_pgbl' => $parsed->totalPgbl,
                    'total_ir_pago' => $parsed->totalIrPago,
                    'gastos_declarados_total' => $parsed->gastosDeclaradosTotal,
                    'gastos_declarados_breakdown' => $parsed->gastosDeclaradosBreakdown,
                    'total_bens_imoveis' => $parsed->totalBensImoveis,
                    'total_dividas_onus' => $parsed->totalDividasOnus,
                    'total_bens_adquiridos_ano' => $parsed->totalBensAdquiridosAno,
                    'source_file_path' => $storedPath,
                    'last_is_retificadora' => $parsed->isRetificadora,
                    'last_recibo_anterior' => $parsed->reciboAnterior,
                    'last_source_sha256' => $sha,
                    'last_imported_at' => now(),
                    'imported_at' => now(),
                ]
            );

            $this->syncIsentos($declaration, $parsed->isentosDetalhados);
            $topIsentos = collect($parsed->isentosDetalhados)->sortByDesc('valor')->take(5)->values()->all();
            $this->inconsistencyService->applyToDeclaration($declaration, $declaration->gastos_estimados, $topIsentos);

            DeclarationImport::create([
                'declaration_id' => $declaration->id,
                'is_retificadora' => $parsed->isRetificadora,
                'recibo_anterior' => $parsed->reciboAnterior,
                'source_file_path' => $storedPath,
                'source_sha256' => $sha,
                'imported_at' => now(),
            ]);

            return $declaration;
        });
    }

    private function storeFile(UploadedFile $file, Client $client, ParsedDeclarationData $parsed): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'dec';
        $filename = sprintf(
            '%s-%s-%s.%s',
            $parsed->anoBase,
            $this->cleanCpfForPath($parsed->cpf),
            now()->format('YmdHis'),
            $extension
        );

        $directory = 'declarations/'.$client->id;

        return Storage::disk('local')->putFileAs($directory, $file, $filename);
    }

    private function cleanCpfForPath(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf) ?? '';

        return Str::limit($digits, 20, '');
    }

    private function syncIsentos(Declaration $declaration, array $isentos): void
    {
        $declaration->isentos()->delete();

        if (empty($isentos)) {
            return;
        }

        $rows = collect($isentos)->map(fn ($item) => [
            'cod_isento' => $item['codigo'],
            'valor' => $item['valor'],
        ])->all();

        $declaration->isentos()->createMany($rows);
    }
}
