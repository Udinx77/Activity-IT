<?php

namespace App\Services\Act;

use App\Models\Act\Activity;
use App\Models\Act\ActivityLog;
use App\Models\Act\Device;
use App\Models\Act\Division;
use App\Models\Act\Location;
use App\Models\Act\Shift;
use App\Models\Act\Supervisor;
use App\Models\Act\Technician;
use App\Models\Act\Tool;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Import activity dari CSV / XLSX / XLS.
 *  parse()  : baca + validasi semua baris (belum menyimpan apa pun)
 *  commit() : simpan baris berstatus "new" hasil parse()
 */
class ActivityImporter
{
    const MAX_ROWS = 2000;

    /** alias header (dinormalisasi: huruf kecil tanpa spasi/simbol). Urutan alias = prioritas. */
    private static $aliases = [
        'code'         => ['id', 'kode', 'code', 'idactivity'],
        'parent'       => ['idinduk', 'induk', 'parent', 'parentid', 'parentcode', 'kodeinduk'],
        'division'     => ['divisi', 'division'],
        'date'         => ['tanggal', 'tgl', 'date', 'activitydate'],
        'plan'         => ['plan', 'rencana'],
        'start_time'   => ['starttime', 'start', 'jammulai', 'mulai'],
        'finish_time'  => ['finishtime', 'jamselesai', 'selesai', 'endtime'],
        'device'       => ['device', 'perangkat'],
        'shift'        => ['shift'],
        'unit'         => ['unit', 'nounit', 'kodeunit'],
        'location'     => ['location', 'lokasi'],
        'supervisor'   => ['pengawas', 'spv', 'supervisor'],
        'team'         => ['team', 'tim', 'teknisi', 'anggota'],
        'problem'      => ['problem', 'masalah', 'kendala'],
        'troubleshoot' => ['troubleshoot', 'troubleshooting', 'penanganan', 'tindakan'],
        'tool'         => ['sarana', 'tool', 'alat'],
        'status'       => ['statusakhir', 'status', 'day1'],
        'remark'       => ['remark', 'keterangan', 'catatan'],
    ];

    private static $statusMap = [
        'finish' => 'finish', 'finished' => 'finish', 'selesai' => 'finish',
        'pending' => 'pending',
        'continue' => 'continue', 'lanjut' => 'continue', 'lanjutan' => 'continue',
        'closed' => 'closed', 'close' => 'closed',
    ];

    // =================================================================
    //  PARSE
    // =================================================================
    public function parse(string $path, string $ext, array $opt): array
    {
        $dateFmt   = ($opt['date_format'] ?? 'mdy') === 'dmy' ? 'dmy' : 'mdy';
        $autoMaster = !empty($opt['auto_master']);
        $defaultDivisionId = !empty($opt['default_division_id']) ? (int) $opt['default_division_id'] : null;

        $tables = in_array($ext, ['xlsx', 'xls']) ? $this->readSpreadsheet($path) : [['name' => null, 'rows' => $this->readCsv($path)]];

        // --- data pembanding dari database ---
        $divisions = []; $divisionById = [];
        foreach (Division::all() as $d) {
            $divisions[mb_strtolower($d->name)] = $d;
            $divisions[mb_strtolower($d->prefix)] = $d;
            $divisionById[$d->id] = $d;
        }
        $maps = [
            'device' => $this->nameMap(Device::class), 'location' => $this->nameMap(Location::class),
            'tool' => $this->nameMap(Tool::class), 'supervisor' => $this->nameMap(Supervisor::class),
            'technician' => $this->nameMap(Technician::class), 'shift' => $this->nameMap(Shift::class),
        ];
        $dbCodes = []; $dbCodePrints = []; $dbPrints = [];
        foreach (Activity::withTrashed()->select('code', 'division_id', 'activity_date', 'start_time', 'unit', 'problem', 'deleted_at')->get() as $a) {
            $pr = $this->fingerprint($a->division_id, $a->activity_date->format('Y-m-d'), (string) $a->start_time, (string) $a->unit, (string) $a->problem);
            $dbCodes[$a->code] = true; $dbCodePrints[$a->code] = $pr;
            if ($a->deleted_at === null) { $dbPrints[$pr] = true; }
        }

        $out = []; $newMasters = ['device' => [], 'location' => [], 'tool' => [], 'supervisor' => [], 'technician' => []];
        $fileCodes = []; $fileCodePrints = []; $filePrints = [];
        $sum = ['read' => 0, 'new' => 0, 'duplicate' => 0, 'error' => 0, 'blank' => 0];
        $sheets = []; $used = []; $ignored = [];
        $total = 0;

        foreach ($tables as $t) {
            [$hdrIdx, $cols] = $this->findHeader($t['rows']);
            if ($hdrIdx === null) {
                $sheets[] = ($t['name'] ?: 'file') . ' (dilewati: kolom Tanggal tidak ditemukan)';
                continue;
            }
            $sheets[] = $t['name'] ?: 'file';
            foreach ($cols['_used'] as $u) { $used[$u] = true; }
            foreach ($cols['_ignored'] as $u) { $ignored[$u] = true; }

            $hint = null; // divisi dari nama sheet
            if ($t['name'] !== null && isset($divisions[mb_strtolower(trim($t['name']))])) { $hint = $divisions[mb_strtolower(trim($t['name']))]; }

            foreach ($t['rows'] as $i => $cells) {
                if ($i <= $hdrIdx) { continue; }
                if ($this->isBlankRow($cells)) { $sum['blank']++; continue; }
                $line = $i + 1;

                $get = function ($field) use ($cols, $cells) { return $this->first($cells, $cols[$field] ?? []); };
                $problem = $this->text($get('problem'));
                $trouble = $this->multiline($get('troubleshoot'));
                $dateRaw = $get('date');
                if ($this->text($dateRaw) === '' && $problem === '' && $trouble === '') { $sum['blank']++; continue; }

                if (++$total > self::MAX_ROWS) {
                    throw new \RuntimeException('Maksimal ' . self::MAX_ROWS . ' baris per upload. Pecah file menjadi beberapa bagian.');
                }
                $sum['read']++;
                $err = []; $warn = [];

                // ---- divisi ----
                $div = null; $divRaw = $this->text($get('division'));
                if ($divRaw !== '') {
                    $div = $divisions[mb_strtolower($divRaw)] ?? null;
                    if (!$div) { $err[] = "Divisi '$divRaw' tidak dikenal (Operation / Analyst / OnBoard / Infra)"; }
                } elseif ($hint) {
                    $div = $hint;
                } elseif ($defaultDivisionId && isset($divisionById[$defaultDivisionId])) {
                    $div = $divisionById[$defaultDivisionId];
                } else {
                    $err[] = 'Divisi kosong (isi kolom Divisi atau pilih divisi default saat upload)';
                }

                // ---- tanggal & jam ----
                $date = $this->parseDate($dateRaw, $dateFmt);
                if ($date === null) { $err[] = "Tanggal tidak valid: '" . $this->text($dateRaw) . "'"; }
                $start = $this->parseTime($get('start_time'), $e1); if ($e1) { $err[] = $e1; }
                $finish = $this->parseTime($get('finish_time'), $e2); if ($e2) { $err[] = $e2; }

                // ---- plan ----
                $planRaw = mb_strtolower($this->text($get('plan')));
                if ($planRaw === '') { $plan = 'unschedule'; $warn[] = 'Plan kosong, dianggap Unschedule'; }
                elseif (in_array($planRaw, ['schedule', 'scheduled', 'terjadwal'])) { $plan = 'schedule'; }
                elseif (in_array($planRaw, ['unschedule', 'unscheduled', 'tidak terjadwal', 'tdk terjadwal'])) { $plan = 'unschedule'; }
                else { $plan = 'unschedule'; $err[] = "Plan tidak valid: '$planRaw' (Schedule / Unschedule)"; }

                // ---- kode, induk, status ----
                $code = strtoupper($this->text($get('code')));
                if ($code !== '' && !preg_match('/^[A-Z]{2,4}[0-9]{1,6}$/', $code)) { $warn[] = "ID '$code' format tidak sesuai, dibuat otomatis"; $code = ''; }
                $parent = strtoupper($this->text($get('parent')));
                if ($parent !== '' && !preg_match('/^[A-Z]{2,4}[0-9]{1,6}$/', $parent)) { $err[] = "ID Induk '$parent' format tidak valid"; }

                $stVals = []; // semua nilai status yang terisi (Status Akhir, Status, Day 1)
                foreach ($cols['status'] ?? [] as $ci) {
                    $v = mb_strtolower($this->text($cells[$ci] ?? null));
                    if ($v !== '') { $stVals[] = $v; }
                }
                $stKey = $stVals[0] ?? '';
                if ($stKey === '') { $status = $parent !== '' ? 'continue' : 'finish'; }
                elseif (isset(self::$statusMap[$stKey])) { $status = self::$statusMap[$stKey]; }
                else { $status = 'finish'; $err[] = "Status tidak valid: '$stKey' (Finish / Pending / Continue / Closed)"; }
                // 'Finish' di salah satu kolom (mis. Day 1) berarti selesai dalam 1 shift; Status Akhir basi (mis. Pending) diabaikan
                if ($status !== 'finish' && $parent === '' && count($stVals) > 1) {
                    foreach ($stVals as $v) {
                        if ((self::$statusMap[$v] ?? null) === 'finish') {
                            $warn[] = 'Status bertentangan (' . implode(' / ', $stVals) . '): dipakai Finish';
                            $status = 'finish';
                            break;
                        }
                    }
                }

                // ---- teks & master ----
                $unit = $this->text($get('unit')); if ($unit === '-') { $unit = ''; }
                if (mb_strlen($unit) > 50) { $unit = mb_substr($unit, 0, 50); $warn[] = 'Unit dipotong 50 karakter'; }
                $names = [
                    'device' => $this->firstPart($this->dash($this->text($get('device')))),
                    'location' => $this->dash($this->text($get('location'))),
                    'tool' => $this->dash($this->text($get('tool'))),
                    'supervisor' => $this->dash($this->text($get('supervisor'))),
                ];
                $team = $this->splitNames($this->text($get('team')));
                foreach ($names as $type => $name) {
                    if ($name !== '' && !isset($maps[$type][mb_strtolower($name)])) {
                        if ($autoMaster) { $newMasters[$type][mb_strtolower($name)] = $name; }
                        else { $err[] = ucfirst($type) . " '$name' belum ada di Master"; }
                    }
                }
                foreach ($team as $name) {
                    if (!isset($maps['technician'][mb_strtolower($name)])) {
                        if ($autoMaster) { $newMasters['technician'][mb_strtolower($name)] = $name; }
                        else { $err[] = "Teknisi '$name' belum ada di Master"; }
                    }
                }
                $shift = $this->text($get('shift'));
                if ($shift !== '') {
                    $key = mb_strtolower($shift);
                    if (!isset($maps['shift'][$key]) && isset($maps['shift']['shift ' . $key])) { $shift = 'Shift ' . $shift; }
                    elseif (!isset($maps['shift'][$key])) { $warn[] = "Shift '$shift' tidak dikenal, dikosongkan"; $shift = ''; }
                }

                $row = [
                    'line' => $line, 'sheet' => $t['name'], 'state' => 'new', 'errors' => $err, 'warnings' => $warn,
                    'd' => [
                        'division_id' => $div ? $div->id : null, 'division' => $div ? $div->name : '', 'prefix' => $div ? $div->prefix : '',
                        'code' => $code, 'parent' => $parent, 'date' => $date, 'plan' => $plan, 'start' => $start, 'finish' => $finish,
                        'device' => $names['device'], 'shift' => $shift, 'unit' => $unit, 'location' => $names['location'],
                        'supervisor' => $names['supervisor'], 'team' => $team, 'problem' => $problem, 'troubleshoot' => $trouble,
                        'tool' => $names['tool'], 'status' => $status, 'remark' => $this->text($get('remark')),
                    ],
                ];

                if ($err) {
                    $row['state'] = 'error';
                } else {
                    $print = $this->fingerprint($div->id, $date, (string) $start, $unit, $problem);

                    if ($code !== '' && isset($dbCodes[$code])) {
                        if ($dbCodePrints[$code] === $print) {
                            $row['state'] = 'duplicate'; $row['warnings'][] = "ID $code sudah ada (isi sama)";
                        } elseif (isset($dbPrints[$print])) {
                            $row['state'] = 'duplicate'; $row['warnings'][] = "Isi sama sudah ada di database dengan ID lain (ID $code dipakai data berbeda)";
                        } else {
                            $row['state'] = 'error';
                            $row['errors'][] = "ID $code sudah dipakai data lain yang isinya berbeda. Ubah ID di file, atau kosongkan ID agar dibuat otomatis";
                        }
                    } elseif ($code !== '' && isset($fileCodes[$code])) {
                        if ($fileCodePrints[$code] === $print) {
                            $row['state'] = 'duplicate'; $row['warnings'][] = "ID $code dobel di file (isi sama)";
                        } else {
                            // ID kembar di file tapi isinya beda = pekerjaan berbeda -> jangan dibuang, beri ID baru
                            $row['warnings'][] = "ID $code dobel di file (isi berbeda): diberi ID baru otomatis";
                            $code = ''; $row['d']['code'] = '';
                        }
                    }

                    if ($row['state'] === 'new') {
                        // ID Induk = ID sendiri (umum di Excel): baris pertama rantai, bukan lanjutan
                        if ($code !== '' && $parent === $code) {
                            $parent = ''; $row['d']['parent'] = '';
                            $row['warnings'][] = "ID Induk sama dengan ID sendiri ($code): dianggap baris pertama rantai";
                        }
                        if ($status === 'finish' && $parent !== '') { $row['state'] = 'error'; $row['errors'][] = 'Status Finish tidak boleh punya ID Induk'; }
                        if ($status === 'continue' && $parent === '') { $row['state'] = 'error'; $row['errors'][] = 'Status Continue wajib punya ID Induk'; }
                    }
                    if ($row['state'] === 'new' && $code === '' && (isset($dbPrints[$print]) || isset($filePrints[$print]))) {
                        $row['state'] = 'duplicate'; $row['warnings'][] = 'Data yang sama sudah ada (tanggal, unit, jam, problem)';
                    }
                    if ($row['state'] === 'new' && $parent !== '' && !isset($dbCodes[$parent]) && !isset($fileCodes[$parent])) {
                        $row['state'] = 'error'; $row['errors'][] = "ID Induk $parent tidak ditemukan (di database maupun di baris sebelumnya)";
                    }
                    if ($row['state'] === 'new') {
                        if ($code !== '') { $fileCodes[$code] = true; $fileCodePrints[$code] = $print; }
                        $filePrints[$print] = true;
                    }
                }
                $sum[$row['state']]++;
                $out[] = $row;
            }
        }

        // master baru hanya dari baris yang benar-benar akan masuk
        $needed = ['device' => [], 'location' => [], 'tool' => [], 'supervisor' => [], 'technician' => []];
        foreach ($out as $r) {
            if ($r['state'] !== 'new') { continue; }
            foreach (['device', 'location', 'tool', 'supervisor'] as $type) {
                $n = $r['d'][$type];
                if ($n !== '' && isset($newMasters[$type][mb_strtolower($n)])) { $needed[$type][mb_strtolower($n)] = $n; }
            }
            foreach ($r['d']['team'] as $n) {
                if (isset($newMasters['technician'][mb_strtolower($n)])) { $needed['technician'][mb_strtolower($n)] = $n; }
            }
        }

        return [
            'rows' => $out, 'summary' => $sum, 'new_masters' => array_map('array_values', $needed),
            'sheets' => $sheets, 'columns_used' => array_keys($used), 'columns_ignored' => array_keys($ignored),
        ];
    }

    // =================================================================
    //  COMMIT
    // =================================================================
    public function commit(array $rows, string $filename): array
    {
        return DB::transaction(function () use ($rows, $filename) {
            $maps = [
                'device' => $this->nameMap(Device::class), 'location' => $this->nameMap(Location::class),
                'tool' => $this->nameMap(Tool::class), 'supervisor' => $this->nameMap(Supervisor::class),
                'technician' => $this->nameMap(Technician::class), 'shift' => $this->nameMap(Shift::class),
            ];
            $classes = ['device' => Device::class, 'location' => Location::class, 'tool' => Tool::class,
                'supervisor' => Supervisor::class, 'technician' => Technician::class];
            $made = ['device' => 0, 'location' => 0, 'tool' => 0, 'supervisor' => 0, 'technician' => 0];
            $resolve = function ($type, $name) use (&$maps, $classes, &$made) {
                if ($name === '') { return null; }
                $key = mb_strtolower($name);
                if (!isset($maps[$type][$key])) {
                    $attrs = ['name' => $name, 'is_active' => 1];
                    if ($type === 'supervisor') { $attrs['position'] = 'SPV'; }
                    $maps[$type][$key] = $classes[$type]::create($attrs)->id;
                    $made[$type]++;
                }
                return $maps[$type][$key];
            };

            $reserved = []; // ID yang diminta file: tidak boleh dipakai kode otomatis
            foreach ($rows as $r0) { if ($r0['d']['code'] !== '') { $reserved[$r0['d']['code']] = true; } }
            $counters = []; $inserted = 0; $skipped = 0;
            foreach ($rows as $r) {
                $d = $r['d'];
                $code = $d['code'];
                if ($code !== '' && Activity::withTrashed()->where('code', $code)->exists()) { $skipped++; continue; }
                if ($code === '') {
                    $prefix = $d['status'] === 'finish' ? $d['prefix'] : 'PD';
                    $code = $this->generate($prefix, $counters, $reserved);
                }
                $shiftId = $d['shift'] !== '' ? ($maps['shift'][mb_strtolower($d['shift'])] ?? null) : null;

                $a = Activity::create([
                    'code' => $code, 'division_id' => $d['division_id'], 'parent_code' => $d['parent'] !== '' ? $d['parent'] : null,
                    'activity_date' => $d['date'], 'shift_id' => $shiftId, 'plan' => $d['plan'],
                    'start_time' => $d['start'], 'finish_time' => $d['finish'],
                    'device_id' => $resolve('device', $d['device']), 'unit' => $d['unit'] !== '' ? $d['unit'] : null,
                    'location_id' => $resolve('location', $d['location']), 'supervisor_id' => $resolve('supervisor', $d['supervisor']),
                    'problem' => $d['problem'] !== '' ? $d['problem'] : null, 'troubleshoot' => $d['troubleshoot'] !== '' ? $d['troubleshoot'] : null,
                    'tool_id' => $resolve('tool', $d['tool']), 'status' => $d['status'],
                    'remark' => $d['remark'] !== '' ? $d['remark'] : null, 'created_by' => auth()->id(),
                ]);
                $techIds = [];
                foreach ($d['team'] as $n) { $techIds[] = $resolve('technician', $n); }
                $a->technicians()->sync(array_values(array_unique(array_filter($techIds))));
                ActivityLog::create(['activity_id' => $a->id, 'user_id' => auth()->id(), 'action' => 'import',
                    'changes' => ['file' => $filename, 'sheet' => $r['sheet'], 'line' => $r['line']]]);
                $inserted++;
            }
            return ['inserted' => $inserted, 'skipped' => $skipped, 'masters' => array_filter($made)];
        });
    }

    /** Kode otomatis: selalu di atas nomor tertinggi (DB + ID dari file), tidak mengisi lubang nomor. */
    private function generate(string $prefix, array &$counters, array $reserved): string
    {
        if (!isset($counters[$prefix])) {
            $n = (int) substr(Activity::nextCode($prefix), strlen($prefix));
            foreach ($reserved as $c => $_) {
                if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $c, $m)) { $n = max($n, (int) $m[1] + 1); }
            }
            $counters[$prefix] = $n;
        }
        do {
            $code = $prefix . str_pad((string) $counters[$prefix]++, 3, '0', STR_PAD_LEFT);
        } while (isset($reserved[$code]) || Activity::withTrashed()->where('code', $code)->exists());
        return $code;
    }

    // =================================================================
    //  PEMBACA FILE
    // =================================================================
    private function readCsv(string $path): array
    {
        $raw = file_get_contents($path);
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") { $raw = substr($raw, 3); }
        if (!mb_check_encoding($raw, 'UTF-8')) { $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'); }
        $head = strtok($raw, "\n");
        $delim = ',';
        $best = substr_count($head, ',');
        foreach ([';', "\t"] as $c) {
            if (substr_count($head, $c) > $best) { $best = substr_count($head, $c); $delim = $c; }
        }
        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw); rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $delim, '"', '\\')) !== false) { $rows[] = $r; }
        fclose($h);
        return $rows;
    }

    private function readSpreadsheet(string $path): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \RuntimeException('Pustaka Excel tidak tersedia di server. Simpan file sebagai CSV lalu upload ulang.');
        }
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $names  = $reader->listWorksheetNames($path);
        $known  = ['operation', 'analyst', 'onboard', 'infra', 'pending'];
        $wanted = array_values(array_filter($names, function ($n) use ($known) { return in_array(mb_strtolower(trim($n)), $known, true); }));
        if (!$wanted) { $wanted = [$names[0]]; } // workbook 1 sheet: pakai sheet pertama
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly($wanted);
        $book = $reader->load($path);
        $tables = [];
        foreach ($wanted as $n) {
            $ws = $book->getSheetByName($n);
            if (!$ws) { continue; }
            try { $rows = $ws->toArray(null, true, false, false); }
            catch (\Throwable $e) { $rows = $ws->toArray(null, false, false, false); }
            $tables[] = ['name' => $n, 'rows' => $rows];
        }
        return $tables;
    }

    // =================================================================
    //  UTIL
    // =================================================================
    /** @return array [indeks baris header | null, peta field => [indeks kolom], _used, _ignored] */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 15, true) as $i => $cells) {
            $norm = [];
            foreach ($cells as $ci => $c) { $norm[$ci] = preg_replace('/[^a-z0-9]/', '', mb_strtolower($this->text($c))); }
            $map = []; $used = []; $claimed = [];
            foreach (self::$aliases as $field => $list) {
                foreach ($list as $alias) {
                    foreach ($norm as $ci => $n) {
                        if ($n === $alias) { $map[$field][] = $ci; $claimed[$ci] = true; $used[$field] = true; }
                    }
                }
            }
            if (isset($map['date'])) {
                $ignored = [];
                foreach ($cells as $ci => $c) {
                    $label = $this->text($c);
                    if ($label !== '' && !isset($claimed[$ci])) { $ignored[] = $label; }
                }
                $map['_used'] = array_keys($used);
                $map['_ignored'] = $ignored;
                return [$i, $map];
            }
        }
        return [null, null];
    }

    private function nameMap(string $class): array
    {
        $m = [];
        foreach ($class::all() as $r) { $m[mb_strtolower(trim($r->name))] = $r->id; }
        return $m;
    }

    private function fingerprint($divisionId, $date, $start, $unit, $problem): string
    {
        $start = $start === '' ? '' : substr($start, 0, 5);
        return md5($divisionId . '|' . $date . '|' . $start . '|' . mb_strtolower(trim($unit)) . '|' . mb_strtolower(trim($problem)));
    }

    private function first(array $cells, array $cols)
    {
        foreach ($cols as $ci) {
            $v = $cells[$ci] ?? null;
            if ($v !== null && $v !== '') { return $v; }
        }
        return null;
    }

    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $c) {
            if ($c !== null && trim((string) $c) !== '') { return false; }
        }
        return true;
    }

    private function text($v): string
    {
        if ($v === null) { return ''; }
        if ($v instanceof DateTimeInterface) { return $v->format('Y-m-d H:i:s'); }
        if (is_bool($v)) { return $v ? '1' : '0'; }
        if (is_float($v) || is_int($v)) { return ($v == floor($v)) ? (string) (int) $v : (string) $v; }
        $s = trim(str_replace("\xC2\xA0", ' ', (string) $v));
        return ($s !== '' && $s[0] === '#' && preg_match('/^#[A-Z0-9\/!?]+$/', $s)) ? '' : $s; // #REF!, #VALUE!, dst
    }

    private function multiline($v): string
    {
        if ($v === null) { return ''; }
        $s = str_replace(["\r\n", "\r", "\xC2\xA0", "\xE2\x81\xA0"], ["\n", "\n", ' ', ''], (string) $v);
        $lines = array_map('rtrim', explode("\n", $s));
        return trim(implode("\n", $lines));
    }

    private function dash(string $s): string { return $s === '-' ? '' : $s; }

    private function firstPart(string $s): string
    {
        $p = preg_split('/\s*[,;]\s*/', $s);
        return trim($p[0] ?? '');
    }

    private function splitNames(string $s): array
    {
        $out = [];
        foreach (preg_split('/\s*[,;\n]+\s*/', $s) as $n) {
            $n = trim($n);
            if ($n === '' || $n === '-') { continue; }
            $out[mb_strtolower($n)] = $n;
        }
        return array_values($out);
    }

    /** @return string|null  Y-m-d */
    private function parseDate($v, string $fmt)
    {
        if ($v === null || $v === '') { return null; }
        if ($v instanceof DateTimeInterface) { return $v->format('Y-m-d'); }
        if (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^\d{5}(\.\d+)?$/', trim($v)))) {
            $n = (float) $v;
            if ($n < 20000 || $n > 80000) { return null; }
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($n)->format('Y-m-d');
            } catch (\Throwable $e) { return null; }
        }
        $s = trim((string) $v);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/', $s, $m)) {
            $y = (int) $m[3]; if ($y < 100) { $y += 2000; }
            $a = (int) $m[1]; $b = (int) $m[2];
            $month = $fmt === 'dmy' ? $b : $a;
            $day   = $fmt === 'dmy' ? $a : $b;
            return checkdate($month, $day, $y) ? sprintf('%04d-%02d-%02d', $y, $month, $day) : null;
        }
        return null;
    }

    /** @return string|null  H:i:00 ; $err diisi bila format tidak valid */
    private function parseTime($v, &$err = null)
    {
        $err = null;
        if ($v === null || $v === '') { return null; }
        if ($v instanceof DateTimeInterface) { return $v->format('H:i') . ':00'; }
        if (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^\d+([.,]\d+)?$/', trim($v)) && strpos((string) $v, ':') === false)) {
            $n = (float) str_replace(',', '.', (string) $v);
            if ($n >= 0 && $n < 1) {                       // pecahan hari Excel
                $sec = (int) round($n * 86400);
                return sprintf('%02d:%02d:00', intdiv($sec, 3600) % 24, intdiv($sec % 3600, 60));
            }
            if ($n >= 1 && $n < 24) {                      // "8.25" = 08:25
                $h = (int) floor($n); $mm = (int) round(($n - $h) * 100);
                if ($mm < 60) { return sprintf('%02d:%02d:00', $h, $mm); }
            }
            $err = "Jam tidak valid: '$v'"; return null;
        }
        $s = trim((string) $v);
        if (preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?\s*(am|pm)?$/i', $s, $m)) {
            $h = (int) $m[1]; $mm = (int) $m[2];
            if (!empty($m[3])) {
                $pm = strtolower($m[3]) === 'pm';
                if ($h === 12) { $h = $pm ? 12 : 0; } elseif ($pm) { $h += 12; }
            }
            if ($h < 24 && $mm < 60) { return sprintf('%02d:%02d:00', $h, $mm); }
        }
        $err = "Jam tidak valid: '$s'";
        return null;
    }
}
