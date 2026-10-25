<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerHasStore
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->store) {
            return redirect()->route('seller.store.edit')->with('warning', 'Lengkapi profil toko Anda terlebih dahulu.');
        }

        return $next($request);
    }
}
