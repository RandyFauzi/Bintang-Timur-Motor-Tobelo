<?php

namespace Tests\Feature;

use App\Models\CompetitorObservation;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmptyCompetitorObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_observation_is_saved_and_visible_without_inflating_totals(): void
    {
        $workshop = Workshop::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->post(route('reports.observations.store'), [
            'workshop_id' => $workshop->id, 'observed_on' => now()->toDateString(),
            'observed_hour' => 15, 'no_work' => 1,
        ])->assertSessionHasNoErrors();
        $record = CompetitorObservation::firstOrFail();
        $this->assertCount(0, $record->workTypes);
        foreach (['list', 'cards'] as $view) {
            $this->get(route('reports.index', ['view' => $view]))->assertOk()
                ->assertSee('Kosong (0 pengerjaan)')->assertViewHas('totalWork', 0)
                ->assertViewHas('workshopCount', 1);
        }
        $this->get(route('reports.index', ['edit' => $record->id]))->assertOk();
        $this->assertStringContainsString('Kosong (0 pengerjaan)', $this->get(route('reports.export'))->assertOk()->streamedContent());
    }

    public function test_existing_work_can_be_changed_to_empty_and_back(): void
    {
        $this->actingAs(User::factory()->create());
        $record = CompetitorObservation::factory()->create();
        $type = WorkType::factory()->create();
        $record->workTypes()->attach($type, ['quantity' => 1]);
        $base = ['workshop_id' => $record->workshop_id, 'observed_on' => now()->toDateString(), 'observed_hour' => 15];
        $this->patch(route('reports.observations.update', $record), $base + ['no_work' => 1])
            ->assertSessionHasNoErrors();
        $this->assertCount(0, $record->fresh()->workTypes);
        $this->patch(route('reports.observations.update', $record), $base + [
            'no_work' => 0, 'items' => [['work_type_id' => $type->id, 'quantity' => 3]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, $record->fresh()->workTypes->sum('pivot.quantity'));
    }

    public function test_ambiguous_empty_observation_with_positive_work_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('reports.observations.store'), [
            'workshop_id' => Workshop::factory()->create()->id, 'observed_on' => now()->toDateString(),
            'observed_hour' => 15, 'no_work' => 1,
            'items' => [['work_type_id' => WorkType::factory()->create()->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('competitor_observations', 0);
    }
}
