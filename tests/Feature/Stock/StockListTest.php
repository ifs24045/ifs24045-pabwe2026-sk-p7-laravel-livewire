<?php

namespace Tests\Feature\Stock;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Stock\Livewire\StockList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class StockListTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function actingAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    private function stockFor(User $user, array $attributes = []): Stock
    {
        return Stock::factory()->create(array_merge(['user_id' => $user->id], $attributes));
    }

    private function xlsxFile(array $rows, string $name = 'stok.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($path);
        $content = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function fakeAi(callable $generator): void
    {
        $this->app->bind(AiClientInterface::class, fn () => new class($generator) implements AiClientInterface
        {
            public function __construct(private $generator)
            {
            }

            public function generate(string $prompt, int $count = 5): array
            {
                return ($this->generator)($prompt, $count);
            }
        });
    }

    // ---------- Daftar, cari, filter ----------

    public function test_it_lists_only_the_members_stocks(): void
    {
        $user = $this->actingMember();
        $this->stockFor($user, ['name' => 'Kopi Milikku']);
        Stock::factory()->create(['name' => 'Teh Orang Lain']);

        Livewire::test(StockList::class)
            ->assertSee('Kopi Milikku')
            ->assertDontSee('Teh Orang Lain');
    }

    public function test_it_shows_empty_state(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)->assertSee('Tidak ada stok yang cocok.');
    }

    public function test_it_searches_by_name(): void
    {
        $user = $this->actingMember();
        $this->stockFor($user, ['name' => 'Kopi Bubuk']);
        $this->stockFor($user, ['name' => 'Teh Celup']);

        Livewire::test(StockList::class)
            ->set('search', 'kopi')
            ->assertSee('Kopi Bubuk')
            ->assertDontSee('Teh Celup');
    }

    public function test_it_filters_by_status(): void
    {
        $user = $this->actingMember();
        $this->stockFor($user, ['name' => 'Barang Habis', 'quantity' => 0, 'status' => 'out_of_stock']);
        $this->stockFor($user, ['name' => 'Barang Aman', 'quantity' => 99, 'min_stock' => 5, 'status' => 'available']);

        Livewire::test(StockList::class)
            ->set('status', 'out_of_stock')
            ->assertSee('Barang Habis')
            ->assertDontSee('Barang Aman');
    }

    public function test_unknown_status_filter_is_ignored(): void
    {
        $user = $this->actingMember();
        $this->stockFor($user, ['name' => 'Barang Satu']);
        $this->stockFor($user, ['name' => 'Barang Dua']);

        Livewire::test(StockList::class)
            ->set('status', 'ngawur')
            ->assertSee('Barang Satu')
            ->assertSee('Barang Dua');
    }

    public function test_it_paginates_ten_items_per_page(): void
    {
        $user = $this->actingMember();
        foreach (range(1, 12) as $i) {
            $this->stockFor($user, ['name' => sprintf('Barang %02d', $i)]);
        }

        Livewire::test(StockList::class)
            ->assertSee('Barang 12')
            ->assertSee('Barang 03')
            ->assertDontSee('Barang 02')
            ->assertDontSee('Barang 01');
    }

    // ---------- Tambah ----------

    public function test_member_can_create_stock_from_the_modal(): void
    {
        $user = $this->actingMember();

        Livewire::test(StockList::class)
            ->call('openCreate')
            ->assertSet('showForm', true)
            ->assertSet('editingId', null)
            ->set('name', 'Kopi')
            ->set('sku', 'KOP-1')
            ->set('description', '**enak**')
            ->set('quantity', '3')
            ->set('unit', 'box')
            ->set('price', '1500')
            ->set('minStock', '5')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Stok berhasil ditambahkan.');

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Kopi',
            'sku' => 'KOP-1',
            'quantity' => 3,
            'unit' => 'box',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
    }

    public function test_optional_fields_are_stored_as_null_when_blank(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->call('openCreate')
            ->set('name', 'Garam')
            ->set('sku', '')
            ->set('description', '')
            ->set('price', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stocks', ['name' => 'Garam', 'sku' => null, 'description' => null, 'price' => null]);
    }

    public function test_save_validates_required_fields(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->call('openCreate')
            ->set('name', '')
            ->set('quantity', '-1')
            ->set('unit', '')
            ->set('price', 'abc')
            ->set('minStock', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'quantity' => 'min', 'unit' => 'required', 'price' => 'numeric', 'minStock' => 'required'])
            ->assertSet('showForm', true);

        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_quantity_and_min_stock_must_be_whole_numbers(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->call('openCreate')
            ->set('name', 'Gula')
            ->set('quantity', '1.5')
            ->set('minStock', 'abc')
            ->call('save')
            ->assertHasErrors(['quantity' => 'integer', 'minStock' => 'integer']);
    }

    public function test_opening_create_again_clears_previous_input_and_errors(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->call('openCreate')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name')
            ->call('closeForm')
            ->call('openCreate')
            ->assertHasNoErrors()
            ->assertSet('name', '')
            ->assertSet('quantity', '0')
            ->assertSet('unit', 'pcs')
            ->assertSet('minStock', '5');
    }

    // ---------- Ubah ----------

    public function test_open_edit_fills_the_form_from_the_stock(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, [
            'name' => 'Beras', 'sku' => 'BRS-1', 'description' => 'Enak', 'quantity' => 7,
            'unit' => 'kg', 'price' => 12500, 'min_stock' => 4,
        ]);

        Livewire::test(StockList::class)
            ->call('openEdit', $stock->id)
            ->assertSet('showForm', true)
            ->assertSet('editingId', $stock->id)
            ->assertSet('name', 'Beras')
            ->assertSet('sku', 'BRS-1')
            ->assertSet('description', 'Enak')
            ->assertSet('quantity', '7')
            ->assertSet('unit', 'kg')
            ->assertSet('price', '12500.00')
            ->assertSet('minStock', '4');
    }

    public function test_open_edit_uses_blank_strings_for_null_columns(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['sku' => null, 'description' => null, 'price' => null]);

        Livewire::test(StockList::class)
            ->call('openEdit', $stock->id)
            ->assertSet('sku', '')
            ->assertSet('description', '')
            ->assertSet('price', '');
    }

    public function test_member_can_update_own_stock_and_status_is_recalculated(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['name' => 'Lama', 'quantity' => 50, 'min_stock' => 5, 'status' => 'available']);

        Livewire::test(StockList::class)
            ->call('openEdit', $stock->id)
            ->set('name', 'Baru')
            ->set('quantity', '2')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Stok berhasil diperbarui.');

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'name' => 'Baru', 'quantity' => 2, 'status' => 'low_stock']);
    }

    public function test_close_form_resets_the_form(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['name' => 'Beras']);

        Livewire::test(StockList::class)
            ->call('openEdit', $stock->id)
            ->call('closeForm')
            ->assertSet('showForm', false)
            ->assertSet('editingId', null)
            ->assertSet('name', '');
    }

    public function test_member_cannot_open_or_update_someone_elses_stock(): void
    {
        $this->actingMember();
        $others = Stock::factory()->create(['name' => 'Milik Orang Lain']);

        Livewire::test(StockList::class)
            ->call('openEdit', $others->id)
            ->assertForbidden();

        Livewire::test(StockList::class)
            ->call('openCreate')
            ->set('editingId', $others->id)
            ->set('name', 'Diretas')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseHas('stocks', ['id' => $others->id, 'name' => 'Milik Orang Lain']);
    }

    public function test_opening_a_missing_stock_returns_404(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)->call('openEdit', 999999)->assertNotFound();
    }

    // ---------- Jumlah ----------

    public function test_member_can_increase_and_decrease_quantity(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['quantity' => 5, 'min_stock' => 5, 'status' => 'low_stock']);

        Livewire::test(StockList::class)
            ->call('adjust', $stock->id, 1)
            ->call('adjust', $stock->id, 1);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 7, 'status' => 'available']);

        Livewire::test(StockList::class)->call('adjust', $stock->id, -1);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 6]);
    }

    public function test_quantity_never_goes_below_zero(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['quantity' => 0, 'status' => 'out_of_stock']);

        Livewire::test(StockList::class)->call('adjust', $stock->id, -1);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 0, 'status' => 'out_of_stock']);
    }

    public function test_member_cannot_adjust_someone_elses_stock(): void
    {
        $this->actingMember();
        $others = Stock::factory()->create(['quantity' => 10]);

        Livewire::test(StockList::class)->call('adjust', $others->id, 1)->assertForbidden();

        $this->assertDatabaseHas('stocks', ['id' => $others->id, 'quantity' => 10]);
    }

    // ---------- Hapus ----------

    public function test_member_can_delete_own_stock_after_confirmation(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user);

        Livewire::test(StockList::class)
            ->call('confirmDelete', $stock->id)
            ->assertSet('confirmingDeleteId', $stock->id)
            ->call('delete')
            ->assertSet('confirmingDeleteId', null)
            ->assertSee('Stok berhasil dihapus.');

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_cancel_delete_keeps_the_stock(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user);

        Livewire::test(StockList::class)
            ->call('confirmDelete', $stock->id)
            ->call('cancelDelete')
            ->assertSet('confirmingDeleteId', null);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id]);
    }

    public function test_delete_without_confirmation_does_nothing(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user);

        Livewire::test(StockList::class)->call('delete')->assertHasNoErrors();

        $this->assertDatabaseHas('stocks', ['id' => $stock->id]);
    }

    public function test_member_cannot_delete_someone_elses_stock(): void
    {
        $this->actingMember();
        $others = Stock::factory()->create();

        Livewire::test(StockList::class)->call('confirmDelete', $others->id)->assertForbidden();

        Livewire::test(StockList::class)
            ->set('confirmingDeleteId', $others->id)
            ->call('delete')
            ->assertForbidden();

        $this->assertDatabaseHas('stocks', ['id' => $others->id]);
    }

    // ---------- Admin tidak boleh menulis ----------

    public function test_admin_cannot_create_stock_from_the_component(): void
    {
        $this->actingAdmin();

        Livewire::test(StockList::class)->call('openCreate')->assertForbidden();

        Livewire::test(StockList::class)
            ->set('name', 'Dari Admin')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseCount('stocks', 0);
    }

    // ---------- Ekspor dan template ----------

    public function test_member_can_download_export_and_template(): void
    {
        $user = $this->actingMember();
        $this->stockFor($user, ['name' => 'Kopi']);

        Livewire::test(StockList::class)->call('export')->assertFileDownloaded();
        Livewire::test(StockList::class)->call('downloadTemplate')->assertFileDownloaded();
    }

    public function test_admin_cannot_download_export_or_template(): void
    {
        $this->actingAdmin();

        Livewire::test(StockList::class)->call('export')->assertForbidden();
        Livewire::test(StockList::class)->call('downloadTemplate')->assertForbidden();
    }

    // ---------- Impor ----------

    public function test_import_modal_opens_and_closes(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->call('openImport')
            ->assertSet('showImport', true)
            ->assertSet('importResult', null)
            ->call('closeImport')
            ->assertSet('showImport', false);
    }

    public function test_member_can_import_stocks_from_xlsx(): void
    {
        $user = $this->actingMember();

        Livewire::test(StockList::class)
            ->call('openImport')
            ->set('importFile', $this->xlsxFile([
                ['name', 'sku', 'quantity', 'unit', 'price', 'min_stock'],
                ['Sabun', 'SBN-1', 20, 'pcs', 5000, 5],
                ['Pasta Gigi', 'PST-1', 3, 'tube', 8000, 5],
            ]))
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('importResult.created', 2)
            ->assertSet('importResult.skipped', 0)
            ->assertSet('importFile', null);

        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Sabun', 'status' => 'available']);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Pasta Gigi', 'status' => 'low_stock']);
    }

        public function test_import_reports_invalid_rows(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->call('openImport')
            ->set('importFile', $this->xlsxFile([
                ['name', 'quantity'],
                ['Valid', 1],
                ['Rusak', -5],
            ]))
            ->call('import')
            ->assertSet('importResult.created', 1)
            ->assertSet('importResult.skipped', 1)
            ->assertSee('Baris 3');
    }

    public function test_import_requires_a_file(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->call('import')
            ->assertHasErrors(['importFile' => 'required']);
    }

    public function test_import_rejects_files_that_are_not_xlsx(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->set('importFile', UploadedFile::fake()->create('stok.pdf', 10, 'application/pdf'))
            ->call('import')
            ->assertHasErrors(['importFile' => 'extensions']);
    }

    public function test_import_rejects_files_larger_than_two_megabytes(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->set('importFile', UploadedFile::fake()->create('besar.xlsx', 3000))
            ->call('import')
            ->assertHasErrors(['importFile' => 'max']);
    }

    public function test_import_reports_a_file_with_xlsx_name_but_invalid_content(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->call('openImport')
            ->set('importFile', UploadedFile::fake()->createWithContent('palsu.xlsx', 'bukan excel'))
            ->call('import')
            ->assertSet('importResult.created', 0)
            ->assertSee('tidak dapat dibaca');
    }

    public function test_admin_cannot_open_import_or_import_files(): void
    {
        $this->actingAdmin();

        Livewire::test(StockList::class)->call('openImport')->assertForbidden();

        Livewire::test(StockList::class)
            ->set('importFile', $this->xlsxFile([['name'], ['X']]))
            ->call('import')
            ->assertForbidden();

        $this->assertDatabaseCount('stocks', 0);
    }

    // ---------- Generate AI ----------

    public function test_ai_modal_opens_and_closes(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->call('openAi')
            ->assertSet('showAi', true)
            ->assertSet('aiCount', '5')
            ->call('closeAi')
            ->assertSet('showAi', false);
    }

    public function test_ai_generate_shows_preview_with_all_items_selected(): void
    {
        $this->actingMember();
        $this->fakeAi(fn (string $prompt, int $count) => [
            new GeneratedStockItem('Beras', 'BRS-1', 10, 'kg', 12000.0, 3, 'Beras putih'),
            new GeneratedStockItem('Gula', null, 5, 'kg', null, 2, null),
        ]);

        Livewire::test(StockList::class)
            ->call('openAi')
            ->set('aiPrompt', 'bahan pokok warung')
            ->set('aiCount', '2')
            ->call('generateAi')
            ->assertHasNoErrors()
            ->assertSet('aiError', null)
            ->assertCount('aiItems', 2)
            ->assertSet('aiItems.0.name', 'Beras')
            ->assertSet('aiItems.1.name', 'Gula')
            ->assertSet('aiSelected', [true, true])
            ->assertSee('Preview (2 item)');
    }

    public function test_ai_generate_passes_prompt_and_count_to_the_client(): void
    {
        $this->actingMember();
        $received = [];
        $this->fakeAi(function (string $prompt, int $count) use (&$received) {
            $received = [$prompt, $count];

            return [new GeneratedStockItem('Satu', null, 1, 'pcs', null, 5, null)];
        });

        Livewire::test(StockList::class)
            ->set('aiPrompt', '  alat tulis kantor  ')
            ->set('aiCount', '7')
            ->call('generateAi');

        $this->assertSame(['alat tulis kantor', 7], $received);
    }

    public function test_ai_generate_validates_input(): void
    {
        $this->actingMember();
        $this->fakeAi(fn () => []);

        Livewire::test(StockList::class)
            ->set('aiPrompt', '')
            ->call('generateAi')
            ->assertHasErrors(['aiPrompt' => 'required']);

        Livewire::test(StockList::class)
            ->set('aiPrompt', 'abc')
            ->call('generateAi')
            ->assertHasErrors(['aiPrompt' => 'min']);

        Livewire::test(StockList::class)
            ->set('aiPrompt', str_repeat('a', 501))
            ->call('generateAi')
            ->assertHasErrors(['aiPrompt' => 'max']);

        Livewire::test(StockList::class)
            ->set('aiPrompt', 'bahan pokok')
            ->set('aiCount', '0')
            ->call('generateAi')
            ->assertHasErrors(['aiCount' => 'between']);

        Livewire::test(StockList::class)
            ->set('aiPrompt', 'bahan pokok')
            ->set('aiCount', '11')
            ->call('generateAi')
            ->assertHasErrors(['aiCount' => 'between']);

        Livewire::test(StockList::class)
            ->set('aiPrompt', 'bahan pokok')
            ->set('aiCount', 'abc')
            ->call('generateAi')
            ->assertHasErrors(['aiCount' => 'integer']);
    }

        public function test_ai_generate_shows_error_when_the_client_fails(): void
    {
        $this->actingMember();
        $this->fakeAi(function () {
            throw new RuntimeException('Layanan AI sedang bermasalah.');
        });

        Livewire::test(StockList::class)
            ->call('openAi')
            ->set('aiPrompt', 'bahan pokok')
            ->call('generateAi')
            ->assertSet('aiError', 'Layanan AI sedang bermasalah.')
            ->assertSet('aiItems', [])
            ->assertSee('Layanan AI sedang bermasalah.');
    }

    public function test_ai_generate_is_rate_limited_per_user(): void
    {
        $user = $this->actingMember();
        $this->fakeAi(fn () => [new GeneratedStockItem('Satu', null, 1, 'pcs', null, 5, null)]);

        foreach (range(1, 10) as $ignored) {
            RateLimiter::hit('ai-generate:'.$user->id, 60);
        }

        Livewire::test(StockList::class)
            ->set('aiPrompt', 'bahan pokok')
            ->call('generateAi')
            ->assertSet('aiError', 'Terlalu banyak permintaan AI. Coba lagi sebentar lagi.')
            ->assertSet('aiItems', []);
    }

    public function test_ai_save_creates_only_selected_items(): void
    {
        $user = $this->actingMember();
        $this->fakeAi(fn () => [
            new GeneratedStockItem('Beras', 'BRS-1', 10, 'kg', 12000.0, 3, 'Beras putih'),
            new GeneratedStockItem('Gula', null, 0, 'kg', null, 2, null),
            new GeneratedStockItem('Minyak', 'MYK-1', 4, 'liter', 15000.0, 5, null),
        ]);

        Livewire::test(StockList::class)
            ->call('openAi')
            ->set('aiPrompt', 'bahan pokok warung')
            ->call('generateAi')
            ->set('aiSelected', [true, false, true])
            ->call('saveAi')
            ->assertHasNoErrors()
            ->assertSet('showAi', false)
            ->assertSet('aiItems', [])
            ->assertSee('2 stok dari AI berhasil disimpan.');

        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Beras', 'status' => 'available']);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Minyak', 'status' => 'low_stock']);
        $this->assertDatabaseMissing('stocks', ['name' => 'Gula']);
    }

    public function test_ai_save_requires_at_least_one_selected_item(): void
    {
        $this->actingMember();
        $this->fakeAi(fn () => [new GeneratedStockItem('Beras', null, 10, 'kg', null, 3, null)]);

        Livewire::test(StockList::class)
            ->set('aiPrompt', 'bahan pokok warung')
            ->call('generateAi')
            ->set('aiSelected', [false])
            ->call('saveAi')
            ->assertHasErrors('aiSelected');

        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_ai_save_revalidates_items_on_the_server(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->set('aiItems', [[
                'name' => '', 'sku' => null, 'quantity' => -5, 'unit' => 'pcs',
                'price' => null, 'min_stock' => 5, 'description' => null,
            ]])
            ->set('aiSelected', [true])
            ->call('saveAi')
            ->assertHasErrors(['aiItems.0.name', 'aiItems.0.quantity']);

        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_ai_save_ignores_selected_indexes_without_an_item(): void
    {
        $this->actingMember();

        Livewire::test(StockList::class)
            ->set('aiItems', [[
                'name' => 'Satu-satunya', 'sku' => null, 'quantity' => 1, 'unit' => 'pcs',
                'price' => null, 'min_stock' => 5, 'description' => null,
            ]])
            ->set('aiSelected', [0 => true, 5 => true])
            ->call('saveAi')
            ->assertHasNoErrors()
            ->assertSee('1 stok dari AI berhasil disimpan.');

        $this->assertDatabaseCount('stocks', 1);
    }

    public function test_admin_cannot_use_ai_generation(): void
    {
        $this->actingAdmin();
        $this->fakeAi(fn () => []);

        Livewire::test(StockList::class)->call('openAi')->assertForbidden();

        Livewire::test(StockList::class)
            ->set('aiPrompt', 'bahan pokok')
            ->call('generateAi')
            ->assertForbidden();

        Livewire::test(StockList::class)
            ->set('aiItems', [['name' => 'X', 'quantity' => 1, 'unit' => 'pcs', 'min_stock' => 5]])
            ->set('aiSelected', [true])
            ->call('saveAi')
            ->assertForbidden();

        $this->assertDatabaseCount('stocks', 0);
    }
}