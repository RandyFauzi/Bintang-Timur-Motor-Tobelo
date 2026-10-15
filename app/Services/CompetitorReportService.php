<?php

namespace App\Services;

use App\Models\CompetitorObservation;
use App\Models\Workshop;
use App\Models\WorkType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class CompetitorReportService
{
    /** @param array<string,mixed> $filters */
    public function query(array $filters): Builder
    {
        $start = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $filters['end_date'] ?? now()->toDateString();
        $query = CompetitorObservation::query()->with(['workshop', 'workTypes' => function (BelongsToMany $workTypes) use ($filters): void {
            if (! empty($filters['work_type_ids'])) {
                $workTypes->whereIn('work_types.id', $filters['work_type_ids']);
            }
        }])->where('observed_on', '>=', $start)
            ->where('observed_on', '<', CarbonImmutable::parse($end)->addDay()->toDateString());
        if (! empty($filters['workshop_id'])) {
            $query->where('workshop_id', $filters['workshop_id']);
        }
        if (isset($filters['hour']) && $filters['hour'] !== '') {
            $query->where('observed_hour', $filters['hour']);
        }
        if (! empty($filters['work_type_ids'])) {
            $query->whereHas('workTypes', fn (Builder $observationQuery) => $observationQuery->whereIn('work_types.id', $filters['work_type_ids']));
        }
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn (Builder $observationQuery) => $observationQuery->whereHas('workshop', fn (Builder $workshopQuery) => $workshopQuery->where('name', 'like', $term))->orWhereHas('workTypes', fn (Builder $workTypeQuery) => $workTypeQuery->where('name', 'like', $term)));
        }

        return $query->orderByDesc('observed_on')->orderByDesc('observed_hour')->orderByDesc('id');
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function data(array $filters): array
    {
        $query = $this->query($filters);
        $items = $this->workItems($filters);
        $range = $this->dateRange($filters);
        $typeFilters = array_replace($filters, [
            'start_date' => $filters['type_start_date'] ?? $range['start'],
            'end_date' => $filters['type_end_date'] ?? $range['end'],
        ]);
        foreach (['type_hour' => 'hour', 'type_workshop_id' => 'workshop_id'] as $field => $target) {
            if (array_key_exists($field, $filters)) {
                $typeFilters[$target] = $filters[$field];
            }
        }
        $workshopFilters = array_replace($filters, [
            'start_date' => $filters['workshop_start_date'] ?? $range['start'],
            'end_date' => $filters['workshop_end_date'] ?? $range['end'],
        ]);
        $byType = $this->workItems($typeFilters)->join('work_types', 'work_types.id', '=', 'items.work_type_id')->select('work_types.name')->selectRaw('SUM(quantity) as value')->groupBy('work_types.id', 'work_types.name')->orderByDesc('value')->get();
        $byWorkshop = $this->workItems($workshopFilters)->join('competitor_observations as observations', 'observations.id', '=', 'items.competitor_observation_id')
            ->join('workshops', 'workshops.id', '=', 'observations.workshop_id')->select('workshops.name')->selectRaw('SUM(quantity) as value')->groupBy('workshops.id', 'workshops.name')->orderByDesc('value')->get();
        $workshopCount = (clone $query)->reorder()->distinct()->count('workshop_id');

        return [
            'observations' => $query->paginate((int) ($filters['per_page'] ?? 10))->withQueryString(),
            'range' => $range, 'typeRange' => $this->dateRange($typeFilters), 'workshopRange' => $this->dateRange($workshopFilters),
            'totalWork' => (int) $items->sum('quantity'), 'typeTotal' => (int) $byType->sum('value'),
            'workshopCount' => $workshopCount,
            'typeChart' => $byType->map(fn (\stdClass $row): array => ['name' => $row->name, 'value' => (int) $row->value])->all(),
            'workshopChart' => $byWorkshop, 'workshops' => Workshop::orderBy('name')->get(), 'workTypes' => WorkType::orderBy('name')->get(),
            'editing' => ! empty($filters['edit']) ? CompetitorObservation::with('workTypes')->findOrFail($filters['edit']) : null,
        ];
    }

    /** @param array<string,mixed> $filters */
    private function workItems(array $filters): QueryBuilder
    {
        $ids = $this->query($filters)->reorder()->select('competitor_observations.id')->toBase();
        $items = DB::table('competitor_observation_work_type as items')->whereIn('competitor_observation_id', $ids);
        if (! empty($filters['work_type_ids'])) {
            $items->whereIn('work_type_id', $filters['work_type_ids']);
        }

        return $items;
    }

    /** @param array<string,mixed> $filters @return array{start:string,end:string,label:string} */
    private function dateRange(array $filters): array
    {
        $start = CarbonImmutable::parse($filters['start_date'] ?? now()->startOfMonth());
        $end = CarbonImmutable::parse($filters['end_date'] ?? now());

        return ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'label' => $start->locale('id')->translatedFormat('j M Y').' - '.$end->locale('id')->translatedFormat('j M Y')];
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, int $userId, ?CompetitorObservation $observation = null): CompetitorObservation
    {
        return DB::transaction(function () use ($data, $userId, $observation): CompetitorObservation {
            $attributes = ['workshop_id' => $data['workshop_id'], 'observed_on' => $data['observed_on'], 'observed_hour' => $data['observed_hour']];
            if ($observation === null) {
                $observation = CompetitorObservation::create($attributes + ['created_by' => $userId]);
            } else {
                $observation->update($attributes);
            }
            $items = [];
            foreach ($data['items'] as $item) {
                $items[$item['work_type_id']] = ['quantity' => $item['quantity']];
            }
            $observation->workTypes()->sync($items);

            return $observation;
        });
    }
}
