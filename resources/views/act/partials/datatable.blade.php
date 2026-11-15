{{-- butuh: $table (id tabel), $title (judul file export) --}}
<script>
$(function () {
  var opt = {exportOptions: {columns: ':visible:not(.no-export)'}};
  var t = $('#{{ $table }}').DataTable({
    autoWidth: false, scrollX: true, pageLength: 25, order: [],
    buttons: [
      $.extend(true, {extend: 'copy'}, opt),
      $.extend(true, {extend: 'excel', title: @json($title)}, opt),
      $.extend(true, {extend: 'pdf', title: @json($title), orientation: 'landscape', pageSize: 'A4'}, opt),
      $.extend(true, {extend: 'print', title: @json($title)}, opt),
      'colvis'
    ]
  });
  t.buttons().container().appendTo('#{{ $table }}_wrapper .col-md-6:eq(0)');
});
</script>
