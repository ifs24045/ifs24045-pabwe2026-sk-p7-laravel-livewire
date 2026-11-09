<?php

namespace Tests\Feature\Stock;

use App\Features\Stock\Livewire\StockDetail;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class StockDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    private function actingMember(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function stockFor(User $user, array $attributes = []): Stock
    {
        return Stock::factory()->create(array_merge(['user_id' => $user->id], $attributes));
    }

    private function detail(Stock $stock): Testable
    {
        return Livewire::test(StockDetail::class, ['stock' => $stock]);
    }

    // ---------- Tampilan ----------

    public function test_it_shows_the_stock_details(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, [
            'name' => 'Beras Premium', 'sku' => 'BRS-001', 'quantity' => 50, 'unit' => 'karung',
            'price' => 68000, 'min_stock' => 10, 'status' => 'available',
        ]);

        $this->detail($stock)
            ->assertSee('Beras Premium')
            ->assertSee('BRS-001')
            ->assertSee('50 karung')
            ->assertSee('Rp 68.000')
            ->assertSee('Tersedia')
            ->assertSee('Belum ada cover');
    }

    public function test_it_shows_placeholders_for_missing_optional_data(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['sku' => null, 'price' => null, 'description' => null, 'cover_image_path' => null]);

        $this->detail($stock)
            ->assertSee('Belum ada deskripsi.')
            ->assertSee('Belum ada cover');
    }

    public function test_it_shows_the_cover_image_when_present(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['cover_image_path' => 'stock-covers/kopi.jpg']);

        $this->detail($stock)
            ->assertSee('storage/stock-covers/kopi.jpg', false)
            ->assertDontSee('Belum ada cover');
    }

    public function test_description_is_rendered_as_markdown(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['description' => "**Tebal** dan *miring*\n\n- satu\n- dua"]);

        $this->detail($stock)
            ->assertSeeHtml('<strong>Tebal</strong>')
            ->assertSeeHtml('<em>miring</em>')
            ->assertSeeHtml('<li>satu</li>')
            ->assertSeeHtml('<li>dua</li>');
    }

    public function test_markdown_strips_raw_html_and_unsafe_links(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, [
            'description' => "Halo <script>alert('x')</script> <img src=x onerror=alert(1)> [klik](javascript:alert(1))",
        ]);

        $this->detail($stock)
            ->assertDontSeeHtml('<script>alert')
            ->assertDontSeeHtml('onerror')
            ->assertDontSeeHtml('javascript:alert')
            ->assertSee('Halo');
    }

    // ---------- Akses ----------

    public function test_owner_can_open_the_detail(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user);

        $this->detail($stock)->assertOk();
    }

    public function test_another_member_cannot_open_the_detail(): void
    {
        $this->actingMember();
        $others = Stock::factory()->create();

        $this->detail($others)->assertForbidden();
    }

    public function test_admin_cannot_open_the_detail(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $stock = Stock::factory()->create();

        $this->detail($stock)->assertForbidden();
    }

    // ---------- Jumlah ----------

    public function test_quantity_buttons_update_quantity_and_status(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['quantity' => 5, 'min_stock' => 5, 'status' => 'low_stock']);

        $this->detail($stock)
            ->call('adjust', 1)
            ->assertSee('6 '.$stock->unit)
            ->assertSee('Tersedia');

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 6, 'status' => 'available']);
    }

    public function test_decrease_never_goes_below_zero(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['quantity' => 1, 'min_stock' => 5, 'status' => 'low_stock']);

        $this->detail($stock)->call('adjust', -1)->call('adjust', -1);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 0, 'status' => 'out_of_stock']);
    }

    // ---------- Ubah ----------

    public function test_start_edit_fills_the_form(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, [
            'name' => 'Beras', 'sku' => 'BRS-1', 'description' => 'Enak', 'quantity' => 7,
            'unit' => 'kg', 'price' => 12500, 'min_stock' => 4,
        ]);

        $this->detail($stock)
            ->call('startEdit')
            ->assertSet('editing', true)
            ->assertSet('name', 'Beras')
            ->assertSet('sku', 'BRS-1')
            ->assertSet('description', 'Enak')
            ->assertSet('quantity', '7')
            ->assertSet('unit', 'kg')
            ->assertSet('price', '12500.00')
            ->assertSet('minStock', '4');
    }

    public function test_start_edit_uses_blank_strings_for_null_columns(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['sku' => null, 'description' => null, 'price' => null]);

        $this->detail($stock)
            ->call('startEdit')
            ->assertSet('sku', '')
            ->assertSet('description', '')
            ->assertSet('price', '');
    }

    public function test_cancel_edit_leaves_edit_mode_and_clears_pending_cover_changes(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user);

        $this->detail($stock)
            ->call('startEdit')
            ->set('removeCover', true)
            ->call('cancelEdit')
            ->assertSet('editing', false)
            ->assertSet('removeCover', false)
            ->assertSet('cover', null);
    }

    public function test_save_updates_the_stock_and_recalculates_status(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['name' => 'Lama', 'quantity' => 50, 'min_stock' => 5, 'status' => 'available']);

        $this->detail($stock)
            ->call('startEdit')
            ->set('name', 'Baru')
            ->set('quantity', '2')
            ->set('description', '**Baru**')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editing', false)
            ->assertSee('Stok berhasil diperbarui.')
            ->assertSee('Baru');

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id, 'name' => 'Baru', 'quantity' => 2, 'status' => 'low_stock', 'description' => '**Baru**',
        ]);
    }

    public function test_blank_optional_fields_are_stored_as_null(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['sku' => 'ADA-1', 'description' => 'Ada', 'price' => 1000]);

        $this->detail($stock)
            ->call('startEdit')
            ->set('sku', '')
            ->set('description', '')
            ->set('price', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'sku' => null, 'description' => null, 'price' => null]);
    }

    public function test_save_validates_the_form(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['name' => 'Tetap']);

        $this->detail($stock)
            ->call('startEdit')
            ->set('name', '')
            ->set('quantity', '-1')
            ->set('unit', '')
            ->set('price', 'abc')
            ->set('minStock', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'quantity' => 'min', 'unit' => 'required', 'price' => 'numeric', 'minStock' => 'required'])
            ->assertSet('editing', true);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'name' => 'Tetap']);
    }

    // ---------- Cover ----------

    public function test_uploading_a_cover_stores_it_and_shows_it(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['cover_image_path' => null]);

        $this->detail($stock)
            ->call('startEdit')
            ->set('cover', UploadedFile::fake()->image('cover.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $path = $stock->fresh()->cover_image_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('stock-covers/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_the_cover_deletes_the_old_file(): void
    {
        Storage::disk('public')->put('stock-covers/lama.jpg', 'x');
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['cover_image_path' => 'stock-covers/lama.jpg']);

        $this->detail($stock)
            ->call('startEdit')
            ->set('cover', UploadedFile::fake()->image('baru.png'))
            ->call('save')
            ->assertHasNoErrors();

        $path = $stock->fresh()->cover_image_path;

        $this->assertNotSame('stock-covers/lama.jpg', $path);
        Storage::disk('public')->assertMissing('stock-covers/lama.jpg');
        Storage::disk('public')->assertExists($path);
    }

    public function test_saving_without_a_new_cover_keeps_the_current_one(): void
    {
        Storage::disk('public')->put('stock-covers/lama.jpg', 'x');
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['cover_image_path' => 'stock-covers/lama.jpg']);

        $this->detail($stock)
            ->call('startEdit')
            ->set('name', 'Ganti Nama')
            ->call('save');

        $this->assertSame('stock-covers/lama.jpg', $stock->fresh()->cover_image_path);
        Storage::disk('public')->assertExists('stock-covers/lama.jpg');
    }

    public function test_the_cover_can_be_removed(): void
    {
        Storage::disk('public')->put('stock-covers/lama.jpg', 'x');
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['cover_image_path' => 'stock-covers/lama.jpg']);

        $this->detail($stock)
            ->call('startEdit')
            ->set('removeCover', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Belum ada cover');

        $this->assertNull($stock->fresh()->cover_image_path);
        Storage::disk('public')->assertMissing('stock-covers/lama.jpg');
    }

    public function test_cover_must_be_an_image(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user);

        $this->detail($stock)
            ->call('startEdit')
            ->set('cover', UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasErrors(['cover' => 'image']);
    }

    public function test_cover_must_not_exceed_two_megabytes(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user);

        $this->detail($stock)
            ->call('startEdit')
            ->set('cover', UploadedFile::fake()->image('besar.jpg')->size(3000))
            ->call('save')
            ->assertHasErrors(['cover' => 'max']);

        $this->assertNull($stock->fresh()->cover_image_path);
    }

    // ---------- Hapus ----------

    public function test_delete_removes_the_stock_and_its_cover_and_redirects(): void
    {
        Storage::disk('public')->put('stock-covers/hapus.jpg', 'x');
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['cover_image_path' => 'stock-covers/hapus.jpg']);

        $this->detail($stock)
            ->call('delete')
            ->assertRedirect(route('stocks.index'));

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
        Storage::disk('public')->assertMissing('stock-covers/hapus.jpg');
        $this->assertSame('Stok berhasil dihapus.', session('status'));
    }

    public function test_delete_a_stock_without_cover(): void
    {
        $user = $this->actingMember();
        $stock = $this->stockFor($user, ['cover_image_path' => null]);

        $this->detail($stock)->call('delete')->assertRedirect(route('stocks.index'));

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }
}