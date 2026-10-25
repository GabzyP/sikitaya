(() => {
    'use strict';

    const rupiah = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(value) || 0);

    /* Header scroll state */
    const nav = document.querySelector('.main-nav');
    if (nav) {
        const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* Quantity steppers */
    document.addEventListener('click', (event) => {
        const plus = event.target.closest('[data-qty-plus]');
        const minus = event.target.closest('[data-qty-minus]');

        if (!plus && !minus) {
            return;
        }

        const wrapper = (plus || minus).closest('.qty-control');
        const input = wrapper?.querySelector('input[type="number"]');

        if (!input) {
            return;
        }

        const min = Number(input.min || 1);
        const max = Number(input.max || 999);
        const current = Number(input.value) || min;
        input.value = Math.min(max, Math.max(min, current + (plus ? 1 : -1)));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    /* Copy to clipboard */
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy]');

        if (!button) {
            return;
        }

        const text = button.getAttribute('data-copy');
        const original = button.innerHTML;

        try {
            await navigator.clipboard.writeText(text);
            button.innerHTML = '<i class="bi bi-check2"></i> Tersalin';
            window.setTimeout(() => { button.innerHTML = original; }, 1800);
        } catch (error) {
            button.innerHTML = '<i class="bi bi-x"></i> Gagal';
        }
    });

    /* Registration role switch */
    const roleRadios = document.querySelectorAll('input[name="role"]');
    const storeFields = document.querySelector('[data-store-fields]');

    if (roleRadios.length && storeFields) {
        const syncRoleFields = () => {
            const selected = document.querySelector('input[name="role"]:checked');
            const isSeller = selected?.value === 'seller';
            storeFields.hidden = !isSeller;
            storeFields.querySelectorAll('input, textarea, select').forEach((field) => {
                if (field.dataset.requiredWhenSeller === 'true') {
                    field.required = isSeller;
                }
            });
        };

        roleRadios.forEach((radio) => radio.addEventListener('change', syncRoleFields));
        syncRoleFields();
    }

    /* Image upload preview */
    document.querySelectorAll('[data-image-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const preview = document.querySelector(input.getAttribute('data-image-input'));
            const file = input.files?.[0];

            if (preview && file) {
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('d-none');
            }
        });
    });

    /* Checkout shipping calculation */
    const checkoutForm = document.querySelector('#checkout-form');

    if (checkoutForm) {
        const citySelect = checkoutForm.querySelector('#city-select');
        const storeBlocks = Array.from(checkoutForm.querySelectorAll('[data-checkout-store]'));
        const submit = checkoutForm.querySelector('[data-checkout-submit]');
        const errorBox = checkoutForm.querySelector('[data-shipping-error]');
        const shippingSummary = document.querySelector('[data-summary-shipping]');
        const totalSummary = document.querySelector('[data-summary-total]');
        let controller = null;
        let requestSequence = 0;

        const setSubmitState = (enabled) => {
            if (submit) {
                submit.disabled = !enabled;
            }
        };

        const setError = (message) => {
            if (!errorBox) {
                return;
            }

            if (message) {
                errorBox.textContent = message;
                errorBox.classList.remove('d-none');
            } else {
                errorBox.textContent = '';
                errorBox.classList.add('d-none');
            }
        };

        const updateSummary = () => {
            let shipping = 0;
            let ready = true;

            storeBlocks.forEach((block) => {
                const select = block.querySelector('.shipping-select');
                const selected = select?.selectedOptions?.[0];

                if (!select || !selected || !selected.value) {
                    ready = false;
                    return;
                }

                shipping += Number(selected.dataset.cost || 0);
            });

            if (shippingSummary) {
                shippingSummary.textContent = rupiah(shipping);
            }

            if (totalSummary) {
                const subtotal = Number(checkoutForm.dataset.subtotal || 0);
                totalSummary.textContent = rupiah(subtotal + shipping);
            }

            if (submit) {
                submit.dataset.ready = ready && !storeBlocks.some((block) => block.dataset.shippingError === '1') ? '1' : '0';
            }
        };

        const populateSelect = (block, options, selectedValue) => {
            const select = block.querySelector('.shipping-select');
            const status = block.querySelector('.shipping-status');

            if (!select) {
                return;
            }

            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = options.length ? 'Pilih layanan pengiriman' : 'Tidak ada layanan tersedia';
            select.appendChild(placeholder);

            const groups = {};
            options.forEach((option) => {
                if (!groups[option.courier]) {
                    groups[option.courier] = document.createElement('optgroup');
                    groups[option.courier].label = option.courier_name || option.courier.toUpperCase();
                    select.appendChild(groups[option.courier]);
                }

                const item = document.createElement('option');
                item.value = option.courier + '|' + option.service;
                item.dataset.cost = option.cost;
                item.textContent = `${option.courier_name} ${option.service} · ${rupiah(option.cost)} · ${option.etd}`;
                groups[option.courier].appendChild(item);
            });

            if (selectedValue) {
                const match = Array.from(select.options).find((option) => option.value === selectedValue);
                if (match) {
                    match.selected = true;
                }
            }

            if (!select.value && select.options.length > 1) {
                select.selectedIndex = 1;
            }

            select.disabled = options.length === 0;
            block.dataset.shippingError = options.length === 0 ? '1' : '0';

            if (status) {
                status.classList.remove('error');
                status.textContent = options.length
                    ? `${options.length} layanan tersedia dari ${block.dataset.storeName}`
                    : 'Kurir tidak melayani rute ini.';
                if (!options.length) {
                    status.classList.add('error');
                }
            }

            updateSummary();
        };

        const loadShipping = async () => {
            const destination = citySelect?.value;

            if (!destination) {
                storeBlocks.forEach((block) => {
                    const select = block.querySelector('.shipping-select');
                    if (select) {
                        select.innerHTML = '<option value="">Pilih kota tujuan terlebih dahulu</option>';
                        select.disabled = true;
                    }
                });
                setSubmitState(false);
                updateSummary();
                return;
            }

            controller?.abort();
            controller = new AbortController();
            const sequence = ++requestSequence;

            setError('');
            setSubmitState(false);
            let hadError = false;

            await Promise.all(storeBlocks.map(async (block) => {
                const select = block.querySelector('.shipping-select');
                const status = block.querySelector('.shipping-status');
                const previousValue = select?.dataset.selected || select?.value || '';

                block.dataset.shippingError = '0';
                if (status) {
                    status.classList.remove('error');
                    status.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Menghitung ongkir...';
                }

                try {
                    const params = new URLSearchParams({
                        store_id: block.dataset.storeId,
                        destination,
                        weight: block.dataset.weight,
                    });

                    const response = await fetch(`${checkoutForm.dataset.shippingUrl}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                        signal: controller.signal,
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(payload.message || 'Ongkir tidak dapat dihitung.');
                    }

                    if (sequence !== requestSequence) {
                        return;
                    }

                    populateSelect(block, payload.options || [], previousValue);
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    hadError = true;
                    block.dataset.shippingError = '1';
                    if (select) {
                        select.innerHTML = '<option value="">Ongkir gagal dimuat</option>';
                        select.disabled = true;
                    }
                    if (status) {
                        status.textContent = error.message || 'Ongkir tidak dapat dihitung. Silakan coba lagi.';
                        status.classList.add('error');
                    }
                    setError(error.message || 'Ongkir tidak dapat dihitung. Silakan coba lagi.');
                }
            }));

            if (sequence !== requestSequence) {
                return;
            }

            updateSummary();
            const allReady = !hadError && storeBlocks.every((block) => {
                const select = block.querySelector('.shipping-select');
                return select && select.value && !select.disabled;
            });
            setSubmitState(allReady);

            if (!allReady) {
                setError('Lengkapi pilihan ongkir untuk seluruh toko sebelum melanjutkan.');
            }
        };

        storeBlocks.forEach((block) => {
            block.querySelector('.shipping-select')?.addEventListener('change', () => {
                block.querySelector('.shipping-select').dataset.selected = block.querySelector('.shipping-select').value;
                updateSummary();
                const allReady = storeBlocks.every((storeBlock) => {
                    const select = storeBlock.querySelector('.shipping-select');
                    return select && select.value && !select.disabled;
                });
                setSubmitState(allReady);
            });
        });

        citySelect?.addEventListener('change', loadShipping);

        checkoutForm.addEventListener('submit', (event) => {
            const allReady = storeBlocks.every((block) => {
                const select = block.querySelector('.shipping-select');
                return select && select.value && !select.disabled;
            });

            if (!allReady) {
                event.preventDefault();
                setError('Pilih layanan pengiriman untuk semua toko terlebih dahulu.');
                return;
            }

            if (submit) {
                submit.disabled = true;
                submit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span> Membuat pesanan...';
            }
        });

        if (citySelect?.value) {
            loadShipping();
        }
    }
})();
