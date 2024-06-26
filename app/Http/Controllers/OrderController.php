<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * Show user's purchesed ticket order list page
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function UserPurchaseOrder(Request $request)
    {
        $user_id = Auth::id();
        $orders = Order::where('user_id', $user_id)->with('event')->orderByDesc('created_at')->paginate(10);
        $sortBy = $request->query('sort_by');
        switch ($sortBy) {
                // case 'name':
                //     $orders = Order::where('user_id', $user_id)
                //         ->with(['event' => function ($query) {
                //             $query->orderBy('name', 'asc');
                //         }])
                //         ->paginate(5);
                //     break;
                // case 'venue':
                //     $orders = Order::where('user_id', $user_id)
                //         ->with(['event' => function ($query) {
                //             $query->orderBy('venue', 'asc');
                //         }])
                //         ->paginate(5);
                //     break;
                // case 'time':
                //     $orders = Order::where('user_id', $user_id)
                //         ->with(['event' => function ($query) {
                //             $query->orderBy('time', 'asc');
                //         }])
                //         ->paginate(5);
                //     break;
                // case 'date':
                //     $orders = Order::where('user_id', $user_id)
                //         ->with(['event' => function ($query) {
                //             $query->orderBy('date', 'asc');
                //         }])
                //         ->paginate(5);
                //     break;
            case 'price':
                $orders = Order::where('user_id', $user_id)->orderBy('price', 'asc')
                    ->paginate(10);
                break;
            case 'quantity':
                $orders = Order::where('user_id', $user_id)->orderBy('quantity', 'asc')
                    ->paginate(10);
                break;
            default:
                $orders = Order::where('user_id', $user_id)->orderByDesc('created_at')->paginate(10);
        }
        return view('tickets.UserTicketOrder', compact('orders'));
    }
    /**
     * Show Statistics Page to organizer's event
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function OrganizerOrderDetails(Request $request)
    {
        $user_id = Auth::id();
        $orders = Order::where('organizer_id', $user_id)->with('event', 'user')->orderByDesc('updated_at')->paginate(5);
        $sortBy = $request->query('sort_by');
        switch ($sortBy) {
                // case 'event_name':
                //     $orders = Order::where('organizer_id', $user_id)
                //         ->with(['event' => function ($query) {
                //             $query->orderBy('name', 'asc');
                //         }])
                //         ->with('user')
                //         // ->orderByDesc('created_at')
                //         ->paginate(5);
                //     break;
                // case 'customer_name':
                //     $orders = Order::where('organizer_id', $user_id)
                //         ->with(['user' => function ($query) {
                //             $query->orderBy('name', 'asc');
                //         }])
                //         ->with('event')
                //         // ->orderByDesc('created_at')
                //         ->paginate(5);
                //     break;
            case 'quantity':
                $orders = Order::where('organizer_id', $user_id)->orderBy('quantity', 'asc')->paginate(5);
                break;
            case 'price':
                $orders = Order::where('organizer_id', $user_id)->orderBy('price', 'asc')->paginate(5);
                break;
            default:
                $orders = Order::where('organizer_id', $user_id)->orderByDesc('created_at')->paginate(5);
        }
        $Totalsale = Order::where('organizer_id', $user_id)->sum('quantity');
        $Todaysale = Order::where('organizer_id', $user_id)->whereDate('created_at', Carbon::today())->sum('quantity');
        $Totalprice = Order::where('organizer_id', $user_id)->sum('total_price');
        $Todayprice = Order::where('organizer_id', $user_id)->whereDate('created_at', Carbon::today())->sum('total_price');
        return view('events.EventStatistic', compact('orders', 'Totalsale', 'Totalprice', 'Todaysale', 'Todayprice'));
    }

    /**
     * Show today's sales page for organizer
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function TodaySales()
    {
        $user_id = Auth::id();
        $orders = Order::where('organizer_id', $user_id)->whereDate('created_at', Carbon::today())->with('event', 'user')->orderByDesc('created_at')->paginate(10);
        $Totalsale = Order::where('organizer_id', $user_id)->sum('quantity');
        $Todaysale = Order::where('organizer_id', $user_id)->whereDate('created_at', Carbon::today())->sum('quantity');
        $Totalprice = Order::where('organizer_id', $user_id)->sum('total_price');
        $Todayprice = Order::where('organizer_id', $user_id)->whereDate('created_at', Carbon::today())->sum('total_price');
        return view('events.Todaysale', compact('orders', 'Totalsale', 'Totalprice', 'Todaysale', 'Todayprice'));
    }

    /**
     * Show purchesed ticket of user
     * @param mixed $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function PurchasedTicket($id)
    {
        $user_id = Auth::id();
        $orders = Order::where('id', $id)->first();
        /**
         * If the ticket is purchesed by authorized user then it will show ticket
         */
        $check = $orders->user_id == $user_id;
        if ($check) {
            $ticket = Order::where('id', $id)->with('event')->first();
            return view('tickets.PurchasedTicket', compact('ticket'));
        } else {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Show Ticket info send to  user email
     * @param mixed $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function EmailTicket($id)
    {
        $ticket = Order::where('id', $id)->with('event')->first();
        return view('tickets.PurchasedTicket', compact('ticket'));
    }
}
