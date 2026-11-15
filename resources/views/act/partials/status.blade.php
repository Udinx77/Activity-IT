@php $__map = ['finish' => 'success', 'closed' => 'secondary', 'pending' => 'danger', 'continue' => 'info']; @endphp
<span class="badge badge-{{ $__map[$s] ?? 'light' }}">{{ ucfirst($s) }}</span>
