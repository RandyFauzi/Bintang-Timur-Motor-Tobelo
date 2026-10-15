import Alpine from 'alpinejs';
import { appShell, dateRangeFilter, stockNotifications, toast } from './components/ui';
import { revenueChart } from './components/revenue-chart';
import { appDialog, multiSelect, observationForm } from './components/catalogue';
import { returnSelection, productPicker } from './components/product-returns';
import { posRegister } from './components/pos';
import { transactionEditor } from './components/transaction-editor';

import.meta.glob('../images/**/*', { eager: true, query: '?url', import: 'default' });

Alpine.data('appShell', appShell);
Alpine.data('dateRangeFilter', dateRangeFilter);
Alpine.data('stockNotifications', stockNotifications);
Alpine.data('toast', toast);
Alpine.data('revenueChart', revenueChart);
Alpine.data('appDialog', appDialog);
Alpine.data('multiSelect', multiSelect);
Alpine.data('observationForm', observationForm);
Alpine.data('returnSelection', returnSelection);
Alpine.data('productPicker', productPicker);
Alpine.data('posRegister', posRegister);
Alpine.data('transactionEditor', transactionEditor);

window.Alpine = Alpine;
Alpine.start();
