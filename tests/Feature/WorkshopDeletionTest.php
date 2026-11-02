<?php

namespace Tests\Feature;

use App\Models\CompetitorObservation;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_workshop_disappears_from_all_report_options(): void
    {
        $workshop = Workshop::factory()->create(['name' => 'Bengkel Demo Hapus']);
        $other = Workshop::factory()->create(['name' => 'Bengkel Tetap']);
        $this->actingAs(User::factory()->create());
        $this->get(route('reports.index'))->assertOk()->assertSee('Hapus bengkel Bengkel Demo Hapus');
        $this->delete(route('reports.workshops.destroy', $workshop))
            ->assertRedirect(route('reports.index'))->assertSessionHasNoErrors();
        $this->assertModelMissing($workshop);
        $this->assertModelExists($other);
        $response = $this->get(route('reports.index'))->assertOk()
            ->assertDontSee('Bengkel Demo Hapus')->assertSee('Bengkel Tetap');
        $this->assertSame([$other->id], $response->viewData('workshops')->pluck('id')->all());
        $this->delete(route('reports.workshops.destroy', $workshop))->assertNotFound();
    }

    public function test_workshop_with_observations_is_preserved_until_its_observations_are_deleted(): void
    {
        $workshop = Workshop::factory()->create();
        $observation = CompetitorObservation::factory()->for($workshop)->create();
        $this->actingAs(User::factory()->create());
        $this->delete(route('reports.workshops.destroy', $workshop))
            ->assertRedirect(route('reports.index'))->assertSessionHasErrors('workshop');
        $this->assertModelExists($workshop);
        $this->assertModelExists($observation);
        $this->delete(route('reports.observations.destroy', $observation))->assertRedirect();
        $this->delete(route('reports.workshops.destroy', $workshop))->assertSessionHasNoErrors();
        $this->assertModelMissing($workshop);
    }

    public function test_guest_cannot_delete_a_workshop(): void
    {
        $workshop = Workshop::factory()->create();
        $this->delete(route('reports.workshops.destroy', $workshop))->assertRedirect(route('login'));
        $this->assertModelExists($workshop);
    }
}
