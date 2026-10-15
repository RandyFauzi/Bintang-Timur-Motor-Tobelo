import Alpine from 'alpinejs';
import { appShell, dateRangeFilter, stockNotifications, toast } from './components/ui';
import { revenueChart } from './components/revenue-chart';

import.meta.glob('../images/**/*', { eager: true, query: '?url', import: 'default' });

Alpine.data('appShell', appShell);
Alpine.data('dateRangeFilter', dateRangeFilter);
Alpine.data('stockNotifications', stockNotifications);
Alpine.data('toast', toast);
Alpine.data('revenueChart', revenueChart);

window.Alpine = Alpine;
Alpine.start();
