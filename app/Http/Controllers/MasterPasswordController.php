<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MasterPasswordController extends Controller
{
    public function edit() { return view('auth.change-password'); }

    public function update(Request $request)
    {
        $request->validate(['current_password' => 'required|current_password', 'password' => 'required|string|min:14|confirmed']);
        $request->user()->forceFill(['password' => Hash::make($request->input('password'))])->save();
        $request->session()->regenerate();
        return redirect()->route('master.password.edit')->with('status', 'Senha alterada. Remova o arquivo da credencial inicial.');
    }
}
