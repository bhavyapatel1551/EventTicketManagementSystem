<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Events;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    // Show Add to Cart Page
    public function ShowCart()
    {
        $user = Auth::user();
        Cart::where('user_id', $user->id)
            ->whereDoesntHave('event', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->delete();

        $cartItems = Cart::where('user_id', $user->id)
            ->with('event')
            ->get();
        $SubTotal = Cart::sum('total_price');
        $ticket = Cart::sum('quantity');
        $TotalItem = Cart::sum('user_id');
        return view('userProfile.Cart', compact('cartItems', 'SubTotal', 'ticket', 'TotalItem'));
    }
    // Directly add to cart 
    public function AddtoCart($id)
    {
        $user = Auth::user();
        $user_id = $user->id;

        $event = Events::findOrFail($id);
        $price = $event->price;

        $cartItem = Cart::where('user_id', $user_id)
            ->where('event_id', $id)
            ->first();

        if ($cartItem) {
            $cartItem->increment('quantity');
            $cartItem->update([
                'total_price' => $cartItem->quantity * $price,
            ]);
        } else {
            Cart::create([
                'user_id' => $user_id,
                'event_id' => $id,
                'quantity' => 1,
                'price' => $price,
                'total_price' => $price,
            ]);
        }

        return redirect()->back()->with('success', 'Added to Cart!');
    }

    public function DeleteFromCart($id)
    {
        Cart::where("id", $id)->delete();
        return redirect()->back()->with("error", "Deleted from Cart!");
    }

    public function CheckOutOrder($id)
    {
        // Retrieve cart items for the specified user
        $cartItems = Cart::where('user_id', $id)->get();

        // Create orders for each cart item
        foreach ($cartItems as $item) {
            Order::create([
                'user_id' => $item->user_id,
                'event_id' => $item->event_id,
                'quantity' => $item->quantity,
                'price' => $item->event->price, // Assuming event price is stored in the event table
                'total_price' => $item->total_price,
                // Add other order details as needed
            ]);
        }

        // Delete cart items associated with the user
        Cart::where('user_id', $id)->delete();

        return redirect()->back()->with('success', 'Added to Cart!');
    }
}