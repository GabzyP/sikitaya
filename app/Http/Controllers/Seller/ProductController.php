<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeStore();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'low'])],
        ]);

        $store = auth()->user()->store;

        $products = Product::where('store_id', $store->id)
            ->with('category')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where('name', 'like', '%'.$q.'%'))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'low', fn ($query) => $query->where('stock', '<=', 5))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('seller.products.index', compact('products', 'filters', 'store'));
    }

    public function create(): View
    {
        $this->authorizeStore();

        $categories = Category::orderBy('name')->get();
        $product = new Product(['is_active' => true, 'stock' => 10, 'weight' => 500]);

        return view('seller.products.form', compact('product', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStore();

        $data = $this->validatedData($request);
        $data['store_id'] = $request->user()->store->id;
        $data['slug'] = Product::generateUniqueSlug($data['name']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);

        return redirect()->route('seller.products.index')->with('success', 'Produk baru berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        $this->authorizeProduct($product);

        $categories = Category::orderBy('name')->get();

        return view('seller.products.form', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($product);

        $data = $this->validatedData($request);

        if ($product->name !== $data['name']) {
            $data['slug'] = Product::generateUniqueSlug($data['name'], $product->id);
        }

        if ($request->hasFile('image')) {
            if ($product->image && ! Str::startsWith($product->image, 'images/')) {
                Storage::disk('public')->delete($product->image);
            }

            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return redirect()->route('seller.products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorizeProduct($product);

        if ($product->image && ! Str::startsWith($product->image, 'images/')) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('seller.products.index')->with('success', 'Produk berhasil dihapus.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        $this->authorizeProduct($product);

        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', 'Status produk diperbarui menjadi '.($product->is_active ? 'aktif' : 'nonaktif').'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:100', 'max:1000000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'weight' => ['required', 'integer', 'min:1', 'max:50000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'image.image' => 'Foto produk harus berupa gambar.',
            'image.mimes' => 'Foto produk harus berupa JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran foto produk maksimal 2 MB.',
            'price.min' => 'Harga minimal Rp 100.',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function authorizeStore(): void
    {
        abort_unless(auth()->user()->store, 403, 'Profil toko tidak ditemukan.');
    }

    private function authorizeProduct(Product $product): void
    {
        $this->authorizeStore();
        abort_unless($product->store_id === auth()->user()->store->id, 403);
    }
}
