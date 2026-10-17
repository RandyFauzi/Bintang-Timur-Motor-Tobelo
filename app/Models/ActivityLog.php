<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    public const ACTIONS = ['access_denied' => 'Akses Ditolak', 'request_failed' => 'Permintaan Gagal', 'export' => 'Export Data', 'create' => 'Tambah Data', 'update' => 'Ubah Data', 'delete' => 'Hapus Data', 'submit' => 'Kirim Tindakan', 'login' => 'Login', 'logout' => 'Logout', 'login_failed' => 'Login Gagal', 'register' => 'Registrasi Akun', 'password_reset' => 'Reset Password', 'password_reset_request' => 'Minta Reset Password', 'checkout' => 'Transaksi Kasir', 'scan' => 'Pindai Produk', 'cancel' => 'Batalkan Data', 'bulk' => 'Ubah Massal'];

    public const RESULTS = ['success' => 'Berhasil', 'failed' => 'Gagal', 'denied' => 'Ditolak'];

    public const MODULES = ['dashboard' => 'Dashboard', 'products' => 'Stok & Produk', 'distributors' => 'Distributor', 'returns' => 'Retur Produk', 'pos' => 'Kasir / POS', 'transactions' => 'Histori Transaksi', 'reports' => 'Laporan & Riset', 'finance' => 'Laporan Keuangan', 'research' => 'Riset Kompetitor', 'users' => 'Pengguna', 'profile' => 'Pengaturan', 'business' => 'Identitas Usaha', 'activity-logs' => 'Log Aktivitas', 'auth' => 'Autentikasi', 'other' => 'Lainnya'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
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
