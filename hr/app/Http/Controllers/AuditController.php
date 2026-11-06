<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->where('school_id', $request->user()->school_id)
            ->with('actor')
            ->latest()
            ->limit(100)
            ->get();

        return view('audit.index', compact('logs'));
    }

    public function export(Request $request)
    {
        $rows = \App\Models\Person::query()
            ->where('school_id', $request->user()->school_id)
            ->with('accreditation')
            ->orderBy('legal_name')
            ->get();

        $lines = ["legal_name,level,status,alignment,maintenance_end,etams_checked_at,induction_note"];
        foreach ($rows as $person) {
            $accreditation = $person->accreditation;
            $lines[] = implode(',', [
                '"'.str_replace('"', '""', $person->legal_name).'"',
                $accreditation->level ?? '',
                $accreditation->status ?? '',
                $accreditation->alignment ?? '',
                optional($accreditation?->maintenance_end)->toDateString(),
                optional($accreditation?->etams_checked_at)->toDateString(),
                'WWCC number and date of birth are omitted from this export',
            ]);
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="nesa-casual-check.csv"',
        ]);
    }

    public function roadmap()
    {
        return view('roadmap', ['modules' => config('modules')]);
    }
}
