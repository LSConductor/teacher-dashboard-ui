<?php

namespace App\Http\Controllers;

use App\Hr\ContractPack;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Person;
use App\Services\Audit;
use App\Services\ContractMerger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        ContractPack::refreshUntouched($request->user()->school_id);
        $templates = $this->library($request->user()->school_id);

        return view('templates.index', [
            'templates' => $templates,
            'groups' => $templates->groupBy('key'),
            'canSort' => Schema::hasColumn('contract_templates', 'sort'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
        ]);
        $schoolId = $request->user()->school_id;
        $base = Str::slug($data['name']) ?: 'contract';
        $key = $base;
        $suffix = 2;
        while (ContractTemplate::query()->where('school_id', $schoolId)->where('key', $key)->exists()) {
            $key = $base.'-'.$suffix;
            $suffix++;
        }
        $sort = Schema::hasColumn('contract_templates', 'sort')
            ? ((int) ContractTemplate::query()->where('school_id', $schoolId)->max('sort')) + 1
            : 0;
        $attributes = [
            'school_id' => $schoolId,
            'key' => $key,
            'name' => $data['name'],
            'body' => $data['body'],
            'status' => 'draft',
            'version' => 1,
        ];
        if (Schema::hasColumn('contract_templates', 'sort')) {
            $attributes['sort'] = $sort;
        }
        $template = ContractTemplate::create($attributes);
        Audit::record('template.created', $template);

        return redirect()->route('templates.edit', $template)->with('status', 'Draft created. Publish it when the wording is ready.');
    }

    public function move(Request $request, ContractTemplate $template)
    {
        abort_unless((int) $template->school_id === (int) $request->user()->school_id, 404);
        if (! Schema::hasColumn('contract_templates', 'sort')) {
            return back()->withErrors(['sort' => 'Run php8.4 artisan migrate --force, then the library can be reordered.']);
        }
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $groups = $this->library($template->school_id)->groupBy('key')->values();
        $index = $groups->search(fn ($rows) => $rows->first()->key === $template->key);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index === false || $swap < 0 || $swap >= $groups->count()) {
            return back();
        }
        $other = $groups[$swap]->first();
        $first = (int) $groups[$index]->min('sort');
        $second = (int) $groups[$swap]->min('sort');
        if ($first === $second) {
            $first = $index + 1;
            $second = $swap + 1;
        }
        ContractTemplate::query()->where('school_id', $template->school_id)->where('key', $template->key)->update(['sort' => $second]);
        ContractTemplate::query()->where('school_id', $template->school_id)->where('key', $other->key)->update(['sort' => $first]);
        Audit::record('template.moved', $template, ['direction' => $direction]);

        return back();
    }

    public function archive(Request $request, ContractTemplate $template)
    {
        abort_unless((int) $template->school_id === (int) $request->user()->school_id, 404);
        abort_unless($template->status !== 'archived', 422);
        $template->update(['status' => 'archived']);
        Audit::record('template.archived', $template, ['version' => $template->version]);

        return back()->with('status', 'That version is retired. Contracts already sent keep it.');
    }

    public function complete(Request $request, ContractMerger $merger)
    {
        $schoolId = $request->user()->school_id;
        $people = Person::query()->where('school_id', $schoolId)->orderBy('preferred_name')->get();
        $templates = ContractTemplate::query()
            ->where('school_id', $schoolId)
            ->where('status', 'published')
            ->orderBy('name')
            ->get();
        $person = $request->filled('person_id')
            ? $people->firstWhere('id', (int) $request->input('person_id'))
            : null;
        $template = $request->filled('template_id')
            ? $templates->firstWhere('id', (int) $request->input('template_id'))
            : null;
        $preview = ($person && $template)
            ? $merger->merge($template, $person, array_filter([
                'class_label' => $request->input('class_label'),
                'booked_on' => $request->input('booked_on'),
                'start_time' => $request->input('start_time'),
                'finish_time' => $request->input('finish_time'),
            ]))
            : null;

        return view('templates.complete', compact('people', 'templates', 'person', 'template', 'preview'));
    }

    public function send(Request $request, ContractMerger $merger)
    {
        $data = $request->validate([
            'person_id' => ['required', 'integer'],
            'template_id' => ['required', 'integer'],
            'class_label' => ['nullable', 'string', 'max:120'],
            'booked_on' => ['nullable', 'date'],
            'start_time' => ['nullable', 'string', 'max:20'],
            'finish_time' => ['nullable', 'string', 'max:20'],
        ]);
        $person = Person::query()->where('school_id', $request->user()->school_id)->findOrFail($data['person_id']);
        $this->authorize('updateFile', $person);
        $template = ContractTemplate::query()
            ->where('school_id', $person->school_id)
            ->where('status', 'published')
            ->findOrFail($data['template_id']);
        $contract = Contract::create([
            'school_id' => $person->school_id,
            'person_id' => $person->id,
            'template_id' => $template->id,
            'version' => $template->version,
            'merged_body' => $merger->merge($template, $person, array_filter([
                'class_label' => $data['class_label'] ?? null,
                'booked_on' => $data['booked_on'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'finish_time' => $data['finish_time'] ?? null,
            ])),
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        Audit::record('contract.sent', $contract, ['template' => $template->key, 'version' => $template->version]);
        $this->receiptWithEngagement($person, $template->key);

        return redirect()->route('casuals.show', $person);
    }

    public function edit(ContractTemplate $template)
    {
        abort_unless($template->school_id === auth()->user()->school_id, 404);
        ContractPack::refreshUntouched($template->school_id);
        $template->refresh();

        return view('templates.edit', [
            'template' => $template,
            'preview' => app(ContractMerger::class)->sample($template->body),
        ]);
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
        $next = [
            'school_id' => $template->school_id,
            'key' => $template->key,
            'name' => $template->name,
            'body' => $template->body,
            'status' => 'draft',
            'version' => $template->version + 1,
        ];
        if (Schema::hasColumn('contract_templates', 'sort')) {
            $next['sort'] = $template->sort;
        }
        ContractTemplate::create($next);
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
            'merged_body' => $merger->merge($template, $person, array_filter([
                'class_label' => $request->input('class_label'),
                'booked_on' => $request->input('booked_on'),
                'start_time' => $request->input('start_time'),
                'finish_time' => $request->input('finish_time'),
            ])),
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        Audit::record('contract.sent', $contract, ['template' => $template->key, 'version' => $template->version]);
        $this->receiptWithEngagement($person, $template->key);

        return back();
    }

    public function sign(Request $request, Contract $contract)
    {
        $person = Person::findOrFail($contract->person_id);
        $this->authorize('view', $person);
        $user = $request->user();
        $allowed = \App\Hr\Roles::manages($user) || (int) $user->person_id === (int) $person->id;
        abort_unless($allowed, 403);
        abort_unless($contract->status === 'sent', 422);
        $contract->update(['status' => 'signed', 'signed_at' => now()]);
        Audit::record('contract.signed', $contract);

        return back();
    }

    public function withdraw(Request $request, Contract $contract)
    {
        $this->guardContract($request, $contract);
        abort_unless($contract->status === 'sent', 422, 'A signed contract stays on the file.');
        $contract->update(['status' => 'withdrawn']);
        Audit::record('contract.withdrawn', $contract);

        return back()->with('status', 'Unsigned contract withdrawn.');
    }

    public function moveContract(Request $request, Contract $contract)
    {
        $from = $this->guardContract($request, $contract);
        abort_unless($contract->status === 'sent', 422, 'A signed contract stays on the person who signed it.');
        $data = $request->validate(['person_id' => ['required', 'integer']]);
        $to = Person::query()->where('school_id', $from->school_id)->findOrFail($data['person_id']);
        $this->authorize('updateFile', $to);
        $body = $contract->merged_body;
        foreach ([[$from->legal_name, $to->legal_name], [$from->preferred_name, $to->preferred_name], [$from->email, $to->email]] as [$old, $new]) {
            if ($old && $new && $old !== $new) {
                $body = str_replace($old, $new, $body);
            }
        }
        if ($from->mobile && $to->mobile && $from->mobile !== $to->mobile) {
            $body = str_replace($from->mobile, $to->mobile, $body);
        }
        $contract->update(['person_id' => $to->id, 'merged_body' => $body]);
        Audit::record('contract.moved', $contract, ['from' => $from->id, 'to' => $to->id]);

        return redirect()->route('casuals.show', $to)->with('status', 'Contract moved to '.$to->preferred_name.'.');
    }

    private function receiptWithEngagement(Person $person, string $key): void
    {
        if ($key !== 'casual-pool' || \App\Hr\ContractDesk::alreadySent($person, 'ceis-receipt')) {
            return;
        }

        \App\Hr\ContractDesk::issue($person, 'ceis-receipt');
    }

    private function library(int $schoolId)
    {
        $query = ContractTemplate::query()->where('school_id', $schoolId);
        if (Schema::hasColumn('contract_templates', 'sort')) {
            $query->orderBy('sort');
        }

        return $query->orderBy('name')->orderByDesc('version')->get();
    }

    private function guardContract(Request $request, Contract $contract): Person
    {
        abort_unless((int) $contract->school_id === (int) $request->user()->school_id, 404);
        $person = Person::query()->findOrFail($contract->person_id);
        $this->authorize('updateFile', $person);

        return $person;
    }
}
