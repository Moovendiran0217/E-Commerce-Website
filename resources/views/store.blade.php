<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f4ee">
    <title>Form & Field — Thoughtful things for everyday living</title>
    <meta name="description" content="Discover thoughtfully made pieces for your home, designed to be lived with and loved for a long time.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="store-shell antialiased">
    <div class="announcement-bar">
        <span>Good things, delivered free on orders over ₹2,500</span>
        <span class="announcement-mark" aria-hidden="true">✳</span>
        <span>Thoughtful by nature. Made to last.</span>
    </div>

    <header class="site-header">
        <a class="wordmark" href="/" aria-label="Form and Field home">
            <span class="wordmark-icon" aria-hidden="true">f.</span>
            <span>form <span class="wordmark-amp">&</span> field</span>
        </a>
        <nav class="main-nav" aria-label="Main navigation">
            <a class="nav-link nav-link-active" href="#shop">Shop all</a>
            <a class="nav-link" href="#story">Our approach</a>
            <a class="nav-link" href="#track-order">Track order</a>
        </nav>
        <button class="cart-trigger" id="open-cart" type="button" aria-label="Open shopping bag">
            <span class="cart-trigger-label">Bag</span>
            <span class="cart-count" id="cart-count">0</span>
        </button>
    </header>

    <main>
        <section class="hero-section" aria-labelledby="hero-title">
            <div class="hero-copy">
                <div class="eyebrow"><span class="eyebrow-line"></span> A little more intentional</div>
                <h1 id="hero-title">A home that<br>feels like <em>you.</em></h1>
                <p>Everyday objects, considered carefully. Made for slow mornings, shared meals and all the little moments in between.</p>
                <a class="primary-link" href="#shop">Find your something <span aria-hidden="true">↗</span></a>
                <div class="hero-note">
                    <span class="hero-note-avatars" aria-hidden="true"><i>J</i><i>A</i><i>M</i></span>
                    <span>Loved in 2,400+ homes</span>
                </div>
            </div>
            <div class="hero-art">
                <img
                    class="hero-image"
                    src="https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=1400&q=85"
                    alt="Sunlit living room with warm, natural textures"
                    fetchpriority="high"
                >
                <div class="hero-art-caption">
                    <span class="caption-dot"></span>
                    <span>Objects with a softer footprint</span>
                </div>
                <div class="hero-stamp" aria-hidden="true">
                    <span>MADE TO</span>
                    <strong>KEEP</strong>
                    <span>✳ &nbsp; NOT REPLACE</span>
                </div>
            </div>
            <div class="hero-index" aria-hidden="true"><span>01</span> / 03</div>
        </section>

        <section class="values-strip" id="story" aria-label="Our values">
            <div class="value-item"><span class="value-icon" aria-hidden="true">✳</span><span>Made with intention</span></div>
            <div class="value-item"><span class="value-icon" aria-hidden="true">↗</span><span>Small-batch makers</span></div>
            <div class="value-item"><span class="value-icon" aria-hidden="true">⌂</span><span>Better, not more</span></div>
            <div class="value-item"><span class="value-icon" aria-hidden="true">♡</span><span>Free shipping over ₹2,500</span></div>
        </section>

        <section class="collection-section" id="shop" aria-labelledby="shop-title">
            <div class="collection-heading">
                <div>
                    <div class="eyebrow"><span class="eyebrow-line"></span> The considered collection</div>
                    <h2 id="shop-title">Objects for <em>every day.</em></h2>
                </div>
                <p>Useful, beautiful, and never just for show.<br>Meet the things you'll reach for again.</p>
            </div>
            <div class="collection-toolbar">
                <span class="product-count" id="product-count">A few good things</span>
                <span class="sort-label">MADE FOR REAL LIFE <span aria-hidden="true">✳</span></span>
            </div>

            <div class="product-grid" id="product-grid" aria-live="polite">
                <div class="loading-state"><span class="loading-spinner" aria-hidden="true"></span><span>Finding the good things…</span></div>
            </div>
        </section>

        <section class="tracking-section" id="track-order" aria-labelledby="tracking-title">
            <div class="tracking-copy">
                <div class="eyebrow"><span class="eyebrow-line"></span> From our hands to yours</div>
                <h2 id="tracking-title">Your order,<br><em>on its way.</em></h2>
                <p>Enter your order reference and checkout email to see the latest delivery update.</p>
                <div class="tracking-promise"><span aria-hidden="true">✳</span> Updates straight from your carrier</div>
            </div>
            <div class="tracking-card">
                <form id="tracking-form" class="tracking-form">
                    <label class="tracking-label" for="tracking-order-number">Order reference</label>
                    <input class="tracking-input" id="tracking-order-number" name="order_number" placeholder="Your order reference" autocomplete="off" required>
                    <label class="tracking-label" for="tracking-email">Checkout email</label>
                    <input class="tracking-input" id="tracking-email" name="email" type="email" placeholder="The email used at checkout" autocomplete="email" required>
                    <p class="tracking-message" id="tracking-message" role="status" aria-live="polite"></p>
                    <button class="tracking-button" id="tracking-button" type="submit">
                        <span>Find my order</span><span aria-hidden="true">↗</span>
                    </button>
                </form>
                <div class="tracking-result" id="tracking-result" aria-live="polite" hidden></div>
            </div>
        </section>

        <section class="closing-note">
            <span class="closing-spark" aria-hidden="true">✳</span>
            <p>Less, but <em>better.</em></p>
            <span>Good design should feel at home.</span>
        </section>
    </main>

    <footer class="site-footer">
        <a class="wordmark footer-wordmark" href="/" aria-label="Form and Field home">
            <span class="wordmark-icon" aria-hidden="true">f.</span>
            <span>form <span class="wordmark-amp">&</span> field</span>
        </a>
        <span>Thoughtfully found. Made to be lived with.</span>
        <span>© {{ date('Y') }} Form & Field</span>
    </footer>

    <div class="store-toast" id="store-toast" role="status" aria-live="polite"></div>
    <div class="drawer-backdrop" id="drawer-backdrop" hidden></div>
    <aside class="cart-drawer" id="cart-drawer" aria-labelledby="drawer-title" aria-hidden="true" inert>
        <div class="drawer-header">
            <div>
                <span class="eyebrow"><span class="eyebrow-line"></span> Your considered collection</span>
                <h2 id="drawer-title">Your bag <span id="drawer-item-count">(0)</span></h2>
            </div>
            <button class="icon-button" id="close-cart" type="button" aria-label="Close shopping bag">×</button>
        </div>

        <div class="cart-content" id="cart-content">
            <div class="cart-items" id="cart-items"></div>
            <div class="empty-cart" id="empty-cart" hidden>
                <span class="empty-cart-icon" aria-hidden="true">↗</span>
                <h3>A little room for something good.</h3>
                <p>Your bag is taking a well-deserved breather.</p>
                <button class="text-button" id="continue-shopping" type="button">Explore the collection <span aria-hidden="true">→</span></button>
            </div>
        </div>

        <form class="checkout-panel" id="checkout-form">
            <div class="checkout-heading">
                <h3>Delivery details</h3>
                <span>JUST A FEW THINGS</span>
            </div>
            <div class="checkout-fields">
                <label class="field-label" for="customer-name">Your name</label>
                <input class="checkout-input" id="customer-name" name="name" autocomplete="name" placeholder="How should we address you?" required maxlength="255">
                <label class="field-label" for="customer-email">Email address</label>
                <input class="checkout-input" id="customer-email" name="email" type="email" autocomplete="email" placeholder="For your order updates" required maxlength="255">
                <label class="field-label" for="customer-phone">Phone number</label>
                <input class="checkout-input" id="customer-phone" name="phone" type="tel" autocomplete="tel" placeholder="+91 98765 43210" required maxlength="30">
                <label class="field-label" for="customer-address">Shipping address</label>
                <textarea class="checkout-input checkout-textarea" id="customer-address" name="address" autocomplete="street-address" placeholder="House, street, city, PIN code" rows="3" required maxlength="2000"></textarea>
            </div>
            <div class="cart-summary">
                <div><span>Subtotal</span><strong id="cart-subtotal">₹0</strong></div>
                <div><span>Shipping</span><strong id="shipping-cost">Free</strong></div>
                <div class="summary-total"><span>Total</span><strong id="cart-total">₹0</strong></div>
            </div>
            <p class="checkout-message" id="checkout-message" role="status" aria-live="polite"></p>
            <button class="checkout-button" id="checkout-button" type="submit" disabled>
                <span>Continue to secure payment</span>
                <span aria-hidden="true">↗</span>
            </button>
            <div class="secure-note"><span aria-hidden="true">◇</span> Secure checkout, powered by Razorpay</div>
        </form>

        <section class="success-panel" id="success-panel" aria-live="polite" hidden>
            <span class="success-icon" aria-hidden="true">✓</span>
            <span class="eyebrow">A good choice</span>
            <h3>Your order is on its way.</h3>
            <p id="success-order-number"></p>
            <a class="success-track-link" id="success-track-link" href="#track-order">Track this delivery <span aria-hidden="true">↗</span></a>
            <button class="checkout-button" id="shop-again" type="button">Back to the good things <span aria-hidden="true">↗</span></button>
        </section>
    </aside>
</body>
</html>
