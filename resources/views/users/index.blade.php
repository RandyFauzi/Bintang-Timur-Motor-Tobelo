<x-app-layout>
    <div x-data="{ 
            createModalOpen: false, 
            editModalOpen: false, 
            editForm: { id: '', name: '', email: '', role: '' },
            openEdit(user) {
                this.editForm.id = user.id;
                this.editForm.name = user.name;
                this.editForm.email = user.email;
                this.editForm.role = user.role;
                this.editModalOpen = true;
            }
        }" class="max-w-7xl mx-auto mb-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Pengguna</h1>
                <p class="text-sm text-gray-500 mt-1">Kelola akun kasir dan admin sistem.</p>
            </div>
            <button @click="createModalOpen = true" class="bg-[#C62828] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm hover:bg-[#9E1B1B]">
                + Tambah Pengguna
            </button>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50 text-xs text-gray-500 uppercase tracking-wider">
                            <th class="p-4 font-medium border-b border-gray-100">Nama Lengkap</th>
                            <th class="p-4 font-medium border-b border-gray-100">Email</th>
                            <th class="p-4 font-medium border-b border-gray-100">Role / Hak Akses</th>
                            <th class="p-4 font-medium border-b border-gray-100">Tgl Bergabung</th>
                            <th class="p-4 font-medium border-b border-gray-100 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        @foreach($users as $user)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="p-4 font-semibold text-gray-900">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-red-100 text-[#C62828] flex items-center justify-center font-bold text-xs uppercase">
                                        {{ substr($user->name, 0, 2) }}
                                    </div>
                                    {{ $user->name }}
                                </div>
                            </td>
                            <td class="p-4 text-gray-500">{{ $user->email }}</td>
                            <td class="p-4">
                                @if($user->role === 'admin')
                                    <span class="bg-purple-50 text-purple-600 px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wide">Admin</span>
                                @else
                                    <span class="bg-blue-50 text-blue-600 px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wide">Staff Kasir</span>
                                @endif
                            </td>
                            <td class="p-4 text-gray-500">{{ $user->created_at->format('d M Y') }}</td>
                            <td class="p-4 text-right">
                                <!-- Edit Button -->
                                <button type="button" @click="openEdit({{ json_encode(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role]) }})" class="text-gray-400 hover:text-blue-600 p-1 transition-colors mr-2" title="Edit Pengguna">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                </button>
                                
                                <!-- Delete Button -->
                                @if($user->id !== auth()->id() && $user->email !== 'randyfauzi24@gmail.com')
                                <form action="{{ route('users.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus pengguna ini?');" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-gray-400 hover:text-red-600 p-1 transition-colors" title="Hapus Pengguna">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $users->links() }}
            </div>
            @endif
        </div>

        <!-- Modal Tambah Pengguna -->
        <div x-show="createModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0" x-cloak>
            <div x-show="createModalOpen" x-transition.opacity class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="createModalOpen = false"></div>
            
            <div x-show="createModalOpen" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md mx-auto overflow-hidden">
                
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Tambah Pengguna Baru</h3>
                    <button @click="createModalOpen = false" class="text-gray-400 hover:text-gray-900 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('users.store') }}" class="p-6">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm" placeholder="Joko Kasir">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" required class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm" placeholder="joko@bintangtimur.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hak Akses / Role</label>
                            <select name="role" required class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                                <option value="staff" {{ old('role') == 'staff' ? 'selected' : '' }}>Staff Kasir</option>
                                <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin (Penuh)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" name="password" required class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ulangi Password</label>
                            <input type="password" name="password_confirmation" required class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                        </div>
                    </div>
                    
                    <div class="mt-8 flex gap-3">
                        <button type="button" @click="createModalOpen = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-700 font-medium text-sm hover:bg-gray-50 transition-colors">Batal</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-[#C62828] text-white font-medium text-sm hover:bg-[#9E1B1B] shadow-sm transition-colors">Buat Akun</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Edit Pengguna -->
        <div x-show="editModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0" x-cloak>
            <div x-show="editModalOpen" x-transition.opacity class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="editModalOpen = false"></div>
            
            <div x-show="editModalOpen" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md mx-auto overflow-hidden">
                
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Edit Pengguna</h3>
                    <button @click="editModalOpen = false" class="text-gray-400 hover:text-gray-900 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form method="POST" :action="`/users/${editForm.id}`" class="p-6">
                    @csrf
                    @method('PUT')
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                            <input type="text" name="name" x-model="editForm.name" required class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" x-model="editForm.email" required class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hak Akses / Role</label>
                            <select name="role" x-model="editForm.role" required class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                                <option value="staff">Staff Kasir</option>
                                <option value="admin">Admin (Penuh)</option>
                            </select>
                        </div>
                        
                        <div class="border-t border-gray-100 pt-4 mt-2">
                            <p class="text-xs text-gray-500 mb-3 font-medium">Biarkan kosong jika tidak ingin mengubah password.</p>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
                                    <input type="password" name="password" class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ulangi Password Baru</label>
                                    <input type="password" name="password_confirmation" class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 flex gap-3">
                        <button type="button" @click="editModalOpen = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-700 font-medium text-sm hover:bg-gray-50 transition-colors">Batal</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-white font-medium text-sm hover:bg-blue-700 shadow-sm transition-colors">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
