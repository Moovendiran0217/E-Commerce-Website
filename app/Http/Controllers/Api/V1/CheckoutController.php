<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Services\RazorpayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Errors\SignatureVerificationError;
use Throwable;

class CheckoutController extends Controller
{
    public function createOrder(Request $request, string $token, RazorpayGateway $razorpay): JsonResponse
    {
        $customer = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:2000'],
        ]);

        $order = DB::transaction(function () use ($customer, $token): Order {
            $cart = Cart::query()->where('token', $token)->lockForUpdate()->firstOrFail();
            $cartItems = $cart->items()->lockForUpdate()->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $items = [];
            $amountPaise = 0;

            foreach ($cartItems as $cartItem) {
                $product = Product::query()->lockForUpdate()->findOrFail($cartItem->product_id);

                if ($cartItem->quantity > $product->stock) {
                    throw ValidationException::withMessages([
                        'cart' => "There is not enough stock for {$product->name}.",
                    ]);
                }

                $unitPricePaise = (int) round((float) $product->price * 100);
                $lineTotalPaise = $unitPricePaise * $cartItem->quantity;
                $amountPaise += $lineTotalPaise;
                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $cartItem->quantity,
                    'unit_price_paise' => $unitPricePaise,
                    'total_price_paise' => $lineTotalPaise,
                ];
            }

            $order = Order::query()->create([
                'order_number' => (string) Str::uuid(),
                'cart_id' => $cart->id,
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
                'shipping_address' => $customer['address'],
                'amount_paise' => $amountPaise,
                'currency' => 'INR',
                'status' => 'pending',
            ]);

            $order->items()->createMany($items);

            return $order;
        });

        try {
            $razorpayOrderId = $razorpay->createOrder($order);
        } catch (Throwable $exception) {
            report($exception);
            $order->update(['status' => 'payment_failed']);

            return response()->json(['message' => 'Unable to initialize payment.'], 502);
        }

        $order->update(['razorpay_order_id' => $razorpayOrderId]);

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'razorpay_order_id' => $razorpayOrderId,
            'amount' => $order->amount_paise,
            'currency' => $order->currency,
            'key' => config('services.razorpay.key'),
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],
        ], 201);
    }

    public function verifyPayment(Request $request, int $order, RazorpayGateway $razorpay): JsonResponse
    {
        $payment = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:255'],
            'razorpay_order_id' => ['required', 'string', 'max:255'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $localOrder = Order::query()->findOrFail($order);

        if ($localOrder->status === 'paid' && $localOrder->razorpay_payment_id === $payment['razorpay_payment_id']) {
            return response()->json(['message' => 'Payment verified.', 'order_id' => $localOrder->id]);
        }

        if ($localOrder->status !== 'pending' || $localOrder->razorpay_order_id !== $payment['razorpay_order_id']) {
            return response()->json(['message' => 'This payment does not match a pending order.'], 409);
        }

        try {
            $razorpay->verifyPaymentSignature(
                $payment['razorpay_order_id'],
                $payment['razorpay_payment_id'],
                $payment['razorpay_signature'],
            );
        } catch (SignatureVerificationError) {
            return response()->json(['message' => 'Invalid payment signature.'], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to verify payment.'], 502);
        }

        DB::transaction(function () use ($localOrder, $payment): void {
            $order = Order::query()->lockForUpdate()->findOrFail($localOrder->id);

            if ($order->status === 'paid') {
                if ($order->razorpay_payment_id !== $payment['razorpay_payment_id']) {
                    abort(409, 'This order has already been paid.');
                }

                return;
            }

            if ($order->status !== 'pending') {
                abort(409, 'This order is not awaiting payment.');
            }

            foreach ($order->items()->orderBy('product_id')->get() as $item) {
                $product = Product::query()->lockForUpdate()->find($item->product_id);

                if ($product === null || $product->stock < $item->quantity) {
                    abort(409, 'There is no longer enough stock to complete this order.');
                }

                $product->decrement('stock', $item->quantity);
            }

            $order->update([
                'status' => 'paid',
                'fulfillment_status' => 'processing',
                'razorpay_payment_id' => $payment['razorpay_payment_id'],
            ]);
        });

        return response()->json(['message' => 'Payment verified.', 'order_id' => $localOrder->id]);
    }
}
