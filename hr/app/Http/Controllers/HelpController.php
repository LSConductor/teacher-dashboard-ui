<?php

namespace App\Http\Controllers;

use App\Hr\Roles;
use Illuminate\Http\Request;

class HelpController extends Controller
{
    public function show(Request $request)
    {
        return view('help.desk', [
            'kind' => Roles::kind($request->user()),
            'label' => Roles::labelFor($request->user()),
        ]);
    }
}
