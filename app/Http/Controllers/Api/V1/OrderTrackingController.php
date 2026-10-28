<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderTrackingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'uuid'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $order = Order::query()
            ->with('items:id,order_id,product_name,quantity')
            ->where('order_number', $data['order_number'])
            ->whereRaw('LOWER(customer_email) = ?', [Str::lower($data['email'])])
            ->first();

        if ($order === null) {
            throw ValidationException::withMessages([
                'order_number' => 'We could not find an order matching those details.',
            ]);
        }

        $statuses = [
            'processing' => 'Order confirmed',
            'shipped' => 'Shipped',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
        ];
        $statusOrder = array_keys($statuses);
        $currentStatus = $order->fulfillment_status;
        $currentIndex = array_search($currentStatus, $statusOrder, true);

        if ($currentStatus === 'awaiting_payment' && $order->status === 'payment_failed') {
            $currentLabel = 'Payment not completed';
        } elseif ($currentStatus === 'awaiting_payment') {
            $currentLabel = 'Awaiting payment';
        } elseif ($currentStatus === 'cancelled') {
            $currentLabel = 'Order cancelled';
        } else {
            $currentLabel = $statuses[$currentStatus] ?? 'Order update';
        }

        $timeline = collect($statuses)
            ->map(fn (string $label, string $status): array => [
                'status' => $status,
                'label' => $label,
                'complete' => $currentIndex !== false && $currentIndex >= array_search($status, $statusOrder, true),
                'current' => $currentStatus === $status,
            ])
            ->when($currentStatus === 'cancelled', fn ($steps) => $steps->map(
                fn (array $step): array => [
                    ...$step,
                    'complete' => $step['status'] === 'processing',
                    'current' => false,
                ],
            )->push([
                'status' => 'cancelled',
                'label' => 'Order cancelled',
                'complete' => true,
                'current' => true,
            ]))
            ->values();

        return response()->json([
            'order_number' => $order->order_number,
            'payment_status' => $order->status,
            'fulfillment_status' => $currentStatus,
            'status_label' => $currentLabel,
            'amount' => $order->amount_paise,
            'currency' => $order->currency,
            'placed_at' => $order->created_at->toIso8601String(),
            'shipped_at' => $order->shipped_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'carrier' => $order->shipping_carrier,
            'tracking_number' => $order->tracking_number,
            'tracking_url' => $order->tracking_url,
            'timeline' => $timeline,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
            ]),
        ]);
    }
}
