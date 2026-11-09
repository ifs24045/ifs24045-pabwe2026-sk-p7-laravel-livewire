<?php

namespace App\Features\Stock\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StockExcelService
{
    public const HEADERS = ['name', 'sku', 'quantity', 'unit', 'price', 'min_stock', 'status', 'description'];

    public const MAX_ROWS = 1000;

    private const MAX_ERRORS = 50;

    private const IMPORT_FIELDS = ['name', 'sku', 'quantity', 'unit', 'price', 'min_stock', 'description'];

    private const TEXT_FIELDS = ['name', 'sku', 'unit', 'description'];

    private const ALIASES = [
        'nama' => 'name',
        'qty' => 'quantity',
        'jumlah' => 'quantity',
        'satuan' => 'unit',
        'harga' => 'price',
        'minimum' => 'min_stock',
        'deskripsi' => 'description',
    ];

    private const RULES = [
        'name' => ['required', 'string', 'max:255'],
        'sku' => ['nullable', 'string', 'max:100'],
        'quantity' => ['nullable', 'integer', 'min:0'],
        'unit' => ['nullable', 'string', 'max:50'],
        'price' => ['nullable', 'numeric', 'min:0'],
        'min_stock' => ['nullable', 'integer', 'min:0'],
        'description' => ['nullable', 'string', 'max:5000'],
    ];

    private const MESSAGES = [
        'required' => 'Kolom :attribute wajib diisi.',
        'string' => 'Kolom :attribute harus berupa teks.',
        'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
        'numeric' => 'Kolom :attribute harus berupa angka.',
        'min' => 'Kolom :attribute tidak boleh kurang dari :min.',
        'max' => 'Kolom :attribute terlalu panjang atau terlalu besar (maks :max).',
    ];

    public function __construct(
        private readonly StockService $stocks,
        private readonly StockRepositoryInterface $repository,
    ) {
    }

    /**
     * Tulis daftar stok ke file .xlsx (path boleh php://output).
     */
    public function export(iterable $stocks, string $path): void
    {
        $spreadsheet = $this->newSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $row = 2;
        foreach ($stocks as $stock) {
            $this->writeRecord($sheet, $row++, [
                $stock->name,
                $stock->sku,
                $stock->quantity,
                $stock->unit,
                $stock->price !== null ? (float) $stock->price : null,
                $stock->min_stock,
                $stock->status->value,
                $stock->description,
            ]);
        }

        $this->save($spreadsheet, $path);
    }

    /**
     * Tulis template kosong (header + satu baris contoh).
     */
    public function template(string $path): void
    {
        $spreadsheet = $this->newSpreadsheet();

        $this->writeRecord($spreadsheet->getActiveSheet(), 2, [
            'Contoh Barang', 'SKU-001', 10, 'pcs', 15000, 5, 'available', 'Deskripsi barang (opsional)',
        ]);

        $this->save($spreadsheet, $path);
    }

    /**
     * @return array{created: int, skipped: int, errors: array<int, string>}
     */
    public function import(string $path, User $user): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (ReaderException) {
            return $this->failure('File Excel tidak dapat dibaca. Pastikan formatnya .xlsx yang valid.');
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
        $spreadsheet->disconnectWorksheets();

        if (count($rows) < 2) {
            return $this->failure('File tidak berisi data.');
        }

        $map = $this->mapHeaders(array_shift($rows));

        if (! isset($map['name'])) {
            return $this->failure('Kolom "name" tidak ditemukan di baris pertama.');
        }

        if (count($rows) > self::MAX_ROWS) {
            return $this->failure('Maksimal '.self::MAX_ROWS.' baris data per impor.');
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $cells) {
            $line = $index + 2;
            $record = $this->extractRecord($cells, $map);

            if ($this->isBlank($record)) {
                continue;
            }

            $validator = Validator::make($record, self::RULES, self::MESSAGES);

            if ($validator->fails()) {
                $skipped++;
                $this->addError($errors, "Baris {$line}: ".$validator->errors()->first());

                continue;
            }

            $data = $validator->validated();

            if (filled($data['sku'] ?? null) && $this->repository->existsSkuForUser($user->id, $data['sku'])) {
                $skipped++;
                $this->addError($errors, "Baris {$line}: SKU {$data['sku']} sudah ada.");

                continue;
            }

            $this->stocks->create($user, [
                ...$data,
                'quantity' => (int) ($data['quantity'] ?? 0),
                'min_stock' => (int) ($data['min_stock'] ?? 5),
                'unit' => $data['unit'] ?? 'pcs',
            ]);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function newSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stok');

        foreach (self::HEADERS as $i => $header) {
            $sheet->setCellValueExplicit([$i + 1, 1], $header, DataType::TYPE_STRING);
        }

        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function writeRecord(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $i => $value) {
            $cell = [$i + 1, $row];

            if (is_int($value) || is_float($value)) {
                $sheet->setCellValue($cell, $value);
            } else {
                $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
            }
        }
    }

    private function save(Spreadsheet $spreadsheet, string $path): void
    {
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * @return array<string, int>
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $title) {
            $key = strtolower(trim((string) $title));
            $key = self::ALIASES[$key] ?? $key;

            if (in_array($key, self::IMPORT_FIELDS, true) && ! isset($map[$key])) {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    private function extractRecord(array $cells, array $map): array
    {
        $record = array_fill_keys(self::IMPORT_FIELDS, null);

        foreach ($map as $field => $index) {
            $value = $cells[$index] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '') {
                $value = null;
            }

            if ($value !== null && in_array($field, self::TEXT_FIELDS, true)) {
                $value = (string) $value;
            }

            $record[$field] = $value;
        }

        return $record;
    }

    private function isBlank(array $record): bool
    {
        return count(array_filter($record, fn ($value) => $value !== null)) === 0;
    }

    private function addError(array &$errors, string $message): void
    {
        if (count($errors) < self::MAX_ERRORS) {
            $errors[] = $message;
        }
    }

    /**
     * @return array{created: int, skipped: int, errors: array<int, string>}
     */
    private function failure(string $message): array
    {
        return ['created' => 0, 'skipped' => 0, 'errors' => [$message]];
    }
}