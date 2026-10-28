<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;
use Razorpay\Api\Api;
use RuntimeException;

class RazorpayGateway
{
    public function createOrder(Order $order): string
    {
        $razorpayOrder = $this->client()->order->create([
            'receipt' => Str::limit($order->order_number, 40, ''),
            'amount' => $order->amount_paise,
            'currency' => $order->currency,
            'notes' => ['local_order_id' => (string) $order->id],
        ]);

        return $razorpayOrder['id'];
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): void
    {
        $this->client()->utility->verifyPaymentSignature([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ]);
    }

    private function client(): Api
    {
        $key = config('services.razorpay.key');
        $secret = config('services.razorpay.secret');

        if (! is_string($key) || $key === '' || ! is_string($secret) || $secret === '') {
            throw new RuntimeException('Razorpay credentials are not configured.');
        }

        return new Api($key, $secret);
    }
}
