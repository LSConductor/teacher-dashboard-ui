<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(): View
    {
        return view('hr.audit', [
            'audits' => Audit::query()->with('actor')->latest('occurred_at')->limit(150)->get(),
        ]);
    }
}
