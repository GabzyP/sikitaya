<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShippingService
{
    public function mode(): string
    {
        return config('rajaongkir.mode') === 'api' ? 'api' : 'dummy';
    }

    public function isDummy(): bool
    {
        return $this->mode() === 'dummy';
    }

    /**
     * @return Collection<int, array{id:int,province_id:int,province:string,type:string,city_name:string,postal_code:string}>
     */
    public function cities(): Collection
    {
        if ($this->isDummy()) {
            return $this->dummyCities();
        }

        return Cache::remember('rajaongkir.cities', now()->addDay(), function () {
            try {
                $response = Http::timeout(config('rajaongkir.timeout'))
                    ->acceptJson()
                    ->withHeaders(['key' => (string) config('rajaongkir.key')])
                    ->get(config('rajaongkir.base_url').'/city');

                if (! $response->successful()) {
                    throw new ShippingException('RajaOngkir menolak permintaan daftar kota (HTTP '.$response->status().').');
                }

                $payload = $response->json();

                if (($payload['rajaongkir']['status']['code'] ?? null) !== 200) {
                    throw new ShippingException($payload['rajaongkir']['status']['description'] ?? 'Daftar kota RajaOngkir tidak tersedia.');
                }

                return collect($payload['rajaongkir']['results'])->map(fn (array $city) => [
                    'id' => (int) $city['city_id'],
                    'province_id' => (int) $city['province_id'],
                    'province' => $city['province'],
                    'type' => $city['type'],
                    'city_name' => $city['type'].' '.$city['city_name'],
                    'postal_code' => $city['postal_code'] ?? '',
                ])->sortBy([['province', 'asc'], ['city_name', 'asc']])->values();
            } catch (ShippingException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                Log::error('Gagal mengambil daftar kota RajaOngkir.', ['exception' => $exception]);

                throw new ShippingException('Layanan RajaOngkir sedang tidak dapat dihubungi. Silakan coba beberapa saat lagi.');
            }
        });
    }

    /**
     * @return array{id:int,province_id:int,province:string,type:string,city_name:string,postal_code:string}|null
     */
    public function findCity(int $cityId): ?array
    {
        try {
            return $this->cities()->firstWhere('id', $cityId);
        } catch (ShippingException $exception) {
            throw $exception;
        }
    }

    /**
     * Semua opsi kurir untuk satu pengiriman.
     *
     * @return list<array{courier:string,courier_name:string,service:string,description:string,cost:int,etd:string}>
     */
    public function options(int $originCityId, int $destinationCityId, int $weight): array
    {
        if ($weight < 1) {
            throw new ShippingException('Berat pengiriman tidak valid.');
        }

        if ($this->isDummy()) {
            return $this->dummyOptions($originCityId, $destinationCityId, $weight);
        }

        $options = [];

        foreach (array_keys(config('rajaongkir.couriers')) as $courier) {
            array_push($options, ...$this->apiCost($originCityId, $destinationCityId, $weight, $courier));
        }

        if ($options === []) {
            throw new ShippingException('Tidak ada layanan pengiriman yang tersedia untuk rute ini.');
        }

        return $options;
    }

    /**
     * @return list<array{courier:string,courier_name:string,service:string,description:string,cost:int,etd:string}>
     */
    public function cost(int $originCityId, int $destinationCityId, int $weight, string $courier): array
    {
        if (! array_key_exists($courier, config('rajaongkir.couriers'))) {
            throw new ShippingException('Kurir yang dipilih tidak didukung.');
        }

        if ($weight < 1) {
            throw new ShippingException('Berat pengiriman tidak valid.');
        }

        if ($this->isDummy()) {
            return array_values(array_filter(
                $this->dummyOptions($originCityId, $destinationCityId, $weight),
                fn (array $option) => $option['courier'] === $courier
            ));
        }

        $options = $this->apiCost($originCityId, $destinationCityId, $weight, $courier);

        if ($options === []) {
            throw new ShippingException('Kurir '.strtoupper($courier).' tidak memiliki layanan untuk rute ini.');
        }

        return $options;
    }

    /**
     * @return Collection<int, array{id:int,province_id:int,province:string,type:string,city_name:string,postal_code:string}>
     */
    private function dummyCities(): Collection
    {
        return Cache::rememberForever('rajaongkir.dummy.cities', function () {
            $path = database_path('data/rajaongkir_cities.json');

            if (! File::exists($path)) {
                throw new ShippingException('Data kota simulasi tidak ditemukan.');
            }

            return collect(File::json($path))
                ->map(fn (array $city) => [
                    'id' => (int) $city['id'],
                    'province_id' => (int) $city['province_id'],
                    'province' => $city['province'],
                    'type' => $city['type'],
                    'city_name' => $city['city_name'],
                    'postal_code' => (string) ($city['postal_code'] ?? ''),
                ])
                ->sortBy([['province', 'asc'], ['city_name', 'asc']])
                ->values();
        });
    }

    /**
     * Tarif simulasi deterministik. Angka tidak diambil dari RajaOngkir asli,
     * tetapi mengikuti struktur respons API Starter.
     *
     * @return list<array{courier:string,courier_name:string,service:string,description:string,cost:int,etd:string}>
     */
    private function dummyOptions(int $originCityId, int $destinationCityId, int $weight): array
    {
        $origin = $this->findCity($originCityId);
        $destination = $this->findCity($destinationCityId);

        if (! $origin || ! $destination) {
            throw new ShippingException('Kota asal atau tujuan pengiriman tidak ditemukan.');
        }

        $zone = $this->dummyZone($origin, $destination);
        $billableKg = max(1, (int) ceil($weight / 1000));

        $couriers = [
            'jne' => [
                ['REG', 'Layanan Reguler', [9000, 17000, 25000, 33000, 44000], '2-3 hari'],
                ['OKE', 'Ongkos Kirim Ekonomis', [7000, 14000, 21000, 28000, 38000], '3-5 hari'],
                ['YES', 'Layanan Yakin Esok Sampai', [14000, 25000, 34000, 44000, 58000], '1-2 hari'],
            ],
            'pos' => [
                ['Reguler', 'Pos Reguler', [8000, 16000, 24000, 32000, 43000], '2-4 hari'],
                ['Kilat', 'Pos Kilat Khusus', [12000, 22000, 31000, 40000, 52000], '1-2 hari'],
            ],
            'tiki' => [
                ['REG', 'TIKI Reguler', [9000, 18000, 26000, 34000, 45000], '2-4 hari'],
                ['ECO', 'TIKI Ekonomis', [6500, 13500, 20000, 27000, 37000], '4-6 hari'],
                ['ONS', 'TIKI Over Night Service', [15000, 27000, 37000, 47000, 63000], '1 hari'],
            ],
        ];

        $options = [];

        foreach ($couriers as $code => $services) {
            foreach ($services as [$service, $description, $rates, $etd]) {
                $cost = $rates[$zone - 1] * $billableKg;

                if ($zone === 1 && $originCityId === $destinationCityId) {
                    $cost = (int) max(6000, round($cost * 0.8));
                    $etd = '1 hari';
                }

                $options[] = [
                    'courier' => $code,
                    'courier_name' => config("rajaongkir.couriers.$code"),
                    'service' => $service,
                    'description' => $description,
                    'cost' => $cost,
                    'etd' => $etd,
                ];
            }
        }

        return $options;
    }

    private function dummyZone(array $origin, array $destination): int
    {
        if ($origin['province'] === $destination['province']) {
            return 1;
        }

        return match ($destination['province']) {
            'Nanggroe Aceh Darussalam (NAD)', 'Sumatera Utara', 'Sumatera Barat', 'Riau',
            'Kepulauan Riau', 'Jambi', 'Sumatera Selatan', 'Bangka Belitung',
            'Bengkulu', 'Lampung' => 2,
            'DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur',
            'Banten', 'Bali' => 3,
            'Kalimantan Barat', 'Kalimantan Selatan', 'Kalimantan Tengah',
            'Kalimantan Timur', 'Kalimantan Utara', 'Sulawesi Utara', 'Gorontalo',
            'Sulawesi Tengah', 'Sulawesi Selatan', 'Sulawesi Barat',
            'Sulawesi Tenggara', 'Nusa Tenggara Barat (NTB)',
            'Nusa Tenggara Timur (NTT)' => 4,
            default => 5,
        };
    }

    /**
     * @return list<array{courier:string,courier_name:string,service:string,description:string,cost:int,etd:string}>
     */
    private function apiCost(int $originCityId, int $destinationCityId, int $weight, string $courier): array
    {
        if (! config('rajaongkir.key')) {
            throw new ShippingException('RAJAONGKIR_API_KEY belum diisi. Gunakan RAJAONGKIR_MODE=dummy atau lengkapi API key.');
        }

        try {
            $response = Http::timeout(config('rajaongkir.timeout'))
                ->asForm()
                ->acceptJson()
                ->withHeaders(['key' => (string) config('rajaongkir.key')])
                ->post(config('rajaongkir.base_url').'/cost', [
                    'origin' => $originCityId,
                    'destination' => $destinationCityId,
                    'weight' => $weight,
                    'courier' => $courier,
                ]);

            if (! $response->successful()) {
                throw new ShippingException('RajaOngkir menolak permintaan ongkir (HTTP '.$response->status().').');
            }

            $payload = $response->json();

            if (($payload['rajaongkir']['status']['code'] ?? null) !== 200) {
                throw new ShippingException($payload['rajaongkir']['status']['description'] ?? 'Ongkir tidak dapat dihitung.');
            }

            $results = $payload['rajaongkir']['results'][0]['costs'] ?? [];
            $courierName = config("rajaongkir.couriers.$courier") ?? strtoupper($courier);

            return collect($results)->map(function (array $item) use ($courier, $courierName) {
                $cost = (int) ($item['cost'][0]['value'] ?? 0);

                return [
                    'courier' => $courier,
                    'courier_name' => $courierName,
                    'service' => $item['service'],
                    'description' => $item['description'] ?? '',
                    'cost' => $cost,
                    'etd' => (string) ($item['cost'][0]['etd'] ?? '-'),
                ];
            })->filter(fn (array $option) => $option['cost'] > 0)->values()->all();
        } catch (ShippingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Gagal menghitung ongkir RajaOngkir.', [
                'origin' => $originCityId,
                'destination' => $destinationCityId,
                'courier' => $courier,
                'exception' => $exception,
            ]);

            throw new ShippingException('Layanan RajaOngkir sedang tidak dapat dihubungi. Silakan coba beberapa saat lagi.');
        }
    }
}
