<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function store(): JsonResponse
    {
        $cart = Cart::query()->create(['token' => (string) Str::uuid()]);

        return response()->json(['cart_token' => $cart->token], 201);
    }

    public function show(string $token): JsonResponse
    {
        return response()->json($this->cartPayload($this->findCart($token)));
    }

    public function addItem(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $cart = DB::transaction(function () use ($data, $token): Cart {
            $cart = Cart::query()->where('token', $token)->lockForUpdate()->firstOrFail();
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();
            $quantity = ($item?->quantity ?? 0) + $data['quantity'];

            if ($quantity > $product->stock) {
                throw ValidationException::withMessages([
                    'quantity' => 'The requested quantity exceeds available stock.',
                ]);
            }

            if ($item === null) {
                $cart->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);
            } else {
                $item->update(['quantity' => $quantity]);
            }

            return $cart;
        });

        return response()->json($this->cartPayload($cart->fresh()));
    }

    public function removeItem(string $token, int $product): JsonResponse
    {
        $cart = $this->findCart($token);
        $cart->items()->where('product_id', $product)->delete();

        return response()->json($this->cartPayload($cart));
    }

    /**
     * @return array<string, mixed>
     */
    private function cartPayload(Cart $cart): array
    {
        $cartItems = $cart->items()->with('product')->get();
        $items = $cartItems->map(function (CartItem $item): array {
            $lineTotalPaise = (int) round((float) $item->product->price * 100) * $item->quantity;

            return [
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'price' => $item->product->price,
                'quantity' => $item->quantity,
                'line_total' => number_format($lineTotalPaise / 100, 2, '.', ''),
            ];
        });
        $totalPaise = $cartItems->sum(
            fn (CartItem $item): int => (int) round((float) $item->product->price * 100) * $item->quantity,
        );

        return [
            'cart_token' => $cart->token,
            'items' => $items,
            'total' => number_format($totalPaise / 100, 2, '.', ''),
        ];
    }

    private function findCart(string $token): Cart
    {
        return Cart::query()->where('token', $token)->firstOrFail();
    }
}
