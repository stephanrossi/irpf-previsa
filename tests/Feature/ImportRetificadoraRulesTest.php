<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Declaration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportRetificadoraRulesTest extends TestCase
{
    use RefreshDatabase;

    private function buildDec(array $options): string
    {
        $header = $this->buildLine('IRPF', [
            [9, 12, $options['exercicio'] ?? '2024'],
            [13, 16, $options['ano_base'] ?? '2023'],
            [21, 21, $options['retificadora'] ? '1' : '0'],
            [22, 32, $options['cpf'] ?? '12345678901'],
            [40, 99, $options['nome'] ?? 'Teste'],
            [121, 121, 'S'],
            [124, 133, $options['recibo'] ?? ''],
        ]);
        $reg20 = $this->buildLine('20', [
            [66, 78, str_pad('10000', 13, '0', STR_PAD_LEFT)],
        ]);

        return $header.PHP_EOL.$reg20;
    }

    private function buildLine(string $prefix, array $fields): string
    {
        $line = str_pad($prefix, 1200, ' ');
        foreach ($fields as [$start, $end, $value]) {
            $length = $end - $start + 1;
            $line = substr_replace($line, str_pad((string) $value, $length, ' ', STR_PAD_RIGHT), $start - 1, $length);
        }

        return $line;
    }

    public function test_blocks_original_when_declaration_exists(): void
    {
        Storage::fake('local');

        $client = Client::create(['nome' => 'Teste', 'cpf' => '12345678901']);
        Declaration::create([
            'client_id' => $client->id,
            'exercicio' => 2024,
            'ano_base' => 2023,
            'tipo' => 'completa',
            'total_rend_tributaveis' => 0,
            'total_renda_isenta' => 0,
            'total_bens_imoveis' => 0,
            'total_dividas_onus' => 0,
            'total_bens_adquiridos_ano' => 0,
            'source_file_path' => 'dummy.dec',
            'imported_at' => now(),
        ]);

        $file = UploadedFile::fake()->createWithContent('original.dec', $this->buildDec([
            'retificadora' => false,
        ]));

        $response = $this->postJson(route('import.store'), ['files' => [$file]]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('files');
    }

    public function test_allows_retificadora_and_updates(): void
    {
        Storage::fake('local');

        $client = Client::create(['nome' => 'Teste', 'cpf' => '12345678901']);
        $declaration = Declaration::create([
            'client_id' => $client->id,
            'exercicio' => 2024,
            'ano_base' => 2023,
            'tipo' => 'completa',
            'total_rend_tributaveis' => 0,
            'total_renda_isenta' => 0,
            'total_bens_imoveis' => 0,
            'total_dividas_onus' => 0,
            'total_bens_adquiridos_ano' => 0,
            'source_file_path' => 'dummy.dec',
            'imported_at' => now(),
        ]);

        $file = UploadedFile::fake()->createWithContent('retificadora.dec', $this->buildDec([
            'retificadora' => true,
            'recibo' => '1234567890',
        ]));

        $response = $this->postJson(route('import.store'), ['files' => [$file]]);

        $response->assertStatus(200);
        $declaration->refresh();
        $this->assertTrue($declaration->last_is_retificadora);
        $this->assertEquals('1234567890', $declaration->last_recibo_anterior);
        $this->assertDatabaseCount('declaration_imports', 1);
    }

    public function test_blocks_duplicate_file_by_sha(): void
    {
        Storage::fake('local');

        $client = Client::create(['nome' => 'Teste', 'cpf' => '12345678901']);
        $declaration = Declaration::create([
            'client_id' => $client->id,
            'exercicio' => 2024,
            'ano_base' => 2023,
            'tipo' => 'completa',
            'total_rend_tributaveis' => 0,
            'total_renda_isenta' => 0,
            'total_bens_imoveis' => 0,
            'total_dividas_onus' => 0,
            'total_bens_adquiridos_ano' => 0,
            'source_file_path' => 'dummy.dec',
            'imported_at' => now(),
            'last_source_sha256' => 'abc',
        ]);

        $content = $this->buildDec(['retificadora' => true]);
        $file = UploadedFile::fake()->createWithContent('dupe.dec', $content);
        $sha = hash('sha256', $content);

        $declaration->imports()->create([
            'is_retificadora' => true,
            'recibo_anterior' => null,
            'source_file_path' => 'dummy.dec',
            'source_sha256' => $sha,
            'imported_at' => now(),
        ]);

        $response = $this->postJson(route('import.store'), ['files' => [$file]]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('files');
    }
}
