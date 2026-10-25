<div x-data="toast" @notify.window="show($event.detail)" class="toast-stack" aria-live="polite" aria-atomic="true">
    <div x-show="visible" x-cloak x-transition.opacity class="toast" :class="{ 'toast--error': type === 'error', 'toast--minimized': minimized }" @mouseenter="pause()" @mouseleave="schedule()">
        <span class="toast__indicator cursor-pointer" aria-hidden="true" x-text="type === 'error' ? '!' : (type === 'success' ? '✓' : 'i')" @click="if (minimized) toggleMinimize()" :title="minimized ? 'Perluas notifikasi' : ''"></span>
        <p class="flex-1 text-sm" x-show="!minimized" x-text="message"></p>
        <div class="flex items-center gap-1 shrink-0">
            <button type="button" @click="toggleMinimize()" class="toast__minimize" :aria-label="minimized ? 'Perluas notifikasi' : 'Minimalkan notifikasi'" :title="minimized ? 'Perluas notifikasi' : 'Minimalkan notifikasi'">
                <span aria-hidden="true" class="text-xs" x-text="minimized ? '↗' : '—'"></span>
            </button>
            <button type="button" @click="visible = false; pause()" class="toast__close" aria-label="Tutup notifikasi">×</button>
        </div>
    </div>
    @if (session('success') || session('error'))
        <span x-init="show(@js(['message' => session('success') ?? session('error'), 'type' => session('error') ? 'error' : 'success']))"></span>
    @endif
</div>
