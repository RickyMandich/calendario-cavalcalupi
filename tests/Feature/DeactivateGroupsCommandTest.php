<?php

namespace Tests\Feature;

use App\Models\Group;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivateGroupsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivates_groups_created_before_august_15(): void
    {
        // Group created in July
        $oldGroup = Group::create(['name' => 'Vecchio Gruppo', 'is_active' => true]);
        $oldGroup->created_at = Carbon::create(now()->year, 7, 10, 10, 0, 0, 'Europe/Rome');
        $oldGroup->save();

        // Group created after August 15 (e.g. September)
        $newGroup = Group::create(['name' => 'Nuovo Gruppo', 'is_active' => true]);
        $newGroup->created_at = Carbon::create(now()->year, 9, 1, 10, 0, 0, 'Europe/Rome');
        $newGroup->save();

        $this->artisan('app:deactivate-groups')
            ->assertSuccessful();

        $this->assertFalse($oldGroup->fresh()->is_active);
        $this->assertTrue($newGroup->fresh()->is_active);
    }
}
