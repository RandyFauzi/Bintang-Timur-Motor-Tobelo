<?php

namespace Tests\Feature;

use App\Models\CompetitorObservation;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitorReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_or_write_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->postJson(route('reports.observations.store'), [])->assertUnauthorized();
        $this->postJson(route('reports.workshops.store'), [])->assertUnauthorized();
    }

    public function test_empty_report_and_creation_dialog_render_without_sample_data(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reports.index', ['create' => 1]))->assertOk()
            ->assertViewHas('totalWork', 0)->assertSee('Belum ada pengamatan')->assertSee('Catat Pengerjaan Kompetitor');
    }

    public function test_observation_saves_multiple_types_and_updates_replace_the_selection(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->create();
        $types = WorkType::factory()->count(2)->create();
        $payload = ['workshop_id' => $workshop->id, 'observed_on' => now()->toDateString(), 'observed_hour' => 15, 'items' => [
            ['work_type_id' => $types[0]->id, 'quantity' => 3], ['work_type_id' => $types[1]->id, 'quantity' => 2],
        ]];
        $this->actingAs($user)->post(route('reports.observations.store'), $payload)->assertRedirect(route('reports.index'))->assertSessionHasNoErrors();
        $record = CompetitorObservation::firstOrFail();
        $this->assertSame($user->id, $record->created_by);
        $this->assertSame(5, $record->workTypes->sum('pivot.quantity'));
        $payload['items'] = [['work_type_id' => $types[1]->id, 'quantity' => 7]];
        $this->patch(route('reports.observations.update', $record), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame([$types[1]->id], $record->workTypes->modelKeys());
        $this->assertSame(7, $record->workTypes->sum('pivot.quantity'));
        $this->get(route('reports.index', ['edit' => $record->id]))->assertOk()->assertSee('Edit Pengamatan Pengerjaan');
    }

    public function test_report_statistics_filters_and_export_describe_the_same_selected_types(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        $user = User::factory()->create();
        $workshop = Workshop::factory()->create(['name' => 'Bengkel Fokus']);
        $oil = WorkType::factory()->create(['name' => 'Oli']);
        $brake = WorkType::factory()->create(['name' => 'Rem']);
        $record = CompetitorObservation::factory()->for($workshop)->create(['observed_on' => '2026-10-05', 'observed_hour' => 11, 'created_by' => $user->id]);
        $record->workTypes()->attach([$oil->id => ['quantity' => 3], $brake->id => ['quantity' => 5]]);
        $old = CompetitorObservation::factory()->for($workshop)->create(['observed_on' => '2026-09-05', 'created_by' => $user->id]);
        $old->workTypes()->attach($oil, ['quantity' => 100]);
        $filters = ['start_date' => '2026-10-01', 'end_date' => '2026-10-10', 'workshop_id' => $workshop->id, 'hour' => 11, 'work_type_ids' => [$oil->id], 'view' => 'cards'];
        $response = $this->actingAs($user)->get(route('reports.index', $filters));
        $response->assertOk()->assertViewHas('totalWork', 3)->assertViewHas('workshopCount', 1)->assertSee('observation-card', false);
        $this->assertSame(1, $response->viewData('observations')->total());
        $this->assertSame(3, $response->viewData('observations')->first()->workTypes->sum('pivot.quantity'));
        $csv = $this->get(route('reports.export', $filters))->assertDownload()->streamedContent();
        $this->assertStringContainsString('Oli (3)', $csv);
        $this->assertStringNotContainsString('Rem (5)', $csv);
    }

    public function test_bulk_lookup_creation_returns_ids_without_leaving_the_form(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('reports.workshops.store'), ['names' => ['A7X', 'NSAN']])
            ->assertCreated()->assertJsonPath('data.0.name', 'A7X')->assertJsonCount(2, 'data');
        $this->postJson(route('reports.work-types.store'), ['names' => ['Karbu', 'Rantai']])->assertCreated()->assertJsonCount(2, 'data');
        $this->assertDatabaseCount('workshops', 2);
        $this->assertDatabaseCount('work_types', 2);
        $this->postJson(route('reports.work-types.store'), ['names' => ['Karbu', 'Baru']])->assertUnprocessable();
        $this->assertDatabaseMissing('work_types', ['name' => 'Baru']);
    }

    public function test_duplicate_types_invalid_quantities_and_future_observations_do_not_save(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->create();
        $type = WorkType::factory()->create();
        $this->actingAs($user)->postJson(route('reports.observations.store'), [
            'workshop_id' => $workshop->id, 'observed_on' => now()->addDay()->toDateString(), 'observed_hour' => 24,
            'items' => [['work_type_id' => $type->id, 'quantity' => 0], ['work_type_id' => $type->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['observed_on', 'observed_hour', 'items.0.quantity', 'items.0.work_type_id']);
        $this->assertDatabaseCount('competitor_observations', 0);
        $this->assertDatabaseCount('competitor_observation_work_type', 0);
    }

    public function test_financial_report_remains_accessible(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reports.finance'))->assertOk()->assertSee('Laporan Keuangan');
    }

    public function test_chart_dates_and_midnight_hour_can_be_filtered_independently_of_the_table(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        $user = User::factory()->create();
        $workshop = Workshop::factory()->create();
        $type = WorkType::factory()->create();
        $current = CompetitorObservation::factory()->for($workshop)->create(['created_by' => $user->id, 'observed_on' => '2026-10-05', 'observed_hour' => 11]);
        $current->workTypes()->attach($type, ['quantity' => 3]);
        $old = CompetitorObservation::factory()->for($workshop)->create(['created_by' => $user->id, 'observed_on' => '2026-09-05', 'observed_hour' => 0]);
        $old->workTypes()->attach($type, ['quantity' => 9]);
        $later = CompetitorObservation::factory()->for($workshop)->create(['created_by' => $user->id, 'observed_on' => '2026-09-05', 'observed_hour' => 12]);
        $later->workTypes()->attach($type, ['quantity' => 20]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'start_date' => '2026-10-05', 'end_date' => '2026-10-05',
            'type_start_date' => '2026-09-05', 'type_end_date' => '2026-09-05', 'type_hour' => 0, 'type_workshop_id' => $workshop->id,
            'workshop_start_date' => '2026-09-05', 'workshop_end_date' => '2026-09-05',
        ]));

        $response->assertOk()->assertViewHas('totalWork', 3)->assertViewHas('typeTotal', 9)->assertViewHas('workshopCount', 1);
        $this->assertSame(1, $response->viewData('observations')->total());
        $this->assertSame(29, (int) $response->viewData('workshopChart')->sum('value'));
    }

    public function test_chart_filters_reject_reversed_or_excessively_long_date_ranges(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));

        $this->actingAs(User::factory()->create())->getJson(route('reports.index', [
            'type_start_date' => '2026-10-10', 'type_end_date' => '2026-10-01',
            'workshop_start_date' => '2025-01-01', 'workshop_end_date' => '2026-10-15',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['type_end_date', 'workshop_end_date']);
    }

    public function test_staff_users_can_access_competitor_reports_and_create_observations(): void
    {
        $staff = User::factory()->staff()->create();
        $workshop = Workshop::factory()->create();
        $type = WorkType::factory()->create();

        $this->actingAs($staff)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Riset Kompetitor')
            ->assertDontSee('Lihat laporan keuangan');

        $payload = [
            'workshop_id' => $workshop->id,
            'observed_on' => now()->toDateString(),
            'observed_hour' => 11,
            'items' => [
                ['work_type_id' => $type->id, 'quantity' => 4],
            ],
        ];

        $this->actingAs($staff)->post(route('reports.observations.store'), $payload)
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('competitor_observations', [
            'created_by' => $staff->id,
            'workshop_id' => $workshop->id,
        ]);
    }

    public function test_staff_users_cannot_access_financial_reports(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('reports.finance'))->assertForbidden();
    }
}
