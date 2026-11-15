<?php

namespace App\Http\Controllers\Act;

use App\Http\Controllers\Controller;
use App\Models\Act\Division;
use App\Services\Act\ActivityImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function index()
    {
        return view('act.import.index', ['divisions' => Division::where('is_active', 1)->orderBy('id')->get()]);
    }

    /** Langkah 1: baca + validasi file, tampilkan preview. Belum ada data yang disimpan. */
    public function preview(Request $request, ActivityImporter $importer)
    {
        $request->validate([
            'file'                => 'required|file|max:5120',
            'default_division_id' => 'nullable|exists:act_divisions,id',
            'date_format'         => 'required|in:mdy,dmy',
        ], ['file.max' => 'Ukuran file maksimal 5 MB.']);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls'])) {
            return back()->withInput()->withErrors(['file' => 'Format file harus CSV, XLSX, atau XLS.']);
        }

        $opt = [
            'default_division_id' => $request->input('default_division_id'),
            'date_format'         => $request->input('date_format'),
            'auto_master'         => $request->boolean('auto_master'),
        ];
        try {
            $res = $importer->parse($file->getRealPath(), $ext, $opt);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['file' => 'Gagal membaca file: ' . $e->getMessage()]);
        }

        $this->cleanup();
        $token = bin2hex(random_bytes(16));
        $newRows = array_values(array_filter($res['rows'], function ($r) { return $r['state'] === 'new'; }));
        Storage::disk('local')->put('act_import/' . $token . '.json', json_encode([
            'user' => auth()->id(), 'file' => $file->getClientOriginalName(), 'rows' => $newRows,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

        return view('act.import.preview', [
            'token' => $token, 'filename' => $file->getClientOriginalName(), 'res' => $res,
            'newRows' => array_slice($newRows, 0, 100),
            'errorRows' => array_slice(array_values(array_filter($res['rows'], function ($r) { return $r['state'] === 'error'; })), 0, 300),
            'dupRows' => array_slice(array_values(array_filter($res['rows'], function ($r) { return $r['state'] === 'duplicate'; })), 0, 15),
        ]);
    }

    /** Langkah 2: simpan baris hasil preview. */
    public function confirm(Request $request, ActivityImporter $importer)
    {
        $token = (string) $request->input('token');
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) { abort(404); }
        $path = 'act_import/' . $token . '.json';
        if (!Storage::disk('local')->exists($path)) {
            return redirect()->route('act.import.index')->with('error', 'Data preview sudah kedaluwarsa. Upload ulang file.');
        }
        $payload = json_decode(Storage::disk('local')->get($path), true);
        if (!$payload || (int) $payload['user'] !== (int) auth()->id()) { abort(403); }

        $stat = $importer->commit($payload['rows'], $payload['file']);
        Storage::disk('local')->delete($path);

        $msg = 'Import selesai: ' . $stat['inserted'] . ' baris ditambahkan';
        if ($stat['skipped']) { $msg .= ', ' . $stat['skipped'] . ' dilewati (ID sudah ada)'; }
        if ($stat['masters']) {
            $labels = ['device' => 'device', 'location' => 'lokasi', 'tool' => 'sarana', 'supervisor' => 'pengawas', 'technician' => 'teknisi'];
            $parts = [];
            foreach ($stat['masters'] as $k => $n) { $parts[] = $n . ' ' . $labels[$k]; }
            $msg .= '. Master baru: ' . implode(', ', $parts);
        }
        return redirect()->route('act.import.index')->with('success', $msg . '.');
    }

    /** Template CSV (Excel: buka lalu Save As .xlsx bila perlu). */
    public function template()
    {
        $head = ['Divisi', 'ID', 'ID Induk', 'Tanggal', 'Plan', 'Start Time', 'Finish Time', 'Device', 'Shift', 'Unit', 'Location',
            'Pengawas', 'Team', 'Problem', 'Troubleshoot', 'Sarana', 'Status', 'Remark'];
        $rows = [
            ['OnBoard', '', '', '2026-10-13', 'Unschedule', '08:30', '09:15', 'Radio', 'Shift 1', 'HDCT77310', 'Pit Lehman', 'Dino', 'Gusfi, Ajay', 'Radio Abnormal', "1. Replace radio\n2. Test modulasi OK", 'BM-602', 'Finish', ''],
            ['OnBoard', 'PD901', '', '2026-10-12', 'Unschedule', '10:00', '', 'WGB', 'Shift 1', 'KT8531', 'Pit Lehman', 'Dino', 'Gusfi', 'Instalasi kit minestar', 'Mulai instalasi', '', 'Pending', 'CONTOH rantai pending (hapus baris contoh sebelum upload)'],
            ['OnBoard', 'PD902', 'PD901', '2026-10-13', 'Unschedule', '08:00', '12:00', 'WGB', 'Shift 1', 'KT8531', 'Pit Lehman', 'Dino', 'Gusfi', '', 'Lanjut instalasi, selesai', '', 'Closed', ''],
        ];
        return response()->streamDownload(function () use ($head, $rows) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, $head, ',', '"', '\\');
            foreach ($rows as $r) { fputcsv($h, $r, ',', '"', '\\'); }
            fclose($h);
        }, 'template_import_activity.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function cleanup(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files('act_import') as $f) {
            if (time() - $disk->lastModified($f) > 86400) { $disk->delete($f); }
        }
    }
}
