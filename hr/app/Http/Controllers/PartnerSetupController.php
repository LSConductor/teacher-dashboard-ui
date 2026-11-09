<?php

namespace App\Http\Controllers;

use App\Hr\PartnerPack;
use App\Models\Partner;
use App\Models\PartnerCheck;
use App\Models\PartnerPage;
use App\Models\School;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PartnerSetupController extends Controller
{
    public function index(Request $request)
    {
        $this->prepare($request);
        $pages = PartnerPage::query()
            ->where('school_id', $request->user()->school_id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return view('setup.partners', ['pages' => $pages]);
    }

    public function edit(Request $request, PartnerPage $page)
    {
        abort_unless((int) $page->school_id === (int) $request->user()->school_id, 404);

        return view('setup.partner-edit', ['page' => $page]);
    }

    public function update(Request $request, PartnerPage $page)
    {
        abort_unless((int) $page->school_id === (int) $request->user()->school_id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string'],
            'video_url' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['body'] = preg_replace("~\r\n?~", "\n", $data['body']);
        $data['video_url'] = $this->videoLink($data['video_url'] ?? null);
        $changed = $data['title'] !== $page->title
            || $data['body'] !== preg_replace("~\r\n?~", "\n", (string) $page->body);
        if ($changed && $this->pageWasRead($page)) {
            $data['version'] = $page->version + 1;
        }
        $page->update($data);
        Audit::record('partner.page', $page, ['version' => $page->version]);
        $status = isset($data['version'])
            ? 'Saved as version '.$page->version.'. An earlier finish does not cover the new wording.'
            : 'Page saved.';

        return redirect()->route('setup.partners')->with('status', $status);
    }

    public function check(Request $request)
    {
        $this->prepare($request);
        $check = PartnerCheck::query()->where('school_id', $request->user()->school_id)->firstOrFail();

        return view('setup.partner-check', ['check' => $check]);
    }

    public function updateCheck(Request $request)
    {
        $this->prepare($request);
        $check = PartnerCheck::query()->where('school_id', $request->user()->school_id)->firstOrFail();
        $rows = $request->validate([
            'prompt' => ['required', 'array'],
            'prompt.*' => ['required', 'string', 'max:300'],
            'choice' => ['required', 'array'],
            'answer' => ['required', 'array'],
        ]);
        $quiz = [];
        foreach ($rows['prompt'] as $index => $prompt) {
            $choices = array_values(array_filter(array_map(
                fn ($choice) => trim((string) $choice),
                $rows['choice'][$index] ?? []
            ), fn ($choice) => $choice !== ''));
            if (count($choices) < 2) {
                throw ValidationException::withMessages([
                    'choice' => 'Each question needs at least two answers.',
                ]);
            }
            $answer = (int) ($rows['answer'][$index] ?? 0);
            if (! array_key_exists($answer, $choices)) {
                $answer = 0;
            }
            $quiz[] = ['prompt' => trim($prompt), 'choices' => $choices, 'answer' => $answer];
        }
        $changed = json_encode($quiz) !== json_encode($check->quiz);
        if ($changed && Partner::query()->where('school_id', $check->school_id)->where('quiz_version_passed', $check->version)->exists()) {
            $check->version = $check->version + 1;
        }
        $check->quiz = $quiz;
        $check->save();
        Audit::record('partner.quiz', $check, ['version' => $check->version]);

        return redirect()->route('setup.partners.check')->with('status', 'Check saved.');
    }

    private function prepare(Request $request): void
    {
        $school = School::query()->findOrFail($request->user()->school_id);
        PartnerPack::ensure($school);
    }

    private function pageWasRead(PartnerPage $page): bool
    {
        return Partner::query()
            ->where('school_id', $page->school_id)
            ->get()
            ->contains(fn (Partner $partner) => (int) (($partner->seen ?? [])[$page->slug] ?? 0) === (int) $page->version);
    }

    private function videoLink(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('~^https?://~i', $value)) {
            $value = 'https://'.ltrim($value, '/');
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages([
                'video_url' => 'Paste a full video link, starting with https://.',
            ]);
        }

        return $value;
    }
}
