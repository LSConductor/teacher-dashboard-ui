<?php

namespace App\Http\Controllers;

use App\Hr\Neighbourhood;
use App\Hr\PartnerCourse;
use App\Hr\PartnerPack;
use App\Models\Partner;
use App\Models\PartnerCheck;
use App\Models\PartnerPage;
use App\Models\School;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartnerJoinController extends Controller
{
    public function create()
    {
        $school = $this->school();

        return view('partners.join', [
            'neighbourhoods' => Neighbourhood::names($school->id),
        ]);
    }

    public function store(Request $request)
    {
        $school = $this->school();
        $names = Neighbourhood::names($school->id);
        $data = $request->validate([
            'preferred_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'relationship' => ['required', Rule::in(['parent', 'grandparent'])],
            'child_name' => ['required', 'string', 'max:160'],
            'neighbourhood' => ['required', 'string', Rule::in($names)],
        ]);

        $existing = Partner::query()
            ->where('school_id', $school->id)
            ->where('email', $data['email'])
            ->first();
        if ($existing) {
            return redirect()
                ->route('partners.course', $existing)
                ->with('status', 'This email is already on the course. Continue here.');
        }

        $partner = Partner::create([
            ...$data,
            'school_id' => $school->id,
            'token' => Str::random(40),
        ]);
        Audit::record('partner.started', $partner, ['name' => $partner->preferred_name]);

        return redirect()->route('partners.course', $partner);
    }

    public function course(Partner $partner)
    {
        $pages = $this->pages($partner);

        return view('partners.play', [
            'step' => 'course',
            'partner' => $partner,
            'pages' => $pages,
            'next' => PartnerCourse::firstUnread($partner, $pages),
        ]);
    }

    public function page(Partner $partner, string $slug)
    {
        $pages = $this->pages($partner);
        $page = $this->findPage($pages, $slug);
        if (! PartnerCourse::unlocked($partner, $pages, $page)) {
            return redirect()->route('partners.page', [$partner, PartnerCourse::firstUnread($partner, $pages)->slug]);
        }

        return view('partners.play', [
            'step' => 'page',
            'partner' => $partner,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function next(Partner $partner, string $slug)
    {
        $pages = $this->pages($partner);
        $page = $this->findPage($pages, $slug);
        abort_unless(PartnerCourse::unlocked($partner, $pages, $page), 403);
        $seen = $partner->seen ?? [];
        $seen[$page->slug] = (int) $page->version;
        $partner->seen = $seen;
        $partner->save();

        $next = PartnerCourse::firstUnread($partner, $pages);
        if ($next) {
            return redirect()->route('partners.page', [$partner, $next->slug]);
        }

        return redirect()->route('partners.check', $partner);
    }

    public function check(Partner $partner)
    {
        $pages = $this->pages($partner);
        if ($unread = PartnerCourse::firstUnread($partner, $pages)) {
            return redirect()->route('partners.page', [$partner, $unread->slug]);
        }

        return view('partners.play', [
            'step' => 'check',
            'partner' => $partner,
            'quiz' => $this->quiz($partner),
        ]);
    }

    public function saveCheck(Request $request, Partner $partner)
    {
        $pages = $this->pages($partner);
        if ($unread = PartnerCourse::firstUnread($partner, $pages)) {
            return redirect()->route('partners.page', [$partner, $unread->slug]);
        }
        $check = $this->quizRow($partner);
        $answers = $request->input('answers', []);
        foreach ($check->quiz ?? [] as $index => $question) {
            if ((int) ($answers[$index] ?? -1) !== (int) $question['answer']) {
                return back()
                    ->withErrors(['answers' => 'One answer does not match the page. Read it again and try the check.'])
                    ->withInput();
            }
        }
        $partner->quiz_version_passed = (int) $check->version;
        if (PartnerCourse::formDone($partner)) {
            $partner->finished_at = $partner->finished_at ?? now();
        }
        $partner->save();

        return redirect()->route('partners.about', $partner);
    }

    public function about(Partner $partner)
    {
        $check = $this->quizRow($partner);
        if ((int) $partner->quiz_version_passed !== (int) $check->version) {
            return redirect()->route('partners.check', $partner);
        }

        return view('partners.play', [
            'step' => 'about',
            'partner' => $partner,
            'interests' => PartnerPack::interests(),
        ]);
    }

    public function saveAbout(Request $request, Partner $partner)
    {
        $check = $this->quizRow($partner);
        if ((int) $partner->quiz_version_passed !== (int) $check->version) {
            return redirect()->route('partners.check', $partner);
        }
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:40'],
            'interests' => ['required', 'array', 'min:1'],
            'interests.*' => [Rule::in(array_keys(PartnerPack::interests()))],
            'strengths' => ['required', 'string', 'max:2000'],
            'work_style' => ['required', Rule::in(['set_path', 'independent'])],
            'with_children' => ['required', Rule::in(['room', 'home', 'either'])],
            'availability' => ['required', 'string', 'max:160'],
        ]);
        $partner->fill($data);
        $partner->finished_at = now();
        $partner->save();
        Audit::record('partner.finished', $partner, ['name' => $partner->preferred_name]);

        return redirect()->route('partners.done', $partner);
    }

    public function done(Partner $partner)
    {
        $pages = $this->pages($partner);
        $check = $this->quizRow($partner);
        if (PartnerCourse::status($partner, $pages, $check) === 'reading' || PartnerCourse::status($partner, $pages, $check) === 'quiz' || PartnerCourse::status($partner, $pages, $check) === 'form') {
            return redirect()->route('partners.course', $partner);
        }

        return view('partners.play', [
            'step' => 'done',
            'partner' => $partner,
            'interests' => PartnerPack::interests(),
        ]);
    }

    private function school(): School
    {
        return School::query()->where('slug', 'living-school')->firstOrFail();
    }

    private function pages(Partner $partner)
    {
        return PartnerPage::query()
            ->where('school_id', $partner->school_id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    private function findPage($pages, string $slug): PartnerPage
    {
        $page = $pages->firstWhere('slug', $slug);
        abort_unless($page, 404);

        return $page;
    }

    private function quizRow(Partner $partner): PartnerCheck
    {
        return PartnerCheck::query()->where('school_id', $partner->school_id)->firstOrFail();
    }

    private function quiz(Partner $partner): array
    {
        return $this->quizRow($partner)->quiz ?? [];
    }
}
