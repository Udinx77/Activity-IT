@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">
        @if ($isEdit) Edit Activity <b>{{ $activity->code }}</b> @else Tambah Activity (Finish dalam 1 shift) @endif
      </h3>
    </div>
    <form method="POST" action="{{ $isEdit ? route('act.activity.update', $activity->id) : route('act.activity.store') }}">
      @csrf
      @if ($isEdit) @method('PUT') @endif
      <div class="card-body">
        @unless ($isEdit)
          <div class="alert alert-info py-2">
            Menu ini khusus job yang <b>selesai dalam satu shift</b> (status otomatis <b>Finish</b>).
            Kalau job belum selesai dan lanjut shift/hari berikutnya, catat di
            <a href="{{ route('act.pending.create') }}"><b>Pending / Continue</b></a>.
          </div>
        @endunless

        @include('act.partials.formfields')

        @if ($isEdit)
          <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control" style="max-width:240px">
              @foreach (['finish', 'pending', 'continue', 'closed'] as $st)
                <option value="{{ $st }}" {{ old('status', $activity->status) === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
              @endforeach
            </select>
          </div>
        @endif
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
        <a href="{{ route('act.activity.index') }}" class="btn btn-default">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
