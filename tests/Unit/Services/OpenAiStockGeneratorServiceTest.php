<?php

namespace Tests\Unit\Services;

use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Ai\Services\OpenAiStockGeneratorService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiStockGeneratorServiceTest extends TestCase
{
    private OpenAiStockGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'openai.endpoint' => 'https://ai.test/v1/chat/completions',
            'openai.key' => 'test-key',
            'openai.model' => 'gpt-test',
        ]);

        $this->service = new OpenAiStockGeneratorService();
    }

    private function fakeContent(array|string $content): void
    {
        Http::fake([
            'https://ai.test/*' => Http::response([
                'choices' => [['message' => ['content' => is_array($content) ? json_encode($content) : $content]]],
            ]),
        ]);
    }

    private function sampleItems(int $count): array
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = [
                'name' => "Barang {$i}", 'sku' => "BRG-{$i}", 'quantity' => 10 * $i, 'unit' => 'pcs',
                'price' => 1000 * $i, 'min_stock' => 5, 'description' => "Deskripsi {$i}",
            ];
        }

        return $items;
    }

    public function test_generate_returns_items_and_sends_expected_request(): void
    {
        $this->fakeContent(['items' => $this->sampleItems(2)]);

        $items = $this->service->generate('bahan pokok', 2);

        $this->assertCount(2, $items);
        $this->assertContainsOnlyInstancesOf(GeneratedStockItem::class, $items);
        $this->assertSame('Barang 1', $items[0]->name);
        $this->assertSame(20, $items[1]->quantity);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://ai.test/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'gpt-test'
                && $request['response_format']['type'] === 'json_object'
                && str_contains($request['messages'][1]['content'], 'tepat 2 item')
                && str_contains($request['messages'][1]['content'], 'bahan pokok');
        });
    }

    public function test_generate_keeps_at_most_the_requested_number_of_items(): void
    {
        $this->fakeContent(['items' => $this->sampleItems(4)]);

        $this->assertCount(2, $this->service->generate('apa saja', 2));
    }

    public function test_generate_accepts_json_wrapped_in_code_fences(): void
    {
        $this->fakeContent("```json\n".json_encode(['items' => $this->sampleItems(1)])."\n```");

        $items = $this->service->generate('apa saja', 5);

        $this->assertCount(1, $items);
        $this->assertSame('Barang 1', $items[0]->name);
    }

    public function test_generate_accepts_a_plain_list_without_items_key(): void
    {
        $this->fakeContent($this->sampleItems(2));

        $this->assertCount(2, $this->service->generate('apa saja', 5));
    }

    public function test_generate_skips_invalid_rows(): void
    {
        $this->fakeContent(['items' => ['bukan array', ['name' => ''], ['name' => 'Valid']]]);

        $items = $this->service->generate('apa saja', 5);

        $this->assertCount(1, $items);
        $this->assertSame('Valid', $items[0]->name);
    }

    public function test_generate_clamps_the_requested_count(): void
    {
        $this->fakeContent(['items' => $this->sampleItems(1)]);

        $this->service->generate('apa saja', 99);
        $this->service->generate('apa saja', 0);

        Http::assertSent(fn (Request $request) => str_contains($request['messages'][1]['content'], 'tepat 10 item'));
        Http::assertSent(fn (Request $request) => str_contains($request['messages'][1]['content'], 'tepat 1 item'));
    }

    public function test_generate_falls_back_to_default_model_when_not_configured(): void
    {
        config(['openai.model' => '']);
        $this->fakeContent(['items' => $this->sampleItems(1)]);

        $this->service->generate('apa saja', 1);

        Http::assertSent(fn (Request $request) => $request['model'] === 'gpt-4o-mini');
    }

    public function test_generate_requires_endpoint(): void
    {
        config(['openai.endpoint' => '']);
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum dikonfigurasi');

        try {
            $this->service->generate('apa saja', 1);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_generate_requires_api_key(): void
    {
        config(['openai.key' => '']);
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum dikonfigurasi');

        $this->service->generate('apa saja', 1);
    }

    public function test_generate_reports_http_errors(): void
    {
        Http::fake(['*' => Http::response('error', 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');

        $this->service->generate('apa saja', 1);
    }

    public function test_generate_reports_connection_problems(): void
    {
        Http::fake(['*' => fn () => throw new ConnectionException('timeout')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak dapat terhubung');

        $this->service->generate('apa saja', 1);
    }

    public function test_generate_fails_when_no_valid_item_is_returned(): void
    {
        $this->fakeContent(['items' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak menghasilkan item yang valid');

        $this->service->generate('apa saja', 1);
    }

    public function test_generate_fails_when_content_is_not_json(): void
    {
        $this->fakeContent('ini bukan json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak menghasilkan item yang valid');

        $this->service->generate('apa saja', 1);
    }

    public function test_generate_fails_when_items_is_not_a_list(): void
    {
        $this->fakeContent('{"items":"bukan array"}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak menghasilkan item yang valid');

        $this->service->generate('apa saja', 1);
    }

    public function test_generate_fails_when_response_has_no_content(): void
    {
        Http::fake(['*' => Http::response(['foo' => 'bar'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak menghasilkan item yang valid');

        $this->service->generate('apa saja', 1);
    }
}