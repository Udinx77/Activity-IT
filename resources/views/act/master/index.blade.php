@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="card">
    <div class="card-header p-2">
      <ul class="nav nav-pills">
        @foreach ($types as $key => $t)
          <li class="nav-item"><a class="nav-link {{ $type === $key ? 'active' : '' }}" href="{{ route('act.master.index', $key) }}">{{ $t['label'] }}</a></li>
        @endforeach
      </ul>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('act.master.store', $type) }}" class="form-inline mb-3">
        @csrf
        <input type="text" name="name" class="form-control mr-2" placeholder="Nama {{ $cfg['label'] }} baru" required maxlength="255">
        @foreach ($cfg['extra'] as $field => $label)
          <input type="{{ in_array($field, ['start_time', 'end_time']) ? 'time' : 'text' }}" name="{{ $field }}" class="form-control mr-2" placeholder="{{ $label }}" title="{{ $label }}">
        @endforeach
        <button class="btn btn-primary"><i class="fas fa-plus"></i> Tambah</button>
      </form>

      <table class="table table-bordered table-sm" style="max-width:900px">
        <thead>
          <tr><th style="width:60px">#</th><th>Nama</th>
            @foreach ($cfg['extra'] as $label)<th>{{ $label }}</th>@endforeach
            <th style="width:90px">Status</th><th style="width:150px">Aksi</th></tr>
        </thead>
        <tbody>
        @foreach ($items as $it)
          <tr class="{{ $it->is_active ? '' : 'text-muted' }}">
            <td>{{ $it->id }}</td>
            <td>{{ $it->name }}</td>
            @foreach ($cfg['extra'] as $field => $label)
              <td>{{ in_array($field, ['start_time', 'end_time']) ? substr((string) $it->$field, 0, 5) : $it->$field }}</td>
            @endforeach
            <td>{!! $it->is_active ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Nonaktif</span>' !!}</td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-xs btn-warning btn-edit"
                      data-id="{{ $it->id }}" data-name="{{ $it->name }}"
                      @foreach ($cfg['extra'] as $field => $label) data-{{ $field }}="{{ in_array($field, ['start_time', 'end_time']) ? substr((string) $it->$field, 0, 5) : $it->$field }}" @endforeach>
                <i class="fas fa-edit"></i> Edit
              </button>
              <form action="{{ route('act.master.toggle', [$type, $it->id]) }}" method="POST" class="d-inline">
                @csrf
                <button class="btn btn-xs btn-{{ $it->is_active ? 'outline-secondary' : 'success' }}">{{ $it->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
      <p class="text-muted">Data tidak dihapus permanen supaya riwayat activity tetap utuh; cukup dinonaktifkan.</p>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" id="formEdit" action="" class="modal-content">
      @csrf @method('PUT')
      <div class="modal-header"><h5 class="modal-title">Edit {{ $cfg['label'] }}</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <div class="form-group"><label>Nama</label><input type="text" name="name" id="editName" class="form-control" required maxlength="255"></div>
        @foreach ($cfg['extra'] as $field => $label)
          <div class="form-group"><label>{{ $label }}</label>
            <input type="{{ in_array($field, ['start_time', 'end_time']) ? 'time' : 'text' }}" name="{{ $field }}" id="edit_{{ $field }}" class="form-control"></div>
        @endforeach
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
    </form>
  </div>
</div>
@endsection

@push('addon-script')
<script>
$(function () {
  var tpl = @json(route('act.master.update', [$type, '__ID__']));
  $('.btn-edit').on('click', function () {
    var b = $(this);
    $('#formEdit').attr('action', tpl.replace('__ID__', b.data('id')));
    $('#editName').val(b.attr('data-name'));
    @foreach ($cfg['extra'] as $field => $label)
    $('#edit_{{ $field }}').val(b.attr('data-{{ $field }}'));
    @endforeach
    $('#modalEdit').modal('show');
  });
});
</script>
@endpush
