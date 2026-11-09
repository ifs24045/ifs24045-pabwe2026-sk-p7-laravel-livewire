<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockExcelService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StockExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $paths = [];

    private StockExcelService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StockExcelService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function tempPath(): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('stock-', true).'.xlsx';
        $this->paths[] = $path;

        return $path;
    }

    private function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);

        $path = $this->tempPath();
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function readRows(string $path): array
    {
        return IOFactory::load($path)->getActiveSheet()->toArray(null, false, false, false);
    }

    public function test_export_writes_header_and_one_row_per_stock(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create([
            'user_id' => $user->id, 'name' => 'Beras', 'sku' => 'BRS-1', 'quantity' => 50, 'unit' => 'kg',
            'price' => 68000, 'min_stock' => 10, 'status' => 'available', 'description' => 'Enak',
        ]);
        Stock::factory()->create([
            'user_id' => $user->id, 'name' => 'Gula', 'sku' => null, 'quantity' => 4, 'unit' => 'kg',
            'price' => null, 'min_stock' => 5, 'status' => 'low_stock', 'description' => null,
        ]);

        $path = $this->tempPath();
        $this->service->export($user->stocks()->orderBy('id')->get(), $path);
        $rows = $this->readRows($path);

        $this->assertCount(3, $rows);
        $this->assertSame(StockExcelService::HEADERS, $rows[0]);
        $this->assertEquals(['Beras', 'BRS-1', 50, 'kg', 68000, 10, 'available', 'Enak'], $rows[1]);
        $this->assertSame('Gula', $rows[2][0]);
        $this->assertEmpty($rows[2][1]);
        $this->assertEmpty($rows[2][4]);
        $this->assertSame('low_stock', $rows[2][6]);
    }

    public function test_export_with_no_stocks_writes_only_header(): void
    {
        $path = $this->tempPath();

        $this->service->export([], $path);

        $this->assertSame([StockExcelService::HEADERS], $this->readRows($path));
    }

    public function test_export_stores_text_that_looks_like_formula_as_plain_string(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => '=1+1']);

        $path = $this->tempPath();
        $this->service->export($user->stocks()->get(), $path);

        $cell = IOFactory::load($path)->getActiveSheet()->getCell('A2');

        $this->assertSame('=1+1', $cell->getValue());
        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
    }

    public function test_template_contains_header_and_sample_row(): void
    {
        $path = $this->tempPath();

        $this->service->template($path);
        $rows = $this->readRows($path);

        $this->assertCount(2, $rows);
        $this->assertSame(StockExcelService::HEADERS, $rows[0]);
        $this->assertSame('Contoh Barang', $rows[1][0]);
    }

    public function test_import_creates_stocks_and_ignores_status_column(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([
            StockExcelService::HEADERS,
            ['Sabun', 'SBN-1', 20, 'pcs', 5000, 5, 'out_of_stock', 'Sabun mandi'],
            ['Pasta Gigi', null, 3, 'tube', 8000.5, 5, null, null],
        ]);

        $result = $this->service->import($path, $user);

        $this->assertSame(['created' => 2, 'skipped' => 0, 'errors' => []], $result);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Sabun', 'quantity' => 20, 'status' => 'available']);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Pasta Gigi', 'quantity' => 3, 'status' => 'low_stock']);
    }

    public function test_import_recognizes_header_aliases(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([
            ['Nama', 'SKU', 'Qty', 'Satuan', 'Harga', 'Minimum', 'Deskripsi'],
            ['Mi Instan', 'MI-1', 100, 'bungkus', 3500, 20, 'Mi goreng'],
        ]);

        $result = $this->service->import($path, $user);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id, 'name' => 'Mi Instan', 'sku' => 'MI-1', 'quantity' => 100,
            'unit' => 'bungkus', 'min_stock' => 20, 'status' => 'available',
        ]);
    }

    public function test_import_applies_defaults_for_missing_optional_columns(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([['name'], ['Garam']]);

        $result = $this->service->import($path, $user);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id, 'name' => 'Garam', 'quantity' => 0, 'unit' => 'pcs',
            'min_stock' => 5, 'status' => 'out_of_stock',
        ]);
    }

    public function test_import_trims_values_and_casts_numeric_names_to_string(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([
            ['name', 'sku'],
            ['  Sabun  ', ' SBN-9 '],
            [12345, null],
        ]);

        $result = $this->service->import($path, $user);

        $this->assertSame(2, $result['created']);
        $this->assertDatabaseHas('stocks', ['name' => 'Sabun', 'sku' => 'SBN-9']);
        $this->assertDatabaseHas('stocks', ['name' => '12345']);
    }

    public function test_import_uses_first_column_when_header_is_duplicated(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([['name', 'name', 'sku'], ['A', 'B', 'S1']]);

        $this->service->import($path, $user);

        $this->assertDatabaseHas('stocks', ['name' => 'A', 'sku' => 'S1']);
        $this->assertDatabaseMissing('stocks', ['name' => 'B']);
    }

    public function test_import_skips_blank_rows_without_counting_them(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([
            ['name', 'quantity'],
            ['A', 1],
            [null, null],
            ['B', 2],
        ]);

        $result = $this->service->import($path, $user);

        $this->assertSame(['created' => 2, 'skipped' => 0, 'errors' => []], $result);
    }

    public function test_import_skips_existing_sku_of_the_same_user_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id, 'sku' => 'DUP-1']);
        Stock::factory()->create(['user_id' => $other->id, 'sku' => 'OTH-1']);

        $path = $this->makeWorkbook([
            ['name', 'sku'],
            ['Duplikat', 'DUP-1'],
            ['Baru', 'NEW-1'],
            ['Sama Dengan Milik Orang Lain', 'OTH-1'],
        ]);

        $result = $this->service->import($path, $user);

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Baris 2', $result['errors'][0]);
        $this->assertStringContainsString('SKU DUP-1 sudah ada', $result['errors'][0]);
    }

    public function test_import_skips_duplicate_sku_inside_the_same_file(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([
            ['name', 'sku'],
            ['Pertama', 'AAA-1'],
            ['Kedua', 'AAA-1'],
        ]);

        $result = $this->service->import($path, $user);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_import_reports_validation_errors_per_row(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([
            ['name', 'quantity', 'price', 'sku'],
            [null, 1, null, 'X-1'],
            ['A', -5, null, null],
            ['B', 'abc', null, null],
            ['C', 1, 'abc', null],
            ['D', 1.5, null, null],
        ]);

        $result = $this->service->import($path, $user);

        $this->assertSame(0, $result['created']);
        $this->assertSame(5, $result['skipped']);
        $this->assertCount(5, $result['errors']);
        $this->assertStringContainsString('Baris 2:', $result['errors'][0]);
        $this->assertStringContainsString('wajib diisi', $result['errors'][0]);
        $this->assertStringContainsString('Baris 3:', $result['errors'][1]);
        $this->assertStringContainsString('kurang dari 0', $result['errors'][1]);
        $this->assertStringContainsString('Baris 4:', $result['errors'][2]);
        $this->assertStringContainsString('bilangan bulat', $result['errors'][2]);
        $this->assertStringContainsString('Baris 5:', $result['errors'][3]);
        $this->assertStringContainsString('angka', $result['errors'][3]);
        $this->assertStringContainsString('Baris 6:', $result['errors'][4]);
        $this->assertSame(0, Stock::count());
    }

    public function test_import_limits_the_number_of_reported_errors(): void
    {
        $user = User::factory()->create();
        $rows = [['name', 'quantity']];
        for ($i = 0; $i < 60; $i++) {
            $rows[] = ['Item '.$i, -1];
        }

        $result = $this->service->import($this->makeWorkbook($rows), $user);

        $this->assertSame(0, $result['created']);
        $this->assertSame(60, $result['skipped']);
        $this->assertCount(50, $result['errors']);
    }

    public function test_import_rejects_file_without_name_column(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([['sku', 'quantity'], ['X', 1]]);

        $result = $this->service->import($path, $user);

        $this->assertSame(0, $result['created']);
        $this->assertSame(['Kolom "name" tidak ditemukan di baris pertama.'], $result['errors']);
    }

    public function test_import_rejects_file_with_header_only(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([['name', 'sku']]);

        $result = $this->service->import($path, $user);

        $this->assertSame(['created' => 0, 'skipped' => 0, 'errors' => ['File tidak berisi data.']], $result);
    }

    public function test_import_rejects_unreadable_file(): void
    {
        $user = User::factory()->create();
        $path = $this->tempPath();
        file_put_contents($path, 'ini bukan file excel');

        $result = $this->service->import($path, $user);

        $this->assertSame(0, $result['created']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('tidak dapat dibaca', $result['errors'][0]);
    }

    public function test_import_rejects_files_with_too_many_rows(): void
    {
        $user = User::factory()->create();
        $rows = [['name']];
        for ($i = 0; $i <= StockExcelService::MAX_ROWS; $i++) {
            $rows[] = ['Item '.$i];
        }

        $result = $this->service->import($this->makeWorkbook($rows), $user);

        $this->assertSame(0, $result['created']);
        $this->assertSame(['Maksimal 1000 baris data per impor.'], $result['errors']);
        $this->assertSame(0, Stock::count());
    }
        public function test_import_treats_whitespace_only_cells_as_empty(): void
    {
        $user = User::factory()->create();
        $path = $this->makeWorkbook([
            ['name', 'sku', 'description'],
            ['Kecap', '   ', '  '],
        ]);

        $result = $this->service->import($path, $user);

        $this->assertSame(['created' => 1, 'skipped' => 0, 'errors' => []], $result);
        $this->assertDatabaseHas('stocks', ['name' => 'Kecap', 'sku' => null, 'description' => null]);
    }
}