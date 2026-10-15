<div x-data="toast" @notify.window="show($event.detail)" class="toast-stack" aria-live="polite" aria-atomic="true">
    <div x-show="visible" x-cloak x-transition.opacity class="toast" :class="{ 'toast--error': type === 'error' }" @mouseenter="pause()" @mouseleave="schedule()">
        <span class="toast__indicator" aria-hidden="true" x-text="type === 'error' ? '!' : (type === 'success' ? '✓' : 'i')"></span>
        <p class="flex-1 text-sm" x-text="message"></p>
        <button type="button" @click="visible = false; pause()" class="toast__close" aria-label="Tutup notifikasi">×</button>
    </div>
    @if (session('success') || session('error'))
        <span x-init="show(@js(['message' => session('success') ?? session('error'), 'type' => session('error') ? 'error' : 'success']))"></span>
    @endif
</div>
