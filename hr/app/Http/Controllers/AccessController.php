<?php

namespace App\Http\Controllers;

use App\Hr\Access;
use App\Hr\Roles;
use Illuminate\Http\Request;

class AccessController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return view('access.show', [
            'mine' => Access::for($user),
            'label' => Roles::labelFor($user),
            'matrix' => Roles::manages($user) ? Access::matrix() : null,
        ]);
    }
}
