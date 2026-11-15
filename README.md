# Activity Plan & Action — Operational Team & Incident Tracking Module (Laravel)

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat&logo=php&logoColor=white)
![Framework](https://img.shields.io/badge/Laravel-8.x-FF2D20?style=flat&logo=laravel&logoColor=white)
![Database](https://img.shields.io/badge/Database-MariaDB_%7C_MySQL-003545?style=flat&logo=mariadb&logoColor=white)
![Frontend](https://img.shields.io/badge/Frontend-AdminLTE_%7C_Blade_%7C_DataTables-3B82F6?style=flat)
![Deployment](https://img.shields.io/badge/Deployment-Bash_%26_Python_Installer-4EAA25?style=flat&logo=gnubash&logoColor=white)

Modul integrasi berbasis **Laravel 8 / PHP 8.1** untuk perencanaan aktivitas operasional harian berbasis tim (*shift-based team dispatch*), pencatatan *troubleshooting* terstruktur, pelacakan pekerjaan lintas *shift* (*pending chain*), serta impor data historis dari Excel/CSV.

Dirancang dengan arsitektur *plug-and-play* (*non-intrusive*) lengkap dengan skrip instalasi otomatis (`install.sh`) sehingga dapat disisipkan ke dalam aplikasi induk Laravel tanpa mengubah tabel maupun autentikasi bawaan.

---

## Fitur Utama

- **Shift-Based Team Generator & Roster Integration (`RosterService`):** Membaca jadwal kehadiran karyawan (`data_attendances` & `data_personnels`) secara otomatis berdasarkan zona waktu operasional **WITA (`Asia/Makassar`)**, mendeteksi *Shift 1* (`07:00–17:00`, kode `H`) dan *Shift 2* (`17:01–06:59`, kode `M`, termasuk penanganan pergantian hari lewat tengah malam), serta memisahkan peran pengawas (*Supervisor SCH/UNS*) dan teknisi.
- **Interactive Team Activity Board:** Papan kerja per divisi (*Operation*, *Analyst*, *OnBoard*, *Infra*) untuk mengelola daftar rencana kerja (*plan checklist*), memantau progres *real-time*, dan mengunci susunan anggota tim secara otomatis setelah proses *troubleshooting* dimulai.
- **Structured Troubleshoot & Cross-Shift Pending Chain:** Pencatatan langkah penanganan (*done*, *remaining*, *next plan*) beserta kategorisasi alasan *pending*. Pekerjaan yang belum tuntas dalam satu *shift* otomatis membentuk rantai pelacakan lintas *shift* melalui kode induk (`PD###` / `parent_code`) hingga berstatus `Closed`.
- **Smart Excel / CSV Importer (`ActivityImporter`):** Mendukung unggahan multi-*sheet* `.xlsx`/`.xls` (via `PhpSpreadsheet`) maupun `.csv` dengan deteksi *encoding* otomatis, pencocokan alias kolom fleksibel, konversi format tanggal/jam serial Excel, serta deteksi duplikasi berbasis *MD5 content fingerprinting* sebelum transaksi disimpan ke database.
- **Auto-Learning Master Data (`MasterResolver`):** Input bebas pada kolom *Device* dan *Location* otomatis dinormalisasi (huruf besar/kecil dan spasi) lalu disimpan ke tabel master agar muncul sebagai saran otomatis pada pengisian berikutnya.
- **Shift Handover Export & Reporting:** Pembuatan halaman cetak / PDF seluruh tim dalam satu *shift*, ringkasan siap salin untuk koordinasi via WhatsApp, serta rekapitulasi periode *Daily*, *Weekly*, dan *Monthly* via DataTables Buttons.

---

## Arsitektur Sistem

```text
┌──────────────────────────────────────────────────────────────────────┐
│                   Aplikasi Induk Laravel (Host App)                  │
│  • Auth Middleware & Session       • Roster Tables (data_attendances │
│  • routes/web.php & Sidebar Blade    & data_personnels)              │
└───────────────┬──────────────────────────────────────┬───────────────┘
                │ (Injeksi via install.sh)             │ (Read-Only Query)
                ▼                                      ▼
┌──────────────────────────────────────────────────────────────────────┐
│                Modul Activity Plan & Action (/activity)              │
│                                                                      │
│  ┌─────────────────────┐  ┌───────────────────┐  ┌────────────────┐  │
│  │   RosterService     │  │ ActivityImporter  │  │ MasterResolver │  │
│  │ • Kalkulasi WITA    │  │ • Parser CSV/XLSX │  │ • Auto-Create  │  │
│  │ • Filter Shift H/M  │  │ • MD5 Fingerprint │  │   Device/Loc   │  │
│  └──────────┬──────────┘  └─────────┬─────────┘  └───────┬────────┘  │
│             └───────────────────────┼────────────────────┘           │
│                                     ▼                                │
│                  [ 9 Controllers & 13 Eloquent Models ]              │
└─────────────────────────────────────┬────────────────────────────────┘
                                      │ Eloquent ORM (DB Transactions)
                                      ▼
┌──────────────────────────────────────────────────────────────────────┐
│                 Database MariaDB / MySQL (Prefiks act_*)             │
│  • Master: act_divisions, act_supervisors, act_technicians, dll.     │
│  • Tim v2: act_teams, act_team_members, act_plans                    │
│  • Log   : act_activities, act_troubleshoots, act_activity_logs      │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Aturan Bisnis & Operasional Shift

| Parameter | Aturan Sistem | Keterangan |
|---|---|---|
| **Zona Waktu Kerja** | `Asia/Makassar` (WITA / UTC+8) | Dihitung independen di `RosterService` meskipun konfigurasi aplikasi induk menggunakan `UTC`. |
| **Shift 1 (Siang)** | `07:00 – 17:00` (Kode Roster: `H`) | Manpower dengan kode kehadiran mengandung `H` otomatis masuk daftar kandidat Shift 1. |
| **Shift 2 (Malam)** | `17:01 – 06:59` (Kode Roster: `M`) | Aktivitas pada jam `00:00 – 06:59` otomatis dihitung sebagai bagian dari Shift 2 tanggal sebelumnya. |
| **Deteksi Pengawas** | Jabatan mengandung `Supervisor`/`SPV` + `SCH`/`UNS` | Tipe rencana tim (*Schedule* / *Unschedule*) otomatis mengikuti jabatan pengawas yang dipilih. |
| **Penomoran ID** | `OP###`, `AL###`, `ONB###`, `IF###`, `PD###` | Kode otomatis berurutan per divisi untuk pekerjaan *Finish* 1 shift, dan `PD###` untuk rantai *Pending*. |
| **Penguncian Tim** | Terkunci setelah ada *follow-up troubleshoot* | Anggota tim dapat diubah/ditukar selama belum ada hasil *troubleshoot* yang disimpan. |

---

## Struktur Paket

```text
activity-plan-action/
├── app/
│   ├── Http/Controllers/Act/
│   │   ├── ActivityController.php   # CRUD laporan activity harian/mingguan/bulanan (Finish 1 shift)
│   │   ├── ClosedController.php     # Rekap gabungan job Finish dan rantai Pending yang sudah Closed
│   │   ├── DashboardController.php  # Papan tim per divisi, kartu ringkasan, grafik 7 hari & aging pending
│   │   ├── ExportController.php     # Halaman cetak PDF semua tim per shift & generator teks WhatsApp
│   │   ├── ImportController.php     # Alur upload CSV/XLSX -> preview validasi -> konfirmasi simpan
│   │   ├── MasterController.php     # Manajemen data master (Shift, Device, Lokasi, Sarana, Pengawas, Teknisi)
│   │   ├── PendingController.php    # Manajemen pekerjaan lintas shift, lanjutan (Continue), & timeline
│   │   ├── PlanController.php       # Pengelolaan plan ceklis tim, bawa pending kemarin, & follow-up
│   │   └── TeamController.php       # Generate tim manual dari roster kehadiran per tanggal & shift
│   ├── Models/Act/                  # 13 Eloquent Model (Activity, Team, TeamMember, Plan, Troubleshoot, dll.)
│   └── Services/Act/
│       ├── ActivityImporter.php     # Parser CSV/XLSX, validasi rantai ID induk, & batch commit transaksional
│       ├── MasterResolver.php       # Normalisasi & auto-create master Device/Location dari input bebas
│       └── RosterService.php        # Kalkulasi shift WITA, parsing roster H/M, & deteksi jabatan pengawas
├── database/
│   ├── act_install.sql              # Skema 11 tabel utama (v1) + seed data awal
│   └── act_v2_teams.sql             # Ekstensi 4 tabel manajemen tim, plan ceklis, & detail troubleshoot (v2)
├── resources/views/act/             # Antarmuka Blade (AdminLTE, DataTables, & komponen partial)
├── routes/activity.php              # Definisi route modular (prefix /activity, nama act.*)
└── install.sh                       # Skrip deployment otomatis dengan backup & injeksi route/sidebar
```

---

## Skema Database

Seluruh tabel menggunakan prefiks `act_` agar terisolasi penuh dari tabel bawaan aplikasi induk:

- **Master & Konfigurasi:** `act_divisions`, `act_supervisors`, `act_technicians`, `act_devices`, `act_locations`, `act_tools`, `act_shifts`, `act_settings`.
- **Manajemen Tim & Rencana (v2):** `act_teams`, `act_team_members` (dengan *unique constraint* pada kombinasi `team_date`, `shift`, `nik` agar satu personel hanya berada di satu tim per *shift*), dan `act_plans`.
- **Eksekusi & Audit Log:** `act_activities`, `act_activity_technicians`, `act_troubleshoots`, dan `act_activity_logs`.

---

## Ringkasan Modul & Route (`/activity`)

Seluruh *endpoint* dimuat di dalam grup *middleware* `auth` aplikasi induk dengan prefiks URL `/activity` dan nama *route* `act.*`:

| Prefix Route | Controller | Fungsi Utama |
|---|---|---|
| `GET /activity/dashboard` | `DashboardController` | Papan tim per divisi pada *shift* aktif, statistik ringkasan, dan daftar *pending* terlama. |
| `/activity/teams/*` | `TeamController` | Pembentukan tim dari kandidat roster harian (`H`/`M`), pengeditan anggota, dan penghapusan tim. |
| `/activity/plans/*` | `PlanController` | Penambahan *plan* kerja tim, penarikan *pending* sebelumnya (*carry over*), dan *follow-up* hasil kerja. |
| `GET /activity/export` | `ExportController` | Tampilan siap cetak / PDF untuk seluruh tim dalam satu *shift* beserta ringkasan teks WhatsApp. |
| `/activity/data/*` | `ActivityController` | Daftar dan CRUD aktivitas yang selesai dalam satu *shift* (*Daily*, *Weekly*, *Monthly*). |
| `/activity/pending/*` | `PendingController` | Pelacakan pekerjaan lintas *shift*, pembuatan lanjutan (*Continue*), dan tampilan *timeline* riwayat. |
| `GET /activity/closed` | `ClosedController` | Arsip gabungan aktivitas *Finish* dan rantai *Pending* yang telah berstatus *Closed*. |
| `/activity/import/*` | `ImportController` | Unduh templat CSV, unggah Excel/CSV, pratinjau validasi baris, dan eksekusi impor massal. |
| `/activity/master/*` | `MasterController` | Pengelolaan data master operasional dan pengaktifan/penonaktifan item (*toggle active*). |

---

## Panduan Instalasi & Rollback

### Prasyarat
- **PHP 8.1** atau lebih baru dengan ekstensi `pdo_mysql`, `mbstring`, dan `zip` (untuk `PhpSpreadsheet`).
- **Laravel 8.x** dengan *template* berbasis AdminLTE.
- **MariaDB 10.2+** atau **MySQL 8.0+**.

### Langkah Pemasangan

1. **Import Skema Database** (via phpMyAdmin atau MySQL CLI ke database aplikasi induk):
   ```bash
   mysql -u username -p nama_database < database/act_install.sql
   mysql -u username -p nama_database < database/act_v2_teams.sql
   ```

2. **Jalankan Skrip Installer Otomatis** (arahkan ke direktori *root* Laravel yang memiliki file `artisan`):
   ```bash
   bash install.sh /path/ke/aplikasi-laravel
   ```
   *Skrip akan otomatis membuat cadangan `routes/web.php` dan `sidebar.blade.php` (`*.bak.TIMESTAMP`), menyalin seluruh file modul, mendaftarkan `require __DIR__.'/activity.php';` di dalam grup `auth`, menyisipkan menu sidebar, serta membersihkan cache `route`, `view`, dan `config`.*

3. **Verifikasi Konfigurasi Jabatan Pengawas (Opsional):**
   Jika penamaan jabatan pengawas pada tabel `data_personnels` memiliki format khusus, sesuaikan aturan pencocokannya pada fungsi `supervisorType()` di dalam `app/Services/Act/RosterService.php`.

### Pembatalan Instalasi (Rollback)
Jika ingin mengembalikan `routes/web.php` dan *sidebar* ke kondisi sebelum instalasi:
```bash
cp routes/web.php.bak.TIMESTAMP routes/web.php
cp resources/views/includes/sidebar.blade.php.bak.TIMESTAMP resources/views/includes/sidebar.blade.php
php artisan route:clear && php artisan view:clear
```
