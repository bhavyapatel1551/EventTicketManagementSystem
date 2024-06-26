<?php

namespace App\Http\Controllers;

use App\Events\SentEmailForUpdatedEvent;
use App\Events\SentEmailForUpdateEvent;
use App\Jobs\Jobs;
use App\Jobs\UpdateEventSentMail;
use App\Models\Cart;
use App\Models\Events;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    /**
     * Show event list For Organizer
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function ShowAllEvents(Request $request)
    {
        $id = Auth::id();
        $sortBy = $request->query('sort_by');

        $sortBy = $sortBy ?: 'name';
        $sortBy = $sortBy ?: 'venue';
        $sortBy = $sortBy ?: 'time';
        $sortBy = $sortBy ?: 'date';
        $sortBy = $sortBy ?: 'price';
        // $sortBy = $sortBy ?: 'date_asc';
        // $sortBy = $sortBy ?: 'alpha_asc';

        $events = Events::query();


        switch ($sortBy) {
            case 'name':
                $events->orderBy('name', 'asc');
                break;
            case 'venue':
                $events->orderBy('venue', 'asc');
                break;
            case 'time':
                $events->orderBy('time', 'asc');
                break;
            case 'date':
                $events->orderBy('date', 'asc');
                break;
            case 'price':
                $events->orderBy('price', 'asc');
                break;
            default:
                $events->orderBy('updated_at', 'desc');
        }
        $events = $events->where('organizer_id', $id)->orderByDesc('updated_at')->paginate(10);
        // $events = Events::where('organizer_id', $id)->orderByDesc('updated_at')->paginate(5);
        return view('events.MyEvent', compact('events'));
    }

    /**
     * Show Create Event Form
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function ShowCreateEventPage()
    {
        return view('events.CreateEvent');
    }

    /**
     * Store Event Data to the Database
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createEvent(Request $request)
    {

        /**
         * Validate input field from the form
         */
        $request->validate(
            [
                'name' => 'required|min:3',
                'venue' => 'required|min:3',
                'date' => 'required|date|after_or_equal:today',
                'time' => 'required|date_format:H:i',
                'price' => 'required|numeric',
                'quantity' => 'required|numeric',
                'about' => '',
                'image' => 'mimes:jpeg,png,jpg,gif,avif|max:10240',
            ],
            [
                'name.required' => 'Event Name is Required',
                'name.min' => 'Event Name Must be More than 3 character',
                'image.mimes' => 'Event Image type must be jpeg,png,jpg,gif,avif.',
                'image.max' => 'Event Image size must be less than 10MB',
            ]
        );
        /**
         * If the Event has the image then it will store the image to the local folder in public/storage/event
         * Otherwise it will image as null 
         */
        if ($request->hasFile('image')) {
            $imagepath = $request->file('image')->getClientOriginalName();
            $request->file('image')->storeAs('event', $imagepath, 'public');
            $imagepath = 'event/' . $imagepath;
        } else {
            $imagepath = null;
        }

        $user = Auth::user();
        /**
         * Change the date format according to database.
         */
        $date = Carbon::createFromFormat('Y-m-d', $request['date'], 'UTC')->format('Y-m-d');
        $organizer_id = $user->id;
        Events::create([
            "name" => $request['name'],
            "venue" => $request['venue'],
            "date" => $date,
            "time" => $request['time'],
            "price" => $request['price'],
            "quantity" => $request['quantity'],
            "about" => $request['about'],
            "image" => $imagepath,
            'organizer_id' => $organizer_id,
        ]);
        return redirect()->route("event")->with("success", "Event has been successfully created!");
    }


    /**
     * Show Edit Event Form
     * @param mixed $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function ShowUpdateEventPage($id)
    {
        /**
         * Fetch the Event details by event id 
         */
        $event = Events::findOrFail($id);
        $date = $event->date;
        $time = $event->time;
        /**
         * Change the date formate according the datepicker field
         */
        $newTime = Carbon::createFromFormat('H:i:s', $time)->format('H:i');
        $newDate = Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        return view('events.UpdateEvent', compact('event', 'newTime', 'newDate'));
    }


    /**
     * Update Event Data
     * @param mixed $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function UpdateEvent($id, Request $request)
    {

        /**
         * Validate the data from input field of  form
         */
        $request->validate(
            [
                'name' => 'required|min:3',
                'venue' => 'required|min:3',
                'date' => 'required|date|after_or_equal:today',
                'time' => 'required|date_format:H:i',
                'price' => 'required|numeric',
                'quantity' => 'required|numeric',
                'about' => '',
                'imageUpadte' => 'mimes:jpeg,png,jpg,gif,avif|max:10240',
            ],
            [
                'name.required' => 'Event Name is Required',
                'name.min' => 'Event Name Must be More than 3 character',
                'image.mimes' => 'Event Image type must be jpeg,png,jpg,gif,avif.',
                'image.max' => 'Event Image size must be less than 10MB',
            ]
        );
        /**
         * If we upload the new image then it will update the image path in database
         * otherwise it will keep old image as it is
         */
        if ($request->hasFile('imageUpadte')) {
            $imagepath = $request->file('imageUpadte')->getClientOriginalName();
            $request->file('imageUpadte')->storeAs('event', $imagepath, 'public');
            $imagepath = 'event/' . $imagepath;
            Events::where('id', $id)->update([
                'image' => $imagepath,
            ]);
        }
        /**
         * Change the date formate according to the datepicker field
         */
        $date = Carbon::createFromFormat('Y-m-d', $request['date'])->format('Y-m-d');
        Events::where('id', $id)->update([
            "name" => $request['name'],
            "venue" => $request['venue'],
            "time" => $request['time'],
            "date" => $date,
            "price" => $request['price'],
            "quantity" => $request['quantity'],
            "about" => $request['about'],
        ]);
        UpdateEventSentMail::dispatch($id);
        return redirect()->route("event")->with("success", "Event successfully updated!");
    }


    /**
     * Delete the Event
     * @param mixed $id
     * @return mixed|\Illuminate\Http\RedirectResponse
     */
    public function deleteEvent($id)
    {
        $Order = Order::where('event_id', $id)->first();
        /**
         * If any user has purchase this Event ticket then user can not delete that event
         */
        if ($Order) {
            return redirect('event')->with('error', 'This ticket cannot be deleted as it has already been purchased by someone.');
        } else {
            Events::where('id', $id)->delete();
            return redirect('event')->with('error', 'Event Deleted successfully!');
        }
    }
}
