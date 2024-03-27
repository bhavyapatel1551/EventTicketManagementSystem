<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function index()
    {
        $user = User::find(Auth::id());

        return view('userProfile.user-profile', compact('user'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|min:3|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . Auth::id(),
            'location' => 'max:255',
            'phone' => 'numeric|digits:10',
            'about' => 'max:255',
        ], [
            'name.required' => 'Name is required',
            'email.required' => 'Email is required',
        ]);

        $user = User::find(Auth::id());

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'location' => $request->location,
            'phone' => $request->phone,
            'about' => $request->about,
        ]);

        return back()->with('success', 'Profile updated successfully.');
    }
    public function showprofilephotoform()
    {

        return view('userProfile.updateprofilephoto');
    }
    public function updateprofilephoto(Request $request)
    {
        // return response($id);
        $data = $request->validate([
            'photo' => 'mimes:jpeg,png,jpg,gif|max:10240',
        ]);
        if ($request->hasFile('photo')) {
            $imagepath = $request->file('photo')->getClientOriginalName();
            $request->file('photo')->storeAs('pfp', $imagepath, 'public');
            $imagepath = 'pfp/' . $imagepath; // Update the image path to include the 'pfp' folder
        } else {
            $imagepath = null;
        }

        $user = User::find(Auth::id());
        $user->update([
            'pfp' => $imagepath
        ]);
        return redirect('user-profile')->with('success', 'Photo uploaded!!');
    }
}
