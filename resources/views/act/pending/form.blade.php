@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="card">
    <div class="card-header"><h3 class="card-title">Catat Job Pending Baru (belum selesai dalam 1 shift)</h3></div>
    <form method="POST" action="{{ route('act.pending.store') }}">
      @csrf
      <div class="card-body">
        <div class="alert alert-warning py-2">
          ID otomatis <b>PD###</b>, status <b>Pending</b>. Shift/hari berikutnya dilanjutkan lewat tombol
          <b>Lanjutkan</b> di daftar Pending (ID baru, tersambung ke ID ini).
        </div>
        @include('act.partials.formfields', ['selectedTech' => [], 'problemRequired' => true])
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-danger"><i class="fas fa-save"></i> Simpan Pending</button>
        <a href="{{ route('act.pending.index') }}" class="btn btn-default">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
