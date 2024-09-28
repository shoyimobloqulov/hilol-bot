<?php

namespace App\Http\Controllers;

use App\Models\CustomUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class AuthenController extends Controller
{
    // Registration
    public function registration()
    {
        return view('auth.registration');
    }

    public function registerUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:custom_users,email',
            'password' => 'required|min:8|max:12|confirmed'
        ]);

        $user = new CustomUser();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);

        if ($user->save()) {
            return back()->with('success', 'You have registered successfully.');
        } else {
            return back()->with('fail', 'Something went wrong!');
        }
    }

    // Login
    public function login()
    {
        return view('auth.login');
    }

    public function loginUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|max:12'
        ]);

        $user = CustomUser::where('email', $request->email)->first();
        if ($user) {
            if (Hash::check($request->password, $user->password)) {
                $request->session()->put('loginId', $user->id);
                return redirect('dashboard');
            } else {
                return back()->with('fail', 'Password does not match!');
            }
        } else {
            return back()->with('fail', 'This email is not registered.');
        }
    }

    // Dashboard
    public function dashboard()
    {
        if (Session::has('loginId')) {
            $data = CustomUser::where('id', Session::get('loginId'))->first();
            return view('dashboard', compact('data'));
        }
        return redirect('login')->with('fail', 'You must be logged in to access the dashboard.');
    }

    // Logout
    public function logout()
    {
        if (Session::has('loginId')) {
            Session::pull('loginId');
            return redirect('login')->with('success', 'You have been logged out successfully.');
        }
        return redirect('login');
    }
}
