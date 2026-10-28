<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Services\RazorpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Razorpay\Api\Errors\SignatureVerificationError;
use Tests\TestCase;

class CheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_includes_order_tracking_lookup(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Track order')
            ->assertSee('tracking-order-number')
            ->assertSee('tracking-email');
    }

    public function test_customer_can_add_a_product_to_a_cart(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['price' => '12.50', 'stock' => 4]);

        $this->postJson("/api/v1/carts/{$cart->token}/items", [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('items.0.name', $product->name)
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.line_total', '25.00')
            ->assertJsonPath('total', '25.00');

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_customer_cannot_add_more_than_available_stock(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 1]);

        $this->postJson("/api/v1/carts/{$cart->token}/items", [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_creates_a_razorpay_order_in_minor_units(): void
    {
        config(['services.razorpay.key' => 'rzp_test_key']);

        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['price' => '49.99', 'stock' => 5]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $gateway = Mockery::mock(RazorpayGateway::class);
        $gateway->shouldReceive('createOrder')
            ->once()
            ->with(Mockery::on(fn (Order $order): bool => $order->amount_paise === 9998))
            ->andReturn('order_razorpay_123');
        $this->app->instance(RazorpayGateway::class, $gateway);

        $checkout = $this->postJson("/api/v1/carts/{$cart->token}/checkout", [
            'name' => 'Asha Customer',
            'email' => 'asha@example.com',
            'phone' => '+919876543210',
            'address' => '12 Main Street, Mumbai',
        ])
            ->assertCreated()
            ->assertJsonPath('razorpay_order_id', 'order_razorpay_123')
            ->assertJsonPath('amount', 9998)
            ->assertJsonPath('currency', 'INR')
            ->assertJsonPath('key', 'rzp_test_key');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $checkout->json('order_number'));
        $this->assertDatabaseHas('orders', [
            'razorpay_order_id' => 'order_razorpay_123',
            'amount_paise' => 9998,
            'status' => 'pending',
        ]);
    }

    public function test_valid_signature_places_order_and_decrements_stock_once(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['price' => '10.00', 'stock' => 3]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $gateway = Mockery::mock(RazorpayGateway::class);
        $gateway->shouldReceive('createOrder')->once()->andReturn('order_razorpay_456');
        $gateway->shouldReceive('verifyPaymentSignature')
            ->once()
            ->with('order_razorpay_456', 'pay_razorpay_456', 'signature')
            ->andReturnNull();
        $this->app->instance(RazorpayGateway::class, $gateway);

        $checkout = $this->postJson("/api/v1/carts/{$cart->token}/checkout", [
            'name' => 'Asha Customer',
            'email' => 'asha@example.com',
            'phone' => '+919876543210',
            'address' => '12 Main Street, Mumbai',
        ])->assertCreated();

        $orderId = $checkout->json('order_id');
        $payment = [
            'razorpay_order_id' => 'order_razorpay_456',
            'razorpay_payment_id' => 'pay_razorpay_456',
            'razorpay_signature' => 'signature',
        ];

        $this->postJson("/api/v1/orders/{$orderId}/verify", $payment)
            ->assertOk()
            ->assertJsonPath('message', 'Payment verified.');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'paid',
            'fulfillment_status' => 'processing',
            'razorpay_payment_id' => 'pay_razorpay_456',
        ]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);

        $this->postJson("/api/v1/orders/{$orderId}/verify", $payment)->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
    }

    public function test_invalid_signature_does_not_place_order_or_decrement_stock(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 3]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $gateway = Mockery::mock(RazorpayGateway::class);
        $gateway->shouldReceive('createOrder')->once()->andReturn('order_razorpay_789');
        $gateway->shouldReceive('verifyPaymentSignature')
            ->once()
            ->andThrow(new SignatureVerificationError('Invalid signature'));
        $this->app->instance(RazorpayGateway::class, $gateway);

        $checkout = $this->postJson("/api/v1/carts/{$cart->token}/checkout", [
            'name' => 'Asha Customer',
            'email' => 'asha@example.com',
            'phone' => '+919876543210',
            'address' => '12 Main Street, Mumbai',
        ])->assertCreated();
        $orderId = $checkout->json('order_id');

        $this->postJson("/api/v1/orders/{$orderId}/verify", [
            'razorpay_order_id' => 'order_razorpay_789',
            'razorpay_payment_id' => 'pay_invalid',
            'razorpay_signature' => 'invalid',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid payment signature.');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
    }

    public function test_payment_for_a_different_razorpay_order_is_rejected(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 3]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $gateway = Mockery::mock(RazorpayGateway::class);
        $gateway->shouldReceive('createOrder')->once()->andReturn('order_razorpay_abc');
        $gateway->shouldNotReceive('verifyPaymentSignature');
        $this->app->instance(RazorpayGateway::class, $gateway);

        $checkout = $this->postJson("/api/v1/carts/{$cart->token}/checkout", [
            'name' => 'Asha Customer',
            'email' => 'asha@example.com',
            'phone' => '+919876543210',
            'address' => '12 Main Street, Mumbai',
        ])->assertCreated();

        $this->postJson('/api/v1/orders/'.$checkout->json('order_id').'/verify', [
            'razorpay_order_id' => 'order_tampered',
            'razorpay_payment_id' => 'pay_razorpay_abc',
            'razorpay_signature' => 'signature',
        ])->assertStatus(409);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
    }

    public function test_customer_can_track_shipment_using_order_number_and_checkout_email(): void
    {
        $order = Order::factory()->create([
            'customer_email' => 'asha@example.com',
            'status' => 'paid',
            'fulfillment_status' => 'shipped',
            'shipping_carrier' => 'Parcel Post',
            'tracking_number' => 'TRACK-12345',
            'tracking_url' => 'https://carrier.example/track/TRACK-12345',
        ]);
        $order->items()->create([
            'product_name' => 'The Sunday Throw',
            'quantity' => 2,
            'unit_price_paise' => 189000,
            'total_price_paise' => 378000,
        ]);

        $this->postJson('/api/v1/orders/track', [
            'order_number' => $order->order_number,
            'email' => 'ASHA@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('order_number', $order->order_number)
            ->assertJsonPath('status_label', 'Shipped')
            ->assertJsonPath('carrier', 'Parcel Post')
            ->assertJsonPath('tracking_number', 'TRACK-12345')
            ->assertJsonPath('timeline.0.complete', true)
            ->assertJsonPath('timeline.1.current', true)
            ->assertJsonPath('items.0.name', 'The Sunday Throw')
            ->assertJsonMissingPath('shipping_address');
    }

    public function test_order_tracking_requires_the_checkout_email(): void
    {
        $order = Order::factory()->create(['customer_email' => 'asha@example.com']);

        $this->postJson('/api/v1/orders/track', [
            'order_number' => $order->order_number,
            'email' => 'someone-else@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_number')
            ->assertJsonPath('errors.order_number.0', 'We could not find an order matching those details.');
    }

    public function test_staff_can_update_carrier_tracking_from_the_console(): void
    {
        $order = Order::factory()->create([
            'status' => 'paid',
            'fulfillment_status' => 'processing',
        ]);

        $exitCode = Artisan::call('orders:update-tracking', [
            'order_number' => $order->order_number,
            'status' => 'shipped',
            '--carrier' => 'Parcel Post',
            '--tracking-number' => 'TRACK-67890',
            '--tracking-url' => 'https://carrier.example/track/TRACK-67890',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'fulfillment_status' => 'shipped',
            'shipping_carrier' => 'Parcel Post',
            'tracking_number' => 'TRACK-67890',
        ]);
        $this->assertNotNull($order->fresh()->shipped_at);
    }

    public function test_staff_cannot_skip_shipment_status_steps(): void
    {
        $order = Order::factory()->create([
            'status' => 'paid',
            'fulfillment_status' => 'processing',
        ]);

        $exitCode = Artisan::call('orders:update-tracking', [
            'order_number' => $order->order_number,
            'status' => 'delivered',
            '--carrier' => 'Parcel Post',
            '--tracking-number' => 'TRACK-67890',
            '--tracking-url' => 'https://carrier.example/track/TRACK-67890',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'fulfillment_status' => 'processing',
        ]);
    }

    public function test_shipment_tracking_requires_an_https_carrier_url(): void
    {
        $order = Order::factory()->create([
            'status' => 'paid',
            'fulfillment_status' => 'processing',
        ]);

        $exitCode = Artisan::call('orders:update-tracking', [
            'order_number' => $order->order_number,
            'status' => 'shipped',
            '--carrier' => 'Parcel Post',
            '--tracking-number' => 'TRACK-67890',
            '--tracking-url' => 'http://carrier.example/track/TRACK-67890',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'fulfillment_status' => 'processing',
            'tracking_number' => null,
        ]);
    }

    public function test_shipment_status_cannot_be_updated_before_payment_is_confirmed(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'fulfillment_status' => 'awaiting_payment',
        ]);

        $exitCode = Artisan::call('orders:update-tracking', [
            'order_number' => $order->order_number,
            'status' => 'processing',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'fulfillment_status' => 'awaiting_payment',
        ]);
    }
}
