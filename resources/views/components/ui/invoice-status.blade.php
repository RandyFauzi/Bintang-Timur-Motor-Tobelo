@props(['status'])
<span @class(['inline-block whitespace-nowrap rounded-lg px-3 py-2 text-xs font-medium','bg-green-50 text-green-800'=>$status === 'paid','bg-amber-50 text-amber-800'=>$status === 'partial','bg-gray-100 text-gray-700'=>$status === 'unpaid'])>{{ \App\Models\DistributorInvoice::STATUSES[$status] }}</span>
