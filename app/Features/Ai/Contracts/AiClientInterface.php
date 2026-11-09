<?php

namespace App\Features\Ai\Contracts;

use App\Features\Ai\DTO\GeneratedStockItem;

interface AiClientInterface
{
    /**
     * @return list<GeneratedStockItem>
     *
     * @throws \RuntimeException jika layanan AI tidak tersedia atau hasilnya tidak valid
     */
    public function generate(string $prompt, int $count = 5): array;
}