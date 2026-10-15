<x-app-layout>
    <div class="catalogue-page">
        <header class="catalogue-heading"><div><h1>Pengguna</h1><p class="mt-2 text-sm text-muted">Kelola akun dan hak akses pengguna Bintang Timur.</p></div></header>
        <div class="mb-6 grid gap-4 md:grid-cols-3">
            <x-dashboard.stat-card title="Total Pengguna" :value="number_format($summary->total)" note="Sesuai pencarian dan filter" icon="user" />
            <x-dashboard.stat-card title="Admin" :value="number_format($summary->admins)" note="Mengelola akun dan operasional" icon="settings" />
            <x-dashboard.stat-card title="Staff Kasir" :value="number_format($summary->staff)" note="Akun operasional toko" icon="cart" />
        </div>
        <section class="catalogue-surface" aria-label="Daftar pengguna">
            <form method="GET" action="{{ route('users.index') }}">
                <x-ui.catalogue-toolbar export-route="users.export" create-label="Tambah Pengguna" dialog="user-form" />
                <div class="catalogue-filters"><label class="return-note-filter"><x-ui.icon name="user" :size="16" /><span class="sr-only">Hak Akses</span><select name="role" onchange="this.form.requestSubmit()"><option value="">Semua Hak Akses</option><option value="admin" @selected(request('role') === 'admin')>Admin</option><option value="staff" @selected(request('role') === 'staff')>Staff Kasir</option></select></label>@if(request()->filled('search') || request()->filled('role'))<a href="{{ route('users.index') }}" class="filter-reset">Reset Filter</a>@endif</div>
            </form>
            @if(request('view') === 'cards')
                <div class="catalogue-card-grid">@forelse($users as $user)<article class="product-card"><header><div class="flex items-center gap-3"><x-ui.user-avatar :name="$user->name" :size="40" /><h2>{{ $user->name }}</h2></div><span class="neutral-badge">{{ $user->role === 'admin' ? 'Admin' : 'Staff Kasir' }}</span></header><dl class="product-card__details"><dt>Email</dt><dd class="break-all">{{ $user->email }}</dd><dt>Bergabung</dt><dd>{{ $user->created_at->format('d M Y') }}</dd></dl><a href="{{ request()->fullUrlWithQuery(['edit'=>$user->id,'create'=>null]) }}" class="ui-button ui-button--secondary">Edit Pengguna <x-ui.icon name="edit" :size="18" /></a></article>@empty<x-ui.empty-state title="Pengguna tidak ditemukan" description="Ubah pencarian atau tambahkan pengguna baru." />@endforelse</div>
            @else
                <div class="catalogue-table-scroll"><table class="catalogue-table"><caption class="sr-only">Akun pengguna dan hak aksesnya</caption><thead><tr>@foreach(['Pengguna','Email','Hak Akses','Tanggal Bergabung','Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>@forelse($users as $user)<tr><td><span class="flex items-center gap-3"><x-ui.user-avatar :name="$user->name" :size="36" /><span class="font-medium">{{ $user->name }}@if($user->id === auth()->id())<span class="ml-2 text-xs text-muted">(Anda)</span>@endif</span></span></td><td class="break-all">{{ $user->email }}</td><td><span class="neutral-badge">{{ $user->role === 'admin' ? 'Admin' : 'Staff Kasir' }}</span></td><td>{{ $user->created_at->format('d M Y') }}</td><td><a href="{{ request()->fullUrlWithQuery(['edit'=>$user->id,'create'=>null]) }}" class="edit-link" aria-label="Edit {{ $user->name }}">Edit <x-ui.icon name="edit" :size="18" /></a></td></tr>@empty<tr><td colspan="5"><x-ui.empty-state title="Pengguna tidak ditemukan" description="Ubah pencarian atau tambahkan pengguna baru." /></td></tr>@endforelse</tbody></table></div>
            @endif
            <x-ui.pagination :paginator="$users" />
        </section>
        <x-ui.dialog name="user-form" :title="$editing ? 'Edit Pengguna' : 'Tambah Pengguna'" :description="$editing ? 'Perbarui informasi akun atau hak akses pengguna.' : 'Buat akun baru untuk mengakses aplikasi.'" :show="$editing !== null || request()->boolean('create') || old('_dialog') === 'user-form'" close-icon="close">
            <form method="POST" action="{{ $editing ? route('users.update',$editing) : route('users.store') }}" class="dialog-form" @submit="submitting=true">
                @csrf @if($editing)@method('PATCH')<input type="hidden" name="_user_id" value="{{ $editing->id }}">@endif<input type="hidden" name="_dialog" value="user-form">
                <x-ui.field name="name" label="Nama Lengkap" :value="$editing?->name" maxlength="255" required autocomplete="name" />
                <x-ui.field name="email" label="Email" type="email" :value="$editing?->email" maxlength="255" required autocomplete="email" />
                <x-ui.field name="role" label="Hak Akses"><select id="role" name="role" class="form-input" required @disabled($editing?->id === auth()->id())><option value="staff" @selected(old('role',$editing?->role ?? 'staff') === 'staff')>Staff Kasir</option><option value="admin" @selected(old('role',$editing?->role) === 'admin')>Admin</option></select>@if($editing?->id === auth()->id())<input type="hidden" name="role" value="admin"><p class="mt-2 text-xs text-muted">Hak akses akun sendiri tetap Admin agar akses pengelolaan tidak hilang.</p>@endif</x-ui.field>
                @if($editing)<p class="border-t border-gray-200 pt-4 text-sm text-muted">Kosongkan password jika tidak ingin mengubahnya.</p>@endif
                <x-ui.field name="password" :label="$editing ? 'Password Baru (Opsional)' : 'Password'" type="password" minlength="8" autocomplete="new-password" :required="$editing === null" hint="Minimal 8 karakter." />
                <x-ui.field name="password_confirmation" label="Konfirmasi Password" type="password" minlength="8" autocomplete="new-password" :required="$editing === null" />
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Menyimpan…' : 'Simpan Pengguna'">Simpan Pengguna</button></footer>
            </form>
        </x-ui.dialog>
    </div>
</x-app-layout>
