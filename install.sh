#!/usr/bin/env bash
# =====================================================================
#  Pasang paket "Activity Plan & Action" ke aplikasi IDA (Laravel 8)
#  Pakai :  bash install.sh /path/ke/folder/IDA
#           (folder IDA = folder yang berisi file "artisan" dan "routes/web.php")
#  Aman  :  file yang diubah (routes/web.php & sidebar) di-backup dulu (*.bak.WAKTU)
# =====================================================================
set -euo pipefail

APP="${1:-}"
if [ -z "$APP" ] || [ ! -f "$APP/artisan" ]; then
  echo "Pakai : bash install.sh /path/ke/IDA   (harus berisi file 'artisan')"
  echo "Cari  : find ~ -maxdepth 3 -name artisan -not -path '*/vendor/*' 2>/dev/null"
  exit 1
fi
HERE="$(cd "$(dirname "$0")" && pwd)"
STAMP="$(date +%Y%m%d%H%M%S)"
WEB="$APP/routes/web.php"
SIDE="$APP/resources/views/includes/sidebar.blade.php"

[ -f "$WEB" ]  || { echo "routes/web.php tidak ditemukan di $APP"; exit 1; }
[ -f "$SIDE" ] || { echo "includes/sidebar.blade.php tidak ditemukan di $APP"; exit 1; }
grep -q "name('logout')" "$WEB" || { echo "Titik sisip route (logout) tidak ditemukan di routes/web.php"; exit 1; }
grep -q 'laravel/framework' "$APP/composer.json" || { echo "composer.json bukan project Laravel"; exit 1; }

echo "1/5 Backup web.php & sidebar ..."
cp "$WEB"  "$WEB.bak.$STAMP"
cp "$SIDE" "$SIDE.bak.$STAMP"

echo "2/5 Salin file (model, controller, service, view, route) ..."
mkdir -p "$APP/app/Models/Act" "$APP/app/Http/Controllers/Act" "$APP/app/Services/Act" "$APP/resources/views/act" "$APP/storage/app/act_import"
cp -r "$HERE/app/Models/Act/."            "$APP/app/Models/Act/"
cp -r "$HERE/app/Http/Controllers/Act/."  "$APP/app/Http/Controllers/Act/"
cp -r "$HERE/app/Services/Act/."          "$APP/app/Services/Act/"
cp -r "$HERE/resources/views/act/."       "$APP/resources/views/act/"
cp    "$HERE/routes/activity.php"         "$APP/routes/activity.php"

echo "3/5 Daftarkan route di routes/web.php (di dalam group auth) ..."
if grep -q "activity.php" "$WEB"; then
  echo "    sudah terdaftar, dilewati"
else
  sed -i "/->name('logout');/a require __DIR__.'/activity.php';" "$WEB"
fi

echo "4/5 Ganti menu 'Activity Plan & Action' di sidebar ..."
if grep -q "act.partials.menu" "$SIDE"; then
  echo "    sudah terpasang, dilewati"
else
  python3 - "$SIDE" <<'PY'
import re, sys
p = sys.argv[1]
lines = open(p, encoding='utf-8').read().split('\n')
start = next((i for i, l in enumerate(lines) if re.search(r'nav-header">\s*Activity Plan\s*(&amp;|&)\s*Action', l)), None)
if start is None:
    sys.exit("Header 'Activity Plan & Action' tidak ditemukan di sidebar. Ganti manual: lihat README.")
end = next((i for i in range(start + 1, len(lines)) if lines[i].strip() == '<hr>'), None)
if end is None:
    sys.exit("Penutup blok (<hr>) tidak ditemukan. Ganti manual: lihat README.")
lines[start:end] = ["            @include('act.partials.menu')"]
open(p, 'w', encoding='utf-8').write('\n'.join(lines))
print("    blok lama (%d baris) diganti" % (end - start))
PY
fi

echo "5/5 Bersihkan cache Laravel ..."
cd "$APP"
php artisan route:clear  >/dev/null 2>&1 || true
php artisan view:clear   >/dev/null 2>&1 || true
php artisan config:clear >/dev/null 2>&1 || true
if php artisan route:list --path=activity 2>/dev/null | grep -q "act.dashboard"; then
  echo "    route terdaftar: OK"
else
  echo "    PERINGATAN: route belum terbaca. Cek: php artisan route:list --path=activity"
fi

echo
echo "SELESAI. Langkah terakhir (lewat phpMyAdmin, database IDA):"
echo "  - instalasi BARU : import database/act_install.sql, lalu database/act_v2_teams.sql"
echo "  - sudah terpasang: cukup import database/act_v2_teams.sql (aman diulang)"
echo "Lalu buka menu 'Activity Plan & Action' di IDA."
echo "Batalkan pemasangan: cp $WEB.bak.$STAMP $WEB ; cp $SIDE.bak.$STAMP $SIDE"
