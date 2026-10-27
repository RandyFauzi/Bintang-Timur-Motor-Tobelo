<div class="notification-dropdown relative" x-data="notificationDropdown(@js($items), @js($total))" @keydown.escape.window="open = false" @click.outside="open = false">
    <button type="button"
            class="notification-bell-btn"
            @click="toggle()"
            :aria-expanded="open"
            aria-haspopup="true"
            aria-label="Daftar notifikasi"
            title="Notifikasi">
        <x-ui.icon name="bell" :size="20" />
        <span x-show="unreadCount > 0"
              x-cloak
              x-text="unreadCount > 99 ? '99+' : unreadCount"
              class="notification-bell-badge"
              role="status"
              :aria-label="unreadCount + ' notifikasi belum dibaca'">
        </span>
    </button>

    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
         class="notification-dropdown-panel"
         role="region"
         aria-label="Panel notifikasi">
        
        <div class="notification-dropdown-header">
            <div class="flex items-center gap-2">
                <span class="font-semibold text-sm">Notifikasi</span>
                <span x-show="unreadCount > 0"
                      x-cloak
                      x-text="unreadCount + ' belum dibaca'"
                      class="notification-unread-pill">
                </span>
            </div>
            <button type="button"
                    x-show="unreadCount > 0"
                    x-cloak
                    @click="markAllAsRead()"
                    class="notification-mark-read">
                Tandai sudah dibaca
            </button>
        </div>

        <div class="notification-dropdown-list">
            <template x-for="item in items" :key="item.key">
                <a :href="item.url"
                   @click="markAsRead(item.key)"
                   class="notification-dropdown-item group"
                   :class="{'notification-dropdown-item--unread': isUnread(item.key)}">
                    
                    <div class="notification-item-icon-box"
                         :class="{
                             'notification-item-icon-box--urgent': item.urgent,
                             'notification-item-icon-box--invoice': !item.urgent && item.source === 'invoice',
                             'notification-item-icon-box--shipment': !item.urgent && item.source === 'shipment',
                             'notification-item-icon-box--product': !item.urgent && item.source === 'product'
                         }">
                        <template x-if="item.source === 'product'">
                            <x-ui.icon name="stock-box" :size="16" />
                        </template>
                        <template x-if="item.source === 'invoice'">
                            <x-ui.icon name="wallet" :size="16" />
                        </template>
                        <template x-if="item.source === 'shipment'">
                            <x-ui.icon name="ship" :size="16" />
                        </template>
                    </div>

                    <div class="notification-item-content">
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="notification-tag"
                                  :class="item.urgent ? 'notification-tag--urgent' : 'notification-tag--default'"
                                  x-text="item.category_label">
                            </span>
                        </div>
                        <h4 class="notification-item-title group-hover:text-[#35521b]" x-text="item.title"></h4>
                        <p class="notification-item-message" x-text="item.message"></p>
                        <span class="notification-item-subtitle" x-text="item.subtitle"></span>
                    </div>

                    <div class="notification-item-aside">
                        <span x-show="isUnread(item.key)"
                              class="notification-unread-dot"
                              title="Belum dibaca"
                              aria-label="Belum dibaca"></span>
                        <span class="notification-item-arrow" aria-hidden="true">&rarr;</span>
                    </div>
                </a>
            </template>

            <div x-show="items.length === 0" class="notification-dropdown-empty">
                <x-ui.icon name="bell" :size="28" class="mx-auto mb-2 opacity-30" />
                <p class="text-xs font-medium text-stone-700">Tidak ada notifikasi aktif</p>
                <p class="text-[11px] text-stone-400 mt-1">Semua kondisi stok, tagihan, dan pengiriman aman.</p>
            </div>
        </div>

        <div class="notification-dropdown-footer">
            <a href="{{ route('notifications.index') }}" class="notification-dropdown-footer-link">
                <span>Lihat Semua Notifikasi</span>
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </div>
</div>
