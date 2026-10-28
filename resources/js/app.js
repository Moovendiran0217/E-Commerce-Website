const apiBase = '/api/v1';
const productPhotos = [
    'photo-1494438639946-1ebd1d20bf85',
    'photo-1503602642458-232111445657',
    'photo-1490312278390-ab64016e0aa9',
    'photo-1490312278390-ab64016e0aa9',
    'photo-1513519245088-0e12902e5a38',
    'photo-1616486338812-3dadae4b4ace',
    'photo-1600210492486-724fe5c67fb0',
    'photo-1494438639946-1ebd1d20bf85',
];

const elements = {
    productGrid: document.querySelector('#product-grid'),
    productCount: document.querySelector('#product-count'),
    cartCount: document.querySelector('#cart-count'),
    drawer: document.querySelector('#cart-drawer'),
    backdrop: document.querySelector('#drawer-backdrop'),
    cartItems: document.querySelector('#cart-items'),
    emptyCart: document.querySelector('#empty-cart'),
    drawerItemCount: document.querySelector('#drawer-item-count'),
    subtotal: document.querySelector('#cart-subtotal'),
    shipping: document.querySelector('#shipping-cost'),
    total: document.querySelector('#cart-total'),
    checkoutForm: document.querySelector('#checkout-form'),
    checkoutButton: document.querySelector('#checkout-button'),
    checkoutMessage: document.querySelector('#checkout-message'),
    successPanel: document.querySelector('#success-panel'),
    successOrderNumber: document.querySelector('#success-order-number'),
    trackingForm: document.querySelector('#tracking-form'),
    trackingButton: document.querySelector('#tracking-button'),
    trackingMessage: document.querySelector('#tracking-message'),
    trackingResult: document.querySelector('#tracking-result'),
    toast: document.querySelector('#store-toast'),
};

let products = [];
let cartToken = localStorage.getItem('form-field-cart-token');
let cart = { items: [], total: '0.00' };
let lastFocusedElement = null;
let toastTimeout;

function showToast(message) {
    elements.toast.textContent = message;
    elements.toast.classList.add('is-visible');
    window.clearTimeout(toastTimeout);
    toastTimeout = window.setTimeout(() => elements.toast.classList.remove('is-visible'), 3500);
}

function formatCurrency(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);
}

async function request(path, options = {}) {
    const response = await fetch(`${apiBase}${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
        },
    });

    let payload;

    try {
        payload = await response.json();
    } catch {
        const error = new Error('The shop could not complete that request. Please try again.');
        error.status = response.status;
        throw error;
    }

    if (!response.ok) {
        const validationMessage = Object.values(payload.errors || {}).flat()[0];
        const error = new Error(validationMessage || payload.message || 'Something went wrong. Please try again.');
        error.status = response.status;
        throw error;
    }

    return payload;
}

async function ensureCart() {
    if (cartToken) {
        try {
            return await request(`/carts/${encodeURIComponent(cartToken)}`);
        } catch (error) {
            if (error.status !== 404) {
                throw error;
            }

            cartToken = null;
            localStorage.removeItem('form-field-cart-token');
        }
    }

    const newCart = await request('/carts', { method: 'POST' });
    cartToken = newCart.cart_token;
    localStorage.setItem('form-field-cart-token', cartToken);

    return request(`/carts/${encodeURIComponent(cartToken)}`);
}

function imageUrl(index, width = 720) {
    const photo = productPhotos[index % productPhotos.length];

    return `https://images.unsplash.com/${photo}?auto=format&fit=crop&w=${width}&q=80`;
}

function renderProducts() {
    elements.productCount.textContent = `${products.length} thoughtful ${products.length === 1 ? 'find' : 'finds'}`;

    if (!products.length) {
        elements.productGrid.innerHTML = `
            <div class="catalog-message">
                <strong>The shelves are waiting.</strong>
                <p>There aren't any products in the catalog yet. Add products to your database, then refresh to see them here.</p>
            </div>`;
        return;
    }

    elements.productGrid.innerHTML = products.map((product, index) => {
        const stockLabel = product.stock > 0 ? `${product.stock} ready to find a home` : 'Currently out of stock';
        const buttonLabel = product.stock > 0 ? 'Add to your bag' : 'All gone for now';

        return `
            <article class="product-card">
                <div class="product-image-wrap">
                    <img class="product-image" src="${imageUrl(index)}" alt="${escapeHtml(product.name)}" loading="lazy">
                    <span class="product-tag">${index % 3 === 0 ? 'Made with care' : index % 3 === 1 ? 'Everyday favourite' : 'Thoughtfully made'}</span>
                </div>
                <div class="product-meta">
                    <div>
                        <h3 class="product-name">${escapeHtml(product.name)}</h3>
                        <p class="product-stock">${stockLabel}</p>
                    </div>
                    <span class="product-price">${formatCurrency(product.price)}</span>
                </div>
                <button class="add-button" type="button" data-add-product="${Number(product.id)}" ${product.stock < 1 ? 'disabled' : ''}>
                    <span>${buttonLabel}</span><span aria-hidden="true">+</span>
                </button>
            </article>`;
    }).join('');
}

function renderCart() {
    const itemCount = cart.items.reduce((total, item) => total + Number(item.quantity), 0);
    const subtotal = Number(cart.total || 0);

    elements.cartCount.textContent = itemCount;
    elements.drawerItemCount.textContent = `(${itemCount})`;
    elements.emptyCart.hidden = itemCount > 0;
    elements.cartItems.hidden = itemCount === 0;
    elements.checkoutButton.disabled = itemCount === 0;
    elements.subtotal.textContent = formatCurrency(subtotal);
    elements.shipping.textContent = 'Free';
    elements.total.textContent = formatCurrency(subtotal);

    elements.cartItems.innerHTML = cart.items.map((item, index) => `
        <article class="cart-item">
            <img class="cart-item-thumb" src="${imageUrl(index, 240)}" alt="" loading="lazy">
            <div>
                <h3 class="cart-item-name">${escapeHtml(item.name)}</h3>
                <p class="cart-item-detail">${Number(item.quantity)} × ${formatCurrency(item.price)}</p>
                <button class="remove-item" type="button" data-remove-product="${Number(item.product_id)}">Remove</button>
            </div>
            <span class="cart-item-price">${formatCurrency(item.line_total)}</span>
        </article>`).join('');
}

async function loadStore() {
    try {
        const [catalog, currentCart] = await Promise.all([
            request('/products'),
            ensureCart(),
        ]);
        products = catalog.data;
        cart = currentCart;
        renderProducts();
        renderCart();
    } catch (error) {
        elements.productGrid.innerHTML = `
            <div class="catalog-message">
                <strong>We couldn't open the shop just yet.</strong>
                <p>${escapeHtml(error.message)} Check the database connection and try refreshing the page.</p>
            </div>`;
        elements.productCount.textContent = 'A little pause';
    }
}

function openDrawer() {
    lastFocusedElement = document.activeElement;
    elements.backdrop.hidden = false;
    elements.drawer.inert = false;
    elements.drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(() => {
        elements.backdrop.classList.add('is-visible');
        elements.drawer.classList.add('is-open');
        document.querySelector('#close-cart').focus();
    });
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    elements.backdrop.classList.remove('is-visible');
    elements.drawer.classList.remove('is-open');
    elements.drawer.setAttribute('aria-hidden', 'true');
    elements.drawer.inert = true;
    document.body.style.overflow = '';
    window.setTimeout(() => {
        elements.backdrop.hidden = true;
    }, 250);
    lastFocusedElement?.focus();
}

function showCheckoutMessage(message) {
    elements.checkoutMessage.textContent = message;
}

async function loadRazorpay() {
    if (window.Razorpay) {
        return;
    }

    await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.onload = resolve;
        script.onerror = () => reject(new Error('Secure checkout could not load. Check your connection and try again.'));
        document.head.append(script);
    });
}

async function verifyPayment(order, payment) {
    try {
        await request(`/orders/${order.order_id}/verify`, {
            method: 'POST',
            body: JSON.stringify(payment),
        });

        elements.checkoutForm.hidden = true;
        elements.successPanel.hidden = false;
        elements.successOrderNumber.textContent = `Order ${order.order_number} · Payment received with thanks.`;
        document.querySelector('#tracking-order-number').value = order.order_number;
        document.querySelector('#tracking-email').value = elements.checkoutForm.elements.email.value;
        cart = { items: [], total: '0.00' };
        cartToken = null;
        localStorage.removeItem('form-field-cart-token');
        renderCart();
    } catch (error) {
        showCheckoutMessage(error.message);
        elements.checkoutButton.disabled = false;
        elements.checkoutButton.removeAttribute('aria-busy');
    }
}

function renderTrackingResult(order) {
    const placedDate = new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
    }).format(new Date(order.placed_at));
    const timeline = order.timeline.map((step) => `
        <li class="tracking-step ${step.complete ? 'is-complete' : ''}">
            <span class="tracking-step-marker" aria-hidden="true">${step.complete ? '✓' : ''}</span>
            <span class="tracking-step-label">${escapeHtml(step.label)}</span>
        </li>`).join('');
    const items = order.items.map((item) => `
        <div class="tracking-product-line">
            <span>${escapeHtml(item.name)}</span><strong>× ${Number(item.quantity)}</strong>
        </div>`).join('');
    let carrierLink = '';

    if (order.tracking_url) {
        try {
            const trackingUrl = new URL(order.tracking_url);

            if (trackingUrl.protocol === 'https:') {
                carrierLink = `<a class="tracking-carrier-link" href="${escapeHtml(trackingUrl.href)}" target="_blank" rel="noopener noreferrer">Open carrier tracking ↗</a>`;
            }
        } catch {
            carrierLink = '';
        }
    }

    elements.trackingResult.innerHTML = `
        <div class="tracking-result-header">
            <div>
                <h3 class="tracking-result-heading">${escapeHtml(order.status_label)}</h3>
                <p class="tracking-order-ref">Order ${escapeHtml(order.order_number)} · Placed ${placedDate}</p>
            </div>
            <span class="tracking-status-pill">${escapeHtml(order.status_label)}</span>
        </div>
        <ol class="tracking-timeline">${timeline}</ol>
        ${order.tracking_number ? `
            <div class="tracking-shipment">
                <span class="tracking-shipment-copy">
                    Shipped with <strong>${escapeHtml(order.carrier)}</strong><br>
                    Tracking number <strong>${escapeHtml(order.tracking_number)}</strong>
                </span>
                ${carrierLink}
            </div>` : ''}
        <div class="tracking-products">${items}</div>`;
    elements.trackingResult.hidden = false;
}

elements.productGrid.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-add-product]');

    if (!button || button.disabled) {
        return;
    }

    button.disabled = true;

    try {
        await ensureCart();
        cart = await request(`/carts/${encodeURIComponent(cartToken)}/items`, {
            method: 'POST',
            body: JSON.stringify({ product_id: Number(button.dataset.addProduct), quantity: 1 }),
        });
        renderCart();
        openDrawer();
    } catch (error) {
        showToast(error.message);
    } finally {
        button.disabled = false;
    }
});

elements.trackingForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.trackingMessage.textContent = '';
    elements.trackingButton.disabled = true;
    elements.trackingButton.setAttribute('aria-busy', 'true');
    elements.trackingResult.hidden = true;

    try {
        const formData = new FormData(elements.trackingForm);
        const order = await request('/orders/track', {
            method: 'POST',
            body: JSON.stringify(Object.fromEntries(formData.entries())),
        });

        renderTrackingResult(order);
    } catch (error) {
        elements.trackingMessage.textContent = error.message;
    } finally {
        elements.trackingButton.disabled = false;
        elements.trackingButton.removeAttribute('aria-busy');
    }
});

elements.cartItems.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-remove-product]');

    if (!button || !cartToken) {
        return;
    }

    button.disabled = true;

    try {
        cart = await request(
            `/carts/${encodeURIComponent(cartToken)}/items/${Number(button.dataset.removeProduct)}`,
            { method: 'DELETE' },
        );
        renderCart();
    } catch (error) {
        showCheckoutMessage(error.message);
        button.disabled = false;
    }
});

elements.checkoutForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    showCheckoutMessage('');
    elements.checkoutButton.disabled = true;
    elements.checkoutButton.setAttribute('aria-busy', 'true');

    try {
        const formData = new FormData(elements.checkoutForm);
        const order = await request(`/carts/${encodeURIComponent(cartToken)}/checkout`, {
            method: 'POST',
            body: JSON.stringify(Object.fromEntries(formData.entries())),
        });

        await loadRazorpay();

        const checkout = new window.Razorpay({
            key: order.key,
            amount: order.amount,
            currency: order.currency,
            name: 'Form & Field',
            description: 'Thoughtful things for everyday living',
            order_id: order.razorpay_order_id,
            prefill: {
                name: order.customer.name,
                email: order.customer.email,
                contact: order.customer.phone,
            },
            theme: { color: '#68764f' },
            handler: (payment) => verifyPayment(order, payment),
            modal: {
                ondismiss: () => {
                    elements.checkoutButton.disabled = false;
                    elements.checkoutButton.removeAttribute('aria-busy');
                },
            },
        });

        checkout.on('payment.failed', (event) => {
            showCheckoutMessage(event.error.description || 'Payment was not completed. Please try again.');
            elements.checkoutButton.disabled = false;
            elements.checkoutButton.removeAttribute('aria-busy');
        });
        checkout.open();
    } catch (error) {
        showCheckoutMessage(error.message);
        elements.checkoutButton.disabled = false;
        elements.checkoutButton.removeAttribute('aria-busy');
    }
});

document.querySelector('#open-cart').addEventListener('click', async () => {
    if (!cartToken) {
        try {
            cart = await ensureCart();
            renderCart();
        } catch (error) {
            showCheckoutMessage(error.message);
        }
    }

    openDrawer();
});
document.querySelector('#close-cart').addEventListener('click', closeDrawer);
document.querySelector('#continue-shopping').addEventListener('click', closeDrawer);
elements.backdrop.addEventListener('click', closeDrawer);
document.querySelector('#shop-again').addEventListener('click', async () => {
    elements.successPanel.hidden = true;
    elements.checkoutForm.hidden = false;
    elements.checkoutForm.reset();
    showCheckoutMessage('');

    try {
        cart = await ensureCart();
        renderCart();
        closeDrawer();
    } catch (error) {
        showCheckoutMessage(error.message);
    }
});
document.querySelector('#success-track-link').addEventListener('click', () => {
    closeDrawer();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && elements.drawer.classList.contains('is-open')) {
        closeDrawer();
    }
});

loadStore();
