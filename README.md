# Form & Field — E-Commerce Store

A Laravel storefront for browsing everyday home goods, managing a guest shopping cart, and completing a Razorpay payment. The checkout API creates Razorpay orders server-side and places an order only after the payment signature is verified. Inventory is decremented within a database transaction after successful verification.

## Requirements

- PHP 8.3 or later with the extensions required by Laravel and PDO MySQL
- Composer
- MySQL
- Node.js and npm
- Razorpay account and API keys for payment testing

## Local setup

1. Install PHP dependencies and create your environment file:

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

   In PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

2. Create the MySQL database (or choose another name):

   ```sql
   CREATE DATABASE e_commerce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Set the database and Razorpay values in `.env`:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=e_commerce
   DB_USERNAME=root
   DB_PASSWORD=

   RAZORPAY_KEY_ID=your_razorpay_key_id
   RAZORPAY_KEY_SECRET=your_razorpay_key_secret
   ```

   Get test keys from the Razorpay Dashboard and keep the secret private. Never expose the secret in frontend code or commit `.env`.

4. Create the tables and seed the example product catalog:

   ```bash
   php artisan migrate --seed
   ```

   The seeder is safe to run again; existing sample products are not duplicated.

5. Install and build the frontend:

   ```bash
   npm install
   npm run build
   ```

6. Start the Laravel server:

   ```bash
   php artisan serve
   ```

   Visit [http://127.0.0.1:8000](http://127.0.0.1:8000). For frontend hot reload during development, run `npm run dev` in a second terminal instead of building assets after every change.

## Store and checkout

The home page displays products from the database. The cart is a guest cart identified by a token stored in the browser. At checkout, the customer enters their name, email, phone, and shipping address. The browser opens Razorpay Checkout with the order created by the backend, then sends Razorpay's payment response to the verification endpoint.

Money is stored and sent to Razorpay in integer paise (for example, ₹49.99 is `4999`). Product prices and stock are read from the database; the client cannot set the checkout amount.

## API

All endpoints are under `/api/v1`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/products` | List products and current stock |
| `POST` | `/carts` | Create a guest cart; returns `cart_token` |
| `GET` | `/carts/{token}` | View cart items and total |
| `POST` | `/carts/{token}/items` | Add quantity to a cart item |
| `DELETE` | `/carts/{token}/items/{product}` | Remove a product from the cart |
| `POST` | `/carts/{token}/checkout` | Validate customer details and create a Razorpay order |
| `POST` | `/orders/{order}/verify` | Verify Razorpay signature and place the order |
| `POST` | `/orders/track` | Look up fulfillment and carrier tracking with order reference and checkout email |

Add a product to a cart:

```json
{
  "product_id": 1,
  "quantity": 2
}
```

Create a checkout order:

```json
{
  "name": "Asha Customer",
  "email": "asha@example.com",
  "phone": "+919876543210",
  "address": "12 Main Street, Mumbai"
}
```

The checkout response includes `order_id`, `order_number`, `razorpay_order_id`, `amount` (paise), `currency`, and the public Razorpay `key` for the client. Keep the order number for tracking. After payment, submit the returned fields to `/orders/{order}/verify`:

```json
{
  "razorpay_payment_id": "pay_from_checkout",
  "razorpay_order_id": "order_from_checkout",
  "razorpay_signature": "signature_from_checkout"
}
```

An invalid signature or mismatched order is rejected. Re-verifying the same successful payment is safe and does not decrement stock a second time.

### Shipment tracking

Customers can track an order from the storefront using the order reference and email address entered at checkout. The API returns payment/fulfillment status, order items, and carrier details without exposing the shipping address. The lookup is rate limited.

Shipment updates are performed by an operator from the server console; no public shipment-update endpoint is exposed:

```bash
php artisan orders:update-tracking {order_number} processing
php artisan orders:update-tracking {order_number} shipped --carrier="Parcel Post" --tracking-number="TRACK-12345" --tracking-url="https://carrier.example/track/TRACK-12345"
php artisan orders:update-tracking {order_number} out_for_delivery --carrier="Parcel Post" --tracking-number="TRACK-12345" --tracking-url="https://carrier.example/track/TRACK-12345"
php artisan orders:update-tracking {order_number} delivered --carrier="Parcel Post" --tracking-number="TRACK-12345" --tracking-url="https://carrier.example/track/TRACK-12345"
```

Replace the sample carrier and URL with the actual shipping provider's tracking details. Shipment transitions must proceed in order (`processing` → `shipped` → `out_for_delivery` → `delivered`); a processing order may instead be cancelled.

Look up an order programmatically:

```json
{
  "order_number": "order-reference-from-checkout",
  "email": "asha@example.com"
}
```

## Tests

Run the test suite with:

```bash
php artisan test --compact
```

Tests use an isolated in-memory SQLite database, so they do not require the MySQL database. Razorpay API calls are mocked in feature tests; use Razorpay test credentials to exercise a real payment flow locally.

## Key files

- `resources/views/store.blade.php` — storefront and cart/checkout markup
- `resources/css/app.css` — responsive storefront styles
- `resources/js/app.js` — product loading, cart actions, and Razorpay Checkout
- `routes/api.php` — versioned commerce API routes
- `app/Http/Controllers/Api/V1/` — product, cart, checkout, and order-tracking endpoints
- `app/Services/RazorpayGateway.php` — Razorpay order creation and signature verification
- `database/migrations/` — products, carts, and order schema
- `database/seeders/ProductSeeder.php` — example products for local development
- `app/Console/Commands/UpdateOrderTracking.php` — staff shipment-status update command
