<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserFilterRequest;
use App\Http\Requests\UserManagementRequest;
use App\Models\User;
use App\Services\CsvExportService;
use App\Services\UserManagementService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(UserFilterRequest $request, UserManagementService $management): View
    {
        $query = $management->query($request->validated());
        $summary = (clone $query)->selectRaw('COUNT(*) as total, COALESCE(SUM(CASE WHEN role = ? THEN 1 ELSE 0 END),0) as admins, COALESCE(SUM(CASE WHEN role = ? THEN 1 ELSE 0 END),0) as staff', ['admin', 'staff'])->toBase()->first();
        $editId = $request->input('edit') ?? old('_user_id');

        return view('users.index', [
            'users' => $query->latest('id')->paginate((int) ($request->validated('per_page') ?? 10))->withQueryString(),
            'summary' => $summary,
            'editing' => filter_var($editId, FILTER_VALIDATE_INT) ? User::findOrFail($editId) : null,
        ]);
    }

    public function store(UserManagementRequest $request, UserManagementService $management): RedirectResponse
    {
        $management->save($request->validated(), $request->user());

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(UserManagementRequest $request, User $user, UserManagementService $management): RedirectResponse
    {
        $management->save($request->validated(), $request->user(), $user);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function export(UserFilterRequest $request, UserManagementService $management, CsvExportService $csv): StreamedResponse
    {
        $rows = $management->query($request->validated())->orderBy('id')->lazy(200)->map(fn (User $user): array => [$user->name, $user->email, $user->role === 'admin' ? 'Admin' : 'Staff Kasir', $user->created_at->format('Y-m-d H:i')]);

        return $csv->download('pengguna-'.now()->format('Ymd').'.csv', ['Nama', 'Email', 'Hak Akses', 'Tanggal Bergabung'], $rows);
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('manage-users');
        if ($user->id === auth()->id() || $user->email === 'randyfauzi24@gmail.com') {
            throw ValidationException::withMessages(['user' => 'Akun utama atau akun sendiri tidak dapat dihapus.']);
        }
        try {
            $user->delete();
        } catch (QueryException) {
            throw ValidationException::withMessages(['user' => 'Akun terhubung dengan transaksi atau aktivitas dan tidak dapat dihapus.']);
        }

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus.');
    }
}
