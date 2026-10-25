<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(): View
    {
        $items = auth()->user()->cartItems()
            ->with(['product.store', 'product.category'])
            ->get();

        $groups = $items->groupBy(fn (CartItem $item) => $item->product->store_id);
        $subtotal = $items->sum(fn (CartItem $item) => $item->product->price * $item->quantity);
        $totalWeight = $items->sum(fn (CartItem $item) => $item->product->weight * $item->quantity);

        return view('cart.index', compact('items', 'groups', 'subtotal', 'totalWeight'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::active()->with('store')->find($data['product_id']);

        if (! $product) {
            return back()->with('error', 'Produk tidak tersedia.');
        }

        if ($product->store->user_id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri.');
        }

        if ($product->stock < 1) {
            return back()->with('error', 'Stok produk ini sedang habis.');
        }

        $item = $request->user()->cartItems()->where('product_id', $product->id)->first();
        $newQuantity = ($item?->quantity ?? 0) + $data['quantity'];

        if ($newQuantity > $product->stock) {
            return back()->with('error', 'Jumlah melebihi stok tersedia ('.$product->stock.' item).');
        }

        $request->user()->cartItems()->updateOrCreate(
            ['product_id' => $product->id],
            ['quantity' => $newQuantity]
        );

        return back()->with('success', $product->name.' ditambahkan ke keranjang.');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorizeOwner($cartItem);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        if ($data['quantity'] > $cartItem->product->stock) {
            return back()->with('error', 'Jumlah melebihi stok tersedia ('.$cartItem->product->stock.' item).');
        }

        $cartItem->update(['quantity' => $data['quantity']]);

        return back()->with('success', 'Jumlah item diperbarui.');
    }

    public function destroy(CartItem $cartItem): RedirectResponse
    {
        $this->authorizeOwner($cartItem);
        $cartItem->delete();

        return back()->with('success', 'Produk dihapus dari keranjang.');
    }

    public function clear(): RedirectResponse
    {
        auth()->user()->cartItems()->delete();

        return redirect()->route('cart.index')->with('success', 'Keranjang berhasil dikosongkan.');
    }

    private function authorizeOwner(CartItem $item): void
    {
        abort_unless($item->user_id === auth()->id(), 403);
    }
}
