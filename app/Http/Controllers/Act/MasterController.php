<?php

namespace App\Http\Controllers\Act;

use App\Http\Controllers\Controller;
use App\Models\Act\Device;
use App\Models\Act\Location;
use App\Models\Act\Shift;
use App\Models\Act\Supervisor;
use App\Models\Act\Technician;
use App\Models\Act\Tool;
use Illuminate\Http\Request;

class MasterController extends Controller
{
    /** type => [label, model, field tambahan] */
    private function types(): array
    {
        return [
            'shift'      => ['label' => 'Shift',      'model' => Shift::class,      'extra' => ['start_time' => 'Mulai', 'end_time' => 'Selesai']],
            'device'     => ['label' => 'Device',     'model' => Device::class,     'extra' => []],
            'location'   => ['label' => 'Lokasi',     'model' => Location::class,   'extra' => []],
            'tool'       => ['label' => 'Sarana',     'model' => Tool::class,       'extra' => []],
            'supervisor' => ['label' => 'Pengawas',   'model' => Supervisor::class, 'extra' => ['position' => 'Posisi']],
            'technician' => ['label' => 'Teknisi',    'model' => Technician::class, 'extra' => []],
        ];
    }

    private function cfg(string $type): array
    {
        $types = $this->types();
        abort_unless(isset($types[$type]), 404);
        return $types[$type];
    }

    public function index($type = 'shift')
    {
        $cfg   = $this->cfg($type);
        $class = $cfg['model'];
        $items = $class::orderBy('id')->get();
        return view('act.master.index', ['type' => $type, 'cfg' => $cfg, 'types' => $this->types(), 'items' => $items]);
    }

    public function store(Request $request, $type)
    {
        $cfg  = $this->cfg($type);
        $data = $request->validate($this->rules($cfg));
        $class = $cfg['model'];
        $class::create(array_merge($data, ['is_active' => 1]));
        return redirect()->route('act.master.index', $type)->with('success', $cfg['label'] . ' ditambahkan.');
    }

    public function update(Request $request, $type, $id)
    {
        $cfg  = $this->cfg($type);
        $data = $request->validate($this->rules($cfg));
        $class = $cfg['model'];
        $class::findOrFail($id)->update($data);
        return redirect()->route('act.master.index', $type)->with('success', $cfg['label'] . ' diperbarui.');
    }

    /** Aktif/nonaktif (tidak dihapus permanen supaya riwayat activity aman). */
    public function toggle($type, $id)
    {
        $cfg   = $this->cfg($type);
        $class = $cfg['model'];
        $row   = $class::findOrFail($id);
        $row->update(['is_active' => !$row->is_active]);
        return redirect()->route('act.master.index', $type)
            ->with('success', $row->name . ($row->is_active ? ' diaktifkan.' : ' dinonaktifkan.'));
    }

    private function rules(array $cfg): array
    {
        $rules = ['name' => 'required|string|max:255'];
        foreach (array_keys($cfg['extra']) as $f) {
            $rules[$f] = in_array($f, ['start_time', 'end_time']) ? 'nullable|date_format:H:i' : 'nullable|string|max:255';
        }
        return $rules;
    }
}
