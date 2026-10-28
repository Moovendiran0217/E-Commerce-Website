<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('orders:update-tracking
    {order_number : The order reference shown to the customer}
    {status : processing, shipped, out_for_delivery, delivered, or cancelled}
    {--carrier= : Shipping carrier name}
    {--tracking-number= : Carrier tracking number}
    {--tracking-url= : HTTPS carrier tracking page URL}')]
#[Description('Update shipment status and carrier tracking details for an order')]
class UpdateOrderTracking extends Command
{
    public function handle(): int
    {
        $order = Order::query()->where('order_number', $this->argument('order_number'))->first();

        if ($order === null) {
            $this->error('Order not found.');

            return self::FAILURE;
        }

        if ($order->status !== 'paid') {
            $this->error('Shipment tracking can only be updated after payment is confirmed.');

            return self::FAILURE;
        }

        $status = (string) $this->argument('status');
        $allowedStatuses = ['processing', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'];

        if (! in_array($status, $allowedStatuses, true)) {
            $this->error('Status must be processing, shipped, out_for_delivery, delivered, or cancelled.');

            return self::FAILURE;
        }

        if (! $this->canTransition($order->fulfillment_status, $status)) {
            $this->error("Cannot change fulfillment status from {$order->fulfillment_status} to {$status}.");

            return self::FAILURE;
        }

        $trackingDetails = [
            'shipping_carrier' => $this->option('carrier'),
            'tracking_number' => $this->option('tracking-number'),
            'tracking_url' => $this->option('tracking-url'),
        ];

        if (in_array($status, ['shipped', 'out_for_delivery', 'delivered'], true)) {
            $validator = Validator::make($trackingDetails, [
                'shipping_carrier' => ['required', 'string', 'max:255'],
                'tracking_number' => ['required', 'string', 'max:255'],
                'tracking_url' => ['required', 'url', 'max:2048'],
            ]);

            if ($validator->fails() || parse_url((string) $trackingDetails['tracking_url'], PHP_URL_SCHEME) !== 'https') {
                $this->error('Shipped orders require a carrier, tracking number, and valid HTTPS tracking URL.');

                return self::FAILURE;
            }
        } elseif (in_array($status, ['processing', 'cancelled'], true)) {
            $trackingDetails = [
                'shipping_carrier' => $order->shipping_carrier,
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->tracking_url,
            ];
        }

        $order->update([
            ...$trackingDetails,
            'fulfillment_status' => $status,
            'shipped_at' => $status === 'shipped' ? ($order->shipped_at ?? now()) : $order->shipped_at,
            'delivered_at' => $status === 'delivered' ? ($order->delivered_at ?? now()) : $order->delivered_at,
        ]);

        $this->info("Order {$order->order_number} updated to {$status}.");

        return self::SUCCESS;
    }

    private function canTransition(string $currentStatus, string $nextStatus): bool
    {
        return $currentStatus === $nextStatus
            || in_array($nextStatus, match ($currentStatus) {
                'processing' => ['shipped', 'cancelled'],
                'shipped' => ['out_for_delivery'],
                'out_for_delivery' => ['delivered'],
                default => [],
            }, true);
    }
}
