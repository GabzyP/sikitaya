<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\ShippingException;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(private readonly ShippingService $shipping) {}

    public function cost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'destination' => ['required', 'integer'],
            'weight' => ['required', 'integer', 'min:1', 'max:50000'],
        ]);

        $store = Store::active()->find($data['store_id']);

        if (! $store) {
            return response()->json(['message' => 'Toko tidak aktif atau tidak ditemukan.'], 422);
        }

        try {
            $options = $this->shipping->options($store->city_id, (int) $data['destination'], (int) $data['weight']);

            return response()->json([
                'mode' => $this->shipping->mode(),
                'origin' => $store->city_name,
                'options' => $options,
            ]);
        } catch (ShippingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
