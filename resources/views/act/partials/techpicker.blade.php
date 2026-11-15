{{-- butuh: $technicians, $selectedTech --}}
<div class="border rounded p-2" style="max-height:230px;overflow:auto">
  <input type="text" class="form-control form-control-sm mb-2" placeholder="Cari nama teknisi..."
         oninput="var q=this.value.toLowerCase();document.querySelectorAll('.tech-item').forEach(function(e){e.style.display=e.textContent.toLowerCase().indexOf(q)>-1?'':'none'})">
  <div class="row">
    @foreach ($technicians as $t)
      <div class="col-md-4 col-6 tech-item">
        <div class="custom-control custom-checkbox">
          <input class="custom-control-input" type="checkbox" id="tech{{ $t->id }}" name="technician_ids[]" value="{{ $t->id }}"
                 {{ in_array($t->id, (array) old('technician_ids', $selectedTech)) ? 'checked' : '' }}>
          <label for="tech{{ $t->id }}" class="custom-control-label font-weight-normal">{{ $t->name }}</label>
        </div>
      </div>
    @endforeach
  </div>
</div>
