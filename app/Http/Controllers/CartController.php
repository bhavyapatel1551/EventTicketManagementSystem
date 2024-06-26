<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Events;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{

    /**
     * Show Cart Page To user
     */
    public function ShowCart()
    {
        $user = Auth::user();

        /**
         * Check if the ticket in the cart is deleted or not
         * if the ticket is deleted then it will remove from the cart
         */
        Cart::where('user_id', $user->id)
            ->whereDoesntHave('event', function ($query) {
                $query->whereNull('deleted_at');
            })->delete();

        /**
         * Update the cart data from the event table incase of the Event Updation 
         */
        $data = Cart::where('user_id', $user->id)->get();
        foreach ($data as $d) {
            $id = $d->event_id;
            $event = Events::find($id);
            if ($event) {
                $d->update([
                    'price' => $event->price,
                    'total_price' => $event->price * $d->quantity,
                ]);
            }
        }
        /**
         * Fetch the cart data from the database along with Totalitem , tickets, SubTotal.
         */
        $cartItems = Cart::where('user_id', $user->id)
            ->with('event')->orderByDesc('updated_at')
            ->get();
        $SubTotal = Cart::where('user_id', $user->id)->sum('total_price');
        $ticket = Cart::where('user_id', $user->id)->sum('quantity');
        $Totalitem = Cart::where('user_id', $user->id)->count();
        return view('userProfile.Cart', compact('cartItems', 'SubTotal', 'ticket', 'Totalitem'));
    }


    /**
     * Add Data to cart table
     * @param mixed $id
     * @return mixed|\Illuminate\Http\RedirectResponse
     */
    public function AddtoCart($id)
    {
        $user = Auth::user();
        $user_id = $user->id;

        /**
         * Fetch all the data related to event
         */
        $event = Events::findOrFail($id);
        $price = $event->price;
        $organizer_id = $event->organizer_id;
        /**
         * If event has sufficent quanityt of ticket if not then shhow message accordingly.
         */
        if ($event->quantity < 1) {
            return redirect()->back()->with('error', "The quantity for ticket '{$event->name}' is insufficient.");
        }
        /**
         * Check if the item is already in the cart or not
         * if item is already in the cart then it will only increase the quantity of the ticket
         * if item is new then it will create the new entry in the cart.
         */
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
                'organizer_id' => $organizer_id,
                'quantity' => 1,
                'price' => $price,
                'total_price' => $price,
            ]);
        }
        return redirect('dashboard')->with('success', "Item successfully added to your cart!");
    }



    /**
     * Redirect to Payment Gateway for order
     * @return mixed|\Illuminate\Http\RedirectResponse
     */
    public function paymentGateway()
    {
        /**
         * Set your Stripe API Key
         */
        \Stripe\Stripe::setApiKey(config('stripe.sk'));
        $user = Auth::user();
        $email = $user->email;
        /**
         * Get Total price, ticket and other info related to ticket
         */
        $SubTotal = Cart::where('user_id', $user->id)->sum('total_price');
        $ticket = Cart::where('user_id', $user->id)->sum('quantity');
        $eventNames = Cart::where('user_id', $user->id)->with('event')->get();
        $description = 'Purchase total  ' . $ticket . " tickets";
        foreach ($eventNames as $eventName) {
            $description .= " | " . $eventName->event->name . ' ' . $eventName->quantity . ' Tickets, ';
        }

        /**
         * Create a payment Intent id for payment transition id
         */
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $SubTotal * 100, // Convert Amount in cents
            'currency' => 'INR', //  Currency code
            'description' => 'Payment for BookMyTicket.com',
            'metadata' => [
                'user_id' => $user->id,
                'email' => $email,
            ],
        ]);

        /**
         * Create Payment Gateway Session for Payment
         */
        $paymentGateway = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'], // mode of the payment
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'INR',
                        'product_data' => [
                            'name' => 'BookMyTicket.com',
                            'description' => $description,
                        ],
                        'unit_amount' => $SubTotal * 100, // Convert to cents
                    ],
                    'quantity' => 1,
                ],
            ],
            'customer_email' => $email, // add customer's email id
            'billing_address_collection' => 'required', // Request customer's blilling address
            'mode' => 'payment',
            'success_url' => route('CheckOutOrder') . '?payment_intent=' . $paymentIntent->id,
            'cancel_url' => route('cart'), // On cancel order it will redirect back to cart 
        ]);
        /**
         * It will redirect to stripe Payment Gateway url with payment intent id
         */
        return redirect()->away($paymentGateway->url);
    }


    /**
     *Place order after successfull Payment from Stripe .
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\RedirectResponse
     */
    public function CheckOutOrder(Request $request)
    {

        $user = Auth::user();
        $cartItems = Cart::where('user_id', $user->id)->get();
        /**
         * Retrieve the payment intent id from the reques.
         */
        $paymentIntentId = $request->input('payment_intent');
        /**
         * Create Order for each cart item and decrease the Quantity of the ticket 
         */
        foreach ($cartItems as $item) {
            Events::where('id', $item->event_id)->decrement('quantity', $item->quantity);
            Order::create([
                'user_id' => $item->user_id,
                'event_id' => $item->event_id,
                'organizer_id' => $item->organizer_id,
                'quantity' => $item->quantity,
                'price' => $item->event->price,
                'total_price' => $item->total_price,
                'transaction_id' => $paymentIntentId,
            ]);
        }

        /**
         * After the Checkout it will Empty the cart table 
         */
        Cart::where('user_id', $user->id)->delete();

        return redirect('/cart')->with('success', "Your order has been successfully placed!");
    }


    /**
     * Increase Quantity of th ticket.
     * @param mixed $id
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function increaseQuantity($id)
    {
        $user = Auth::user();
        $cart = Cart::where('id', $id)->first();

        /**
         * Check if the ticket has sufficeint quantity or not
         * if ticket has sufficeint quantity then it will increase the Ticket quantity
         * if ticket has insufficient quantity then it will return error message
         */
        $event = Events::findOrFail($cart->event_id);
        if ($event->quantity > $cart->quantity) {
            $cart->increment('quantity');
            $cart->update(['total_price' => $cart->quantity * $cart->price]);
            $SubTotal = Cart::where('user_id', $user->id)->sum('total_price');
            $ticket = Cart::where('user_id', $user->id)->sum('quantity');
            return response()->json([
                'quantity' => $cart->quantity,
                'SubTotal' => '₹' . number_format($SubTotal),
                'ticket' => number_format($ticket),
            ]);
        } else {
            return response()->json(['error' => 'Ticket quantity for ' . $event->name . ' is insufficient.']);
        }
    }
    /**
     * Decrease the quantity of the ticket.
     * @param mixed $id
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function decreaseQuantity($id)
    {
        $user = Auth::user();
        $cart = Cart::where('id', $id)->first();

        /**
         * Check if the cart ticket quantity is less than 1 or not
         * if it will get less then 1 then it will delete the cart item
         * else it will decrease the cart item quantity.
         */
        if ($cart->quantity <= 1) {
            return response()->json(['delete' => true]);
        } else {
            $cart->decrement('quantity');
            $cart->update(['total_price' => $cart->quantity * $cart->price]);
            $SubTotal = Cart::where('user_id', $user->id)->sum('total_price');
            $ticket = Cart::where('user_id', $user->id)->sum('quantity');
            return response()->json([
                'quantity' => $cart->quantity,
                'SubTotal' => '₹' . number_format($SubTotal),
                'ticket' => number_format($ticket),
            ]);
        }
    }

    /**
     * Delete item from Cart
     * @param mixed $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function DeleteFromCart($id)
    {
        Cart::where("id", $id)->delete();
        return redirect()->back()->with("error", "Item removed frome cart!");
    }
}
