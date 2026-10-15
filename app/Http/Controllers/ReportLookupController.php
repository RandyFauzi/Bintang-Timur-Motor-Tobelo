<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use App\Models\WorkType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportLookupController extends Controller
{
    public function workshops(Request $request): JsonResponse
    {
        return $this->store($request, Workshop::class, 'workshops');
    }

    public function workTypes(Request $request): JsonResponse
    {
        return $this->store($request, WorkType::class, 'work_types');
    }

    /** @param class-string<Workshop|WorkType> $model */
    private function store(Request $request, string $model, string $table): JsonResponse
    {
        $data = $request->validate(['names' => ['required', 'array', 'min:1', 'max:20'], 'names.*' => ['required', 'string', 'max:255', 'distinct:ignore_case', Rule::unique($table, 'name')]]);
        $records = DB::transaction(function () use ($data, $model): array {
            $records = [];
            foreach ($data['names'] as $name) {
                $record = $model::create(['name' => trim($name)]);
                $records[] = ['id' => $record->id, 'name' => $record->name];
            }

            return $records;
        });

        return response()->json(['data' => $records, 'message' => 'Data baru berhasil ditambahkan.'], 201);
    }
}
