# Activity Plan & Action — Operational Team & Incident Tracking Module (Laravel)

Modul integrasi berbasis **Laravel 8 / PHP 8.1** untuk perencanaan aktivitas harian berbasis tim (*shift-based team dispatch*), pencatatan *troubleshooting*, pelacakan pekerjaan lintas *shift* (*pending chain*), serta impor data historis dari Excel/CSV[cite: 10, 12, 15]. Dirancang sebagai paket *plug-and-play* dengan skrip instalasi otomatis (*non-intrusive*) di atas aplikasi utama IDA.

## Fitur Utama

* **Shift-Based Team Generator & Roster Integration:** Membaca jadwal kehadiran karyawan (`data_attendances` & `data_personnels`) secara otomatis berdasarkan zona waktu operasional **WITA (`Asia/Makassar`)**, mendeteksi *Shift 1* (`07:00–17:00`, kode `H`) dan *Shift 2* (`17:01–06:59`, kode `M`, termasuk penanganan pergantian hari lewat tengah malam), serta mengenali peran pengawas (*Supervisor SCH/UNS*) vs teknisi.
* **Interactive Team Activity Board:** Papan kerja per divisi (*Operation*, *Analyst*, *OnBoard*, *Infra*) untuk mengelola daftar rencana kerja (*plan checklist*), memantau progres *real-time*, dan mengunci susunan anggota tim secara otomatis setelah proses *troubleshooting* dimulai[cite: 10, 13].
* **Structured Troubleshoot & Cross-Shift Pending Chain:** Pencatatan langkah penanganan (*done*, *remaining*, *next plan*) serta kategorisasi alasan *pending*[cite: 10, 14]. Pekerjaan yang belum selesai otomatis membentuk rantai pelacakan lintas *shift* menggunakan kode induk (`PD###` / `parent_code`) hingga berstatus `Closed`[cite: 10, 13, 15].
* **Smart Excel / CSV Importer (`ActivityImporter`):** Mendukung unggahan multi-*sheet* `.xlsx`/`.xls` (via `PhpSpreadsheet`) maupun `.csv` dengan deteksi *encoding* otomatis, pencocokan alias kolom fleksibel, konversi format tanggal/jam serial Excel, serta deteksi duplikasi berbasis *MD5 content fingerprinting* sebelum transaksi disimpan ke database[cite: 15].
* **Auto-Learning Master Data (`MasterResolver`):** Input bebas pada kolom *Device* dan *Location* otomatis dinormalisasi (huruf besar/kecil dan spasi) dan ditambahkan ke tabel master sebagai saran otomatis pada pengisian berikutnya[cite: 10, 16].
* **Shift Handover Export:** Pembuatan laporan cetak / PDF seluruh tim dalam satu *shift* serta format ringkasan siap salin untuk koordinasi via WhatsApp[cite: 10, 12].

## Arsitektur & Struktur Paket

```text
├── app/
│   ├── Http/Controllers/Act/    # 9 Controller (Dashboard, Team, Plan, Activity, Pending, Closed, Import, Export, Master)
│   ├── Models/Act/              # 13 Eloquent Model (prefiks tabel act_*)
│   └── Services/Act/
│       ├── RosterService.php    # Kalkulasi shift WITA, parsing roster H/M, & deteksi jabatan pengawas
│       ├── ActivityImporter.php # Parser CSV/XLSX, validasi rantai ID induk, & batch commit transaksional
│       └── MasterResolver.php   # Normalisasi & auto-create master Device/Location
├── database/
│   ├── act_install.sql          # Skema 11 tabel utama (v1) + seed data master
│   └── act_v2_teams.sql         # Ekstensi 4 tabel manajemen tim, plan, & detail troubleshoot (v2)
├── resources/views/act/         # Antarmuka Blade (AdminLTE + DataTables)
├── routes/activity.php          # Definisi route modular (/activity, nama act.*)
└── install.sh                   # Skrip deployment otomatis dengan backup & injeksi route/sidebar
```

## Skema Database

Seluruh tabel menggunakan prefiks `act_` agar terisolasi dan tidak mengubah struktur tabel bawaan aplikasi induk[cite: 10, 13]:

* **Master & Konfigurasi:** `act_divisions`, `act_supervisors`, `act_technicians`, `act_devices`, `act_locations`, `act_tools`, `act_shifts`, `act_settings`[cite: 13].
* **Manajemen Tim & Rencana (v2):** `act_teams`, `act_team_members` (dengan *unique constraint* per `team_date`, `shift`, `nik`), `act_plans`.
* **Eksekusi & Audit Log:** `act_activities`, `act_activity_technicians`, `act_troubleshoots`, `act_activity_logs`[cite: 13, 14].

## Teknologi yang Digunakan

* **Backend:** PHP 8.1, Laravel 8 (Eloquent ORM, DB Transactions, Blade)
* **Database:** MariaDB 10.2+ / MySQL (`InnoDB`, `utf8mb4`)[cite: 13, 14]
* **Frontend:** AdminLTE, Bootstrap, DataTables (Buttons Export Excel/PDF/Print), Vanilla JavaScript
* **Deployment & Tooling:** Bash (`install.sh`), Python 3 (injeksi template Blade otomatis), PhpSpreadsheet[cite: 11, 15]

## Panduan Instalasi

1. **Import Skema Database** (via phpMyAdmin atau MySQL CLI pada database aplikasi induk):
   ```bash
   mysql -u username -p nama_database < database/act_install.sql
   mysql -u username -p nama_database < database/act_v2_teams.sql
   ```

2. **Jalankan Skrip Installer Otomatis** (arahkan ke direktori root Laravel yang memiliki file `artisan`):
   ```bash
   bash install.sh /path/ke/aplikasi-laravel
   ```
   *Skrip akan otomatis membuat cadangan `routes/web.php` dan `sidebar.blade.php` (`*.bak.TIMESTAMP`), menyalin seluruh modul, mendaftarkan route di dalam grup middleware `auth`, serta membersihkan cache route dan view[cite: 10, 11].*