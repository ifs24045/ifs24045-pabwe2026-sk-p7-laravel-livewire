<?php

namespace Tests\Unit\Policies;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Policies\StockPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockPolicyTest extends TestCase
{
    use RefreshDatabase;

    private StockPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new StockPolicy();
    }

    public function test_only_non_admin_can_view_any_and_create(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->policy->viewAny($member));
        $this->assertTrue($this->policy->create($member));
        $this->assertFalse($this->policy->viewAny($admin));
        $this->assertFalse($this->policy->create($admin));
    }

    public function test_owner_can_view_update_and_delete(): void
    {
        $owner = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($this->policy->view($owner, $stock));
        $this->assertTrue($this->policy->update($owner, $stock));
        $this->assertTrue($this->policy->delete($owner, $stock));
    }

    public function test_other_user_cannot_view_update_or_delete(): void
    {
        $stock = Stock::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($this->policy->view($other, $stock));
        $this->assertFalse($this->policy->update($other, $stock));
        $this->assertFalse($this->policy->delete($other, $stock));
    }

    public function test_admin_cannot_modify_member_stock(): void
    {
        $stock = Stock::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertFalse($this->policy->update($admin, $stock));
        $this->assertFalse($this->policy->delete($admin, $stock));
    }
}