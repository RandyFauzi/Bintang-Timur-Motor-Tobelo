<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    public const ACTIONS = ['access_denied' => 'Akses Ditolak', 'request_failed' => 'Permintaan Gagal', 'export' => 'Export Data', 'create' => 'Tambah Data', 'update' => 'Ubah Data', 'delete' => 'Hapus Data', 'submit' => 'Kirim Tindakan', 'login' => 'Login', 'logout' => 'Logout', 'login_failed' => 'Login Gagal', 'register' => 'Registrasi Akun', 'password_reset' => 'Reset Password', 'password_reset_request' => 'Minta Reset Password', 'checkout' => 'Transaksi Kasir', 'scan' => 'Pindai Produk', 'cancel' => 'Batalkan Data', 'bulk' => 'Ubah Massal', 'undo' => 'Kembalikan Perubahan (Undo)'];

    public const RESULTS = ['success' => 'Berhasil', 'failed' => 'Gagal', 'denied' => 'Ditolak'];

    public const MODULES = ['dashboard' => 'Dashboard', 'products' => 'Stok & Produk', 'distributors' => 'Distributor', 'returns' => 'Retur Produk', 'pos' => 'Kasir / POS', 'transactions' => 'Histori Transaksi', 'reports' => 'Laporan & Riset', 'finance' => 'Laporan Keuangan', 'research' => 'Riset Kompetitor', 'users' => 'Pengguna', 'profile' => 'Pengaturan', 'business' => 'Identitas Usaha', 'activity-logs' => 'Log Aktivitas', 'auth' => 'Autentikasi', 'other' => 'Lainnya'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'metadata' => 'array', 'occurred_at' => 'immutable_datetime', 'undone_at' => 'immutable_datetime'];
    }

    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }

    public function canBeUndone(): bool
    {
        if ($this->isUndone()) {
            return false;
        }

        $recordedChanges = (array) ($this->getAttribute('changes') ?? []);

        if ($this->result !== 'success' || empty($recordedChanges)) {
            return false;
        }

        if (! empty($this->metadata['changes_truncated'])) {
            return false;
        }

        if ($this->action === 'undo') {
            return false;
        }

        return true;
    }

    public function undoEligibilityReason(): ?string
    {
        if ($this->isUndone()) {
            return 'Sudah dikembalikan (di-undo)';
        }

        if ($this->action === 'undo') {
            return 'Aktivitas undo tidak dapat di-undo kembali';
        }

        if ($this->result !== 'success') {
            return 'Aktivitas tidak selesai dengan berhasil';
        }

        $recordedChanges = (array) ($this->getAttribute('changes') ?? []);
        if (empty($recordedChanges)) {
            return 'Tidak ada rekaman data yang berubah pada aktivitas ini';
        }

        if (! empty($this->metadata['changes_truncated'])) {
            return 'Data perubahan terpotong karena melebihi batas simpan. Gunakan fitur Hapus Massal pada modul terkait.';
        }

        return null;
    }

    public function markAsUndone(?int $undoLogId = null, ?User $actor = null): void
    {
        $now = now();
        DB::table('activity_logs')
            ->where('id', $this->id)
            ->update([
                'undone_at' => $now,
                'undone_by_user_id' => $actor?->id,
                'undone_by_name' => $actor?->name ?? 'Sistem',
                'undo_activity_log_id' => $undoLogId,
            ]);

        $this->undone_at = $now;
        $this->undone_by_user_id = $actor?->id;
        $this->undone_by_name = $actor?->name ?? 'Sistem';
        $this->undo_activity_log_id = $undoLogId;
    }

    public function summaryDescription(): string
    {
        $action = self::ACTIONS[$this->action] ?? $this->action;
        $module = self::MODULES[$this->module] ?? $this->module;

        return "{$action} pada modul {$module} oleh {$this->actor_name}";
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Log aktivitas tidak dapat diubah.');
        });
        static::deleting(function (): void {
            throw new LogicException('Log aktivitas tidak dapat dihapus.');
        });
    }
}
