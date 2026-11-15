@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="row">
    <div class="col-md-5">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Import dari CSV / Excel</h3></div>
        <form method="POST" action="{{ route('act.import.preview') }}" enctype="multipart/form-data">
          @csrf
          <div class="card-body">
            <div class="form-group">
              <label>File (.csv, .xlsx, .xls — maks 5 MB, 2000 baris)</label>
              <input type="file" name="file" class="form-control-file" accept=".csv,.txt,.xlsx,.xls" required>
            </div>
            <div class="form-group">
              <label>Divisi default</label>
              <select name="default_division_id" class="form-control">
                <option value="">- tidak ada (file punya kolom Divisi / nama sheet = divisi) -</option>
                @foreach ($divisions as $d)
                  <option value="{{ $d->id }}" {{ (string) old('default_division_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
              </select>
              <small class="text-muted">Dipakai untuk baris yang tidak punya kolom Divisi. Contoh: CSV dari sheet OnBoard → pilih OnBoard.</small>
            </div>
            <div class="form-group">
              <label>Format tanggal di file</label>
              <select name="date_format" class="form-control">
                <option value="mdy" {{ old('date_format', 'mdy') === 'mdy' ? 'selected' : '' }}>Bulan/Hari/Tahun — 10/2/2026 = 2 Oktober (default Excel/CSV)</option>
                <option value="dmy" {{ old('date_format') === 'dmy' ? 'selected' : '' }}>Hari/Bulan/Tahun — 10/2/2026 = 10 Februari</option>
              </select>
              <small class="text-muted">Format 2026-10-02 dan sel tanggal asli Excel dikenali otomatis.</small>
            </div>
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="auto_master" name="auto_master" value="1" {{ old('auto_master', '1') ? 'checked' : '' }}>
              <label class="custom-control-label font-weight-normal" for="auto_master">Buat otomatis Device / Lokasi / Sarana / Pengawas / Teknisi yang belum ada di Master</label>
            </div>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary"><i class="fas fa-search"></i> Periksa File (Preview)</button>
            <a href="{{ route('act.import.template') }}" class="btn btn-default"><i class="fas fa-download"></i> Unduh Template CSV</a>
          </div>
        </form>
      </div>
    </div>

    <div class="col-md-7">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Panduan</h3></div>
        <div class="card-body">
          <p>Alur: <b>upload → preview (cek error, duplikat) → konfirmasi</b>. Tidak ada data yang tersimpan sebelum kamu menekan Konfirmasi.</p>
          <ul>
            <li><b>Satu sheet/CSV</b> = satu divisi. Upload <b>seluruh workbook</b> (.xlsx) juga bisa: sheet bernama <i>Operation, Analyst, OnBoard, Infra</i> otomatis jadi divisinya, sheet <i>Pending</i> memakai kolom Divisi. Sheet lain (SPV, Closed, Master, dst) diabaikan.</li>
            <li>Kolom wajib: <b>Tanggal</b> dan <b>Problem/Troubleshoot</b>. Urutan kolom bebas, nama kolom dikenali otomatis (Tanggal, Plan, Start Time, Finish Time, Device, Shift, Unit, Location, Pengawas, Team, Problem, Troubleshoot, Sarana, Status / Day 1 / Status Akhir, Remark, ID, ID Induk, Divisi).</li>
            <li><b>ID</b> di file dipakai bila belum terpakai; bila kosong dibuat otomatis (OP/AL/ONB/IF untuk Finish, PD untuk Pending).</li>
            <li><b>Duplikat dilewati</b>: ID sudah ada <i>dan isinya sama</i>. Baris tanpa ID dicek lewat isi data (tanggal + unit + jam + problem). Aman mengupload ulang file yang sama.</li>
            <li><b>ID kembar</b> di dalam file (mis. akibat rumus nomor di Excel) tidak membuang data: pekerjaan yang berbeda otomatis diberi ID baru. ID yang sudah dipakai data lain di database (isi berbeda) ditandai <b>error</b> supaya tidak menimpa.</li>
            <li><b>Status</b>: Finish, Pending, Continue, Closed. Kosong = Finish (atau Continue bila ada ID Induk).</li>
            <li><b>Rantai Pending</b>: isi kolom <b>ID</b> dan <b>ID Induk</b> (ID induk harus sudah ada atau ada di baris sebelumnya).</li>
            <li>Jam boleh <code>08:30</code>, <code>8.30</code>, atau format jam Excel. Tim dipisah koma. Tanda <code>-</code> dianggap kosong.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
