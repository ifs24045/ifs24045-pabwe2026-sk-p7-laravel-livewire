<?php

namespace App\Features\Ai\DTO;

final class GeneratedStockItem
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $sku,
        public readonly int $quantity,
        public readonly string $unit,
        public readonly ?float $price,
        public readonly int $minStock,
        public readonly ?string $description,
    ) {
    }

    /**
     * Bentuk item dari data mentah AI. Mengembalikan null jika tidak valid.
     */
    public static function fromArray(array $data): ?self
    {
        $name = self::text($data['name'] ?? null, 255);

        if ($name === null) {
            return null;
        }

        return new self(
            name: $name,
            sku: self::text($data['sku'] ?? null, 100),
            quantity: self::wholeNumber($data['quantity'] ?? null, 0),
            unit: self::text($data['unit'] ?? null, 50) ?? 'pcs',
            price: isset($data['price']) && is_numeric($data['price']) ? max(0.0, (float) $data['price']) : null,
            minStock: self::wholeNumber($data['min_stock'] ?? null, 5),
            description: self::text($data['description'] ?? null, 5000),
        );
    }

    /**
     * @return array{name: string, sku: ?string, quantity: int, unit: string, price: ?float, min_stock: int, description: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'price' => $this->price,
            'min_stock' => $this->minStock,
            'description' => $this->description,
        ];
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private static function wholeNumber(mixed $value, int $default): int
    {
        return is_numeric($value) ? max(0, (int) $value) : $default;
    }
}