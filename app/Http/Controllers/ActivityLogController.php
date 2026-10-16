<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityLogFilterRequest;
use App\Models\ActivityLog;
use App\Services\CsvExportService;
use App\Services\ExcelExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    /** @param array<string, mixed> $filters */
    private function query(array $filters): Builder
    {
        $query = ActivityLog::query();
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn (Builder $query) => $query->where('actor_name', 'like', $search)->orWhere('route_name', 'like', $search)->orWhere('request_id', 'like', $search));
        }
        foreach (['user_id', 'module', 'action', 'result'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['start_date'])) {
            $query->where('occurred_at', '>=', $filters['start_date'].' 00:00:00');
        }
        if (! empty($filters['end_date'])) {
            $query->where('occurred_at', '<=', $filters['end_date'].' 23:59:59.999999');
        }

        return $query;
    }

    public function index(ActivityLogFilterRequest $request): View
    {
        $filters = $request->validated();
        $query = $this->query($filters);
        $summary = ['total' => (clone $query)->count(), 'failed' => (clone $query)->whereIn('result', ['failed', 'denied'])->count(), 'users' => (clone $query)->whereNotNull('user_id')->distinct()->count('user_id')];
        $logs = $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 25)->withQueryString()->appends(['detail' => null]);
        $detail = ! empty($filters['detail']) ? ActivityLog::findOrFail($filters['detail']) : null;

        return view('activity-logs.index', ['logs' => $logs, 'summary' => $summary, 'detail' => $detail, 'users' => ActivityLog::whereNotNull('user_id')->select(['user_id as id', 'actor_name as name'])->distinct()->orderBy('name')->limit(500)->get(), 'actions' => ActivityLog::ACTIONS, 'results' => ActivityLog::RESULTS, 'modules' => ActivityLog::MODULES]);
    }

    public function export(ActivityLogFilterRequest $request, CsvExportService $csv, ExcelExportService $excel): StreamedResponse
    {
        $rows = $this->query($request->validated())->orderByDesc('id')->lazyByIdDesc(200)->map(fn (ActivityLog $log): array => [$log->request_id, $log->occurred_at->format('Y-m-d H:i:s.u').' '.config('app.timezone'), $log->actor_name, $log->user_id, $log->actor_role, ActivityLog::MODULES[$log->module] ?? $log->module, ActivityLog::ACTIONS[$log->action] ?? $log->action, ActivityLog::RESULTS[$log->result] ?? $log->result, $log->route_name, $log->method, $log->status_code, $log->ip_address, json_encode($log->changes, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]);
        $headers = ['ID Permintaan', 'Waktu', 'Akun', 'ID Akun', 'Peran', 'Modul', 'Aktivitas', 'Hasil', 'Rute', 'Metode', 'HTTP', 'Alamat IP', 'Perubahan'];

        if ($request->query('format') === 'xlsx' || $request->query('format') === 'excel') {
            return $excel->download(
                'log-aktivitas-'.now()->format('Ymd').'.xlsx',
                'AUDIT LOG AKTIVITAS SISTEM',
                $headers,
                $rows,
                'Catatan Jejak Operasional & Keamanan Bintang Timur Motor'
            );
        }

        return $csv->download('log-aktivitas-'.now()->format('Ymd').'.csv', $headers, $rows);
    }
}
