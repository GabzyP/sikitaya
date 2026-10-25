@php
    $badge = $product->stock <= 0 ? 'Habis' : ($product->is_low_stock ? 'Stok terbatas' : null);
@endphp
<article class="product-card h-100">
    <a href="{{ route('products.show', $product) }}" class="product-image-wrap">
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="product-image" loading="lazy">
        @if ($product->category)
            <span class="product-category">{{ $product->category->name }}</span>
        @endif
        @if ($badge)
            <span class="product-stock-badge {{ $product->stock <= 0 ? 'out' : '' }}">{{ $badge }}</span>
        @endif
    </a>
    <div class="product-body">
        <a href="{{ route('stores.show', $product->store) }}" class="product-store">
            <i class="bi bi-shop"></i> {{ $product->store->name }}
        </a>
        <h3><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
        <div class="product-meta">
            <span class="product-price">{{ rupiah($product->price) }}</span>
            <span class="product-weight">{{ berat($product->weight) }}</span>
        </div>
    </div>
    <div class="product-footer">
        @if ($product->stock > 0)
            <form action="{{ route('cart.store') }}" method="POST" class="w-100">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="btn btn-cart w-100">
                    <i class="bi bi-bag-plus"></i> Tambah ke keranjang
                </button>
            </form>
        @else
            <button type="button" class="btn btn-cart w-100" disabled><i class="bi bi-x-circle"></i> Stok habis</button>
        @endif
    </div>
</article>
