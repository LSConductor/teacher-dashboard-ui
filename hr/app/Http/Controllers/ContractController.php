<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Person;
use App\Services\Audit;
use App\Services\ContractMerger;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $templates = ContractTemplate::query()
            ->where('school_id', $request->user()->school_id)
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();

        return view('templates.index', compact('templates'));
    }

    public function edit(ContractTemplate $template)
    {
        abort_unless($template->school_id === auth()->user()->school_id, 404);

        return view('templates.edit', compact('template'));
    }

    public function update(Request $request, ContractTemplate $template)
    {
        abort_unless($template->school_id === $request->user()->school_id, 404);
        abort_unless($template->status === 'draft', 422, 'Published versions stay as they were sent.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
        ]);
        $template->update($data);
        Audit::record('template.updated', $template);

        return back();
    }

    public function publish(Request $request, ContractTemplate $template)
    {
        abort_unless($template->school_id === $request->user()->school_id, 404);
        abort_unless($template->status === 'draft', 422);
        $template->update(['status' => 'published', 'published_at' => now()]);
        ContractTemplate::create([
            'school_id' => $template->school_id,
            'key' => $template->key,
            'name' => $template->name,
            'body' => $template->body,
            'status' => 'draft',
            'version' => $template->version + 1,
        ]);
        Audit::record('template.published', $template, ['version' => $template->version]);

        return redirect()->route('templates.index');
    }

    public function issue(Request $request, Person $person, ContractMerger $merger)
    {
        $this->authorize('updateFile', $person);
        $data = $request->validate(['template_id' => ['required', 'integer']]);
        $template = ContractTemplate::query()
            ->where('school_id', $person->school_id)
            ->where('status', 'published')
            ->findOrFail($data['template_id']);
        $contract = Contract::create([
            'school_id' => $person->school_id,
            'person_id' => $person->id,
            'template_id' => $template->id,
            'version' => $template->version,
            'merged_body' => $merger->merge($template, $person),
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        Audit::record('contract.sent', $contract, ['template' => $template->key, 'version' => $template->version]);

        return back();
    }

    public function sign(Request $request, Contract $contract)
    {
        $person = Person::findOrFail($contract->person_id);
        $this->authorize('view', $person);
        $user = $request->user();
        $allowed = in_array($user->role, ['hr', 'conductor'], true) || (int) $user->person_id === (int) $person->id;
        abort_unless($allowed, 403);
        abort_unless($contract->status === 'sent', 422);
        $contract->update(['status' => 'signed', 'signed_at' => now()]);
        Audit::record('contract.signed', $contract);

        return back();
    }
}
