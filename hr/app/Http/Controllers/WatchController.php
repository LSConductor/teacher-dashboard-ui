<?php

namespace App\Http\Controllers;

use App\Hr\MorningWatch;
use App\Hr\Roles;
use Illuminate\Http\Request;

class WatchController extends Controller
{
    public function index(Request $request)
    {
        $kind = Roles::kind($request->user());
        abort_unless(in_array($kind, ['manage', 'front_desk', 'neighbourhood'], true), 403);

        return view('watch.index', [
            'lines' => MorningWatch::lines($request->user()),
        ]);
    }
}
