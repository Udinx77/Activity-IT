@if (session('success'))
  <div class="alert alert-success alert-dismissible">
    <button type="button" class="close" data-dismiss="alert">&times;</button>{{ session('success') }}
  </div>
@endif
@if (session('error'))
  <div class="alert alert-danger alert-dismissible">
    <button type="button" class="close" data-dismiss="alert">&times;</button>{{ session('error') }}
  </div>
@endif
@if ($errors->any())
  <div class="alert alert-danger">
    <b>Data belum bisa disimpan:</b>
    <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif
