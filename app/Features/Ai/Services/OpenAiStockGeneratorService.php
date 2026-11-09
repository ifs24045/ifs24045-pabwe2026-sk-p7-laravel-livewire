<?php

namespace App\Features\Ai\Services;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiStockGeneratorService implements AiClientInterface
{
    private const TIMEOUT_SECONDS = 30;

    private const DEFAULT_MODEL = 'gpt-4o-mini';

    private const SYSTEM_PROMPT = <<<'TXT'
Anda adalah asisten inventaris. Balas HANYA dengan JSON valid berbentuk:
{"items":[{"name":"string","sku":"string atau null","quantity":0,"unit":"string","price":0,"min_stock":0,"description":"string atau null"}]}
Buat item stok barang yang realistis sesuai permintaan pengguna. Harga dalam Rupiah (angka tanpa simbol). quantity dan min_stock adalah bilangan bulat >= 0. description boleh Markdown singkat. Jangan menambahkan teks di luar JSON.
TXT;

    public function generate(string $prompt, int $count = 5): array
    {
        $endpoint = (string) config('openai.endpoint');
        $key = (string) config('openai.key');
        $model = (string) (config('openai.model') ?: self::DEFAULT_MODEL);

        if ($endpoint === '' || $key === '') {
            throw new RuntimeException('Integrasi AI belum dikonfigurasi (OPENAI_ENDPOINT dan OPENAI_KEY di .env).');
        }

        $count = max(1, min(10, $count));

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($endpoint, [
                    'model' => $model,
                    'temperature' => 0.7,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => "Buat tepat {$count} item stok untuk: {$prompt}"],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Tidak dapat terhubung ke layanan AI. Periksa koneksi internet Anda.');
        }

        if ($response->failed()) {
            throw new RuntimeException('Layanan AI menolak permintaan (HTTP '.$response->status().'). Periksa OPENAI_KEY dan OPENAI_MODEL.');
        }

        $items = $this->parseItems((string) $response->json('choices.0.message.content'), $count);

        if ($items === []) {
            throw new RuntimeException('AI tidak menghasilkan item yang valid. Coba ubah deskripsi Anda.');
        }

        return $items;
    }

    /**
     * @return list<GeneratedStockItem>
     */
    private function parseItems(string $content, int $count): array
    {
        $content = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $content));
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return [];
        }

        $rows = $decoded['items'] ?? $decoded;

        if (! is_array($rows)) {
            return [];
        }

        $items = [];

        foreach (array_slice(array_values($rows), 0, $count) as $row) {
            $item = is_array($row) ? GeneratedStockItem::fromArray($row) : null;

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }
}