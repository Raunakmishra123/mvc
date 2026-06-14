<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegistrationController extends Controller {
    public function register(Request $request) {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
            'resume'   => 'required|mimes:pdf|max:5120',
        ]);

        $path = $request->file('resume')->store('resumes', 'public');

        User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
            'resume_path' => $path,
        ]);

        return redirect('/login')->with('success', 'Registration successful!');
    }
}
