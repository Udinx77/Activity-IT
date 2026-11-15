<?php

namespace App\Services\Act;

use App\Models\Act\Device;
use App\Models\Act\Location;

/**
 * Isian bebas Device / Lokasi pada form: dicocokkan dengan master (tanpa beda huruf besar-kecil & spasi);
 * bila belum ada dibuat baru supaya muncul sebagai saran berikutnya.
 */
class MasterResolver
{
    /** Cari/buat master berdasarkan nama. Kosong atau "-" = tidak ada (null). */
    public static function id(string $class, ?string $name): ?int
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
        if ($name === '' || $name === '-') { return null; }
        $m = $class::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->first();
        if (!$m) { $m = $class::create(['name' => $name, 'is_active' => 1]); }
        elseif (!$m->is_active) { $m->update(['is_active' => 1]); }
        return (int) $m->id;
    }

    /**
     * Ubah `device_name` / `location_name` hasil form menjadi `device_id` / `location_id`.
     * Bila field nama tidak dikirim, `device_id` / `location_id` apa adanya dibiarkan (kompatibel dengan form/API lama).
     */
    public static function apply(array $data): array
    {
        foreach (['device' => Device::class, 'location' => Location::class] as $key => $class) {
            $field = $key . '_name';
            if (array_key_exists($field, $data)) {
                $data[$key . '_id'] = self::id($class, $data[$field]);
                unset($data[$field]);
            }
        }
        return $data;
    }
}
