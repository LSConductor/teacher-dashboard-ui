<?php

namespace App\Hr;

use App\Models\PartnerCheck;
use App\Models\PartnerPage;
use App\Models\School;

class PartnerPack
{
    /** @return array<string, string> */
    public static function interests(): array
    {
        return [
            'reading' => 'Hearing readers',
            'excursion' => 'Excursion',
            'canteen' => 'Canteen',
            'classroom' => 'Classroom',
            'home' => 'At home',
        ];
    }

    /** @return list<array{slug: string, title: string, body: string}> */
    public static function pages(): array
    {
        return [
            [
                'slug' => 'welcome',
                'title' => 'Welcome and thanks',
                'body' => <<<'TXT'
Living School acknowledges we are all learning and growing together on Widjabul/Wyabal country of the Bundjalung Nation, and pays respect to Elders past, present, and future.

The school cares to teach, challenge, and nurture good people for a sustainable future. The values are respect, responsibility, and relationships that are positive and productive. The triangle is the teacher, the parent, and the student. Grandparents belong in that triangle too.

The staff are glad you are here. This short course is the thanks, the expectations, and the role. When you finish, HR has your name. A director can ask you to help once the volunteer check is recorded.
TXT,
            ],
            [
                'slug' => 'aim',
                'title' => 'What this is, and what it is not',
                'body' => <<<'TXT'
Parents as partners nurtures a community of support. It uses the people of the school for the students' learning, builds networks, supports teachers, and leaves room to learn and to enjoy the day.

It is not for labelling children, classes, or teachers. It is not for comparing children, promoting rivalry, or watching the classroom. It does not train you to be a teacher. Teaching stays a profession.

The classroom is the teacher's domain. Wait to be introduced. Let the teacher set the tone. You are there for a good relationship with the students and the teacher.
TXT,
            ],
            [
                'slug' => 'expectations',
                'title' => 'Expectations',
                'body' => <<<'TXT'
Four standards hold the community: a safe environment, respect through the way we speak, active participation, and purposeful learning.

Safety means lawful, responsible conduct, no danger to anyone's physical or emotional security, and conflict resolved without violence. A volunteer is never alone with a child.

Effort means you support the teacher as guided, and you heed the teacher's professional judgement. Respect means courtesy in what you do and what you say. Self-responsibility means you follow the teacher's instructions and own what you do.

You must never use physical force. Living School does not use or condone corporal punishment. If you are unsure, speak to the nearest member of staff.
TXT,
            ],
            [
                'slug' => 'on-the-day',
                'title' => 'The role on the day',
                'body' => <<<'TXT'
Come through the main gate. Report to the Office first. You will be given a badge and asked to sign the parent helpers' book. Do not enter a learning space unless the teacher is present.

The old house is for teachers only. Do not enter it, even if a teacher asks you to collect something.

Before you start, meet the teacher for about twenty minutes. Ask about the day, the students who need help, and what to do in a fire drill or a lockdown. Offer only the time you can keep. If you are ill, tell the Office and stay home.

You are not the class disciplinarian. Follow the teacher's behaviour plan. Do not mark student work. Help children get ready for the next lesson without taking over.
TXT,
            ],
            [
                'slug' => 'with-a-child',
                'title' => 'With a child',
                'body' => <<<'TXT'
Hearing readers is a likely task. With a beginning reader, read together, talk about the pictures, and point to each word. With an early reader, let the child point, find repeated words, and pause at a full stop. With an extending reader, help them work out a new word and talk about what the story means.

Praise effort, not attainment. A useful line is: I like the way you worked out the hard part. Do not use treats as incentives.

Ask the teacher which students need a few extra minutes. You can make a difference at the start or end of a lesson, getting unpacked and organised. Stay in sight of other people. You are never alone with a child.
TXT,
            ],
            [
                'slug' => 'confidence',
                'title' => 'Confidence',
                'body' => <<<'TXT'
Anything you hear or see about a particular child stays inside the school.

If you believe a child is at risk, tell the class teacher or the Conductor. Do not speak to that child's parents. Do not investigate it yourself.

A kind comment outside school is still a breach. Even "Your child reads well" is not yours to say. If a friend asks how their child is going, ask them to speak with the class teacher.
TXT,
            ],
        ];
    }

    /**
     * @return list<array{prompt: string, choices: list<string>, answer: int}>
     */
    public static function quiz(): array
    {
        return [
            [
                'prompt' => 'When may you be alone with a child?',
                'choices' => [
                    'Never',
                    'When the teacher steps out for a minute',
                    'If you know the child well',
                ],
                'answer' => 0,
            ],
            [
                'prompt' => 'Where do you start when you arrive?',
                'choices' => [
                    'The Office, for a badge and the helpers\' book',
                    'Straight into the classroom',
                    'The old house',
                ],
                'answer' => 0,
            ],
            [
                'prompt' => 'Who may enter the old house?',
                'choices' => [
                    'Teachers only',
                    'A volunteer collecting something for a teacher',
                    'Anyone wearing a badge',
                ],
                'answer' => 0,
            ],
            [
                'prompt' => 'A child worries you. Who do you tell?',
                'choices' => [
                    'The class teacher or the Conductor',
                    'The child\'s parents at the gate',
                    'Other parents you know',
                ],
                'answer' => 0,
            ],
            [
                'prompt' => 'When may you use physical force?',
                'choices' => [
                    'Never',
                    'To move a child back to their seat',
                    'If they will not follow an instruction',
                ],
                'answer' => 0,
            ],
            [
                'prompt' => 'Who marks student work?',
                'choices' => [
                    'The teacher. A volunteer does not mark work.',
                    'You mark the reading group',
                    'You mark it if the teacher is busy',
                ],
                'answer' => 0,
            ],
            [
                'prompt' => 'How do you encourage a reader?',
                'choices' => [
                    'Praise the effort. Do not use treats.',
                    'Praise the highest score in the group',
                    'Offer a treat for finishing the book',
                ],
                'answer' => 0,
            ],
            [
                'prompt' => 'You wake up ill on a day you offered. What do you do?',
                'choices' => [
                    'Tell the Office and stay home',
                    'Come in and sit quietly',
                    'Send a message with your child',
                ],
                'answer' => 0,
            ],
        ];
    }

    public static function ensure(School $school): void
    {
        foreach (self::pages() as $sort => $page) {
            PartnerPage::query()->firstOrCreate(
                ['school_id' => $school->id, 'slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'body' => $page['body'],
                    'sort' => $sort + 1,
                    'version' => 1,
                ]
            );
        }

        PartnerCheck::query()->firstOrCreate(
            ['school_id' => $school->id],
            ['quiz' => self::quiz(), 'version' => 1]
        );
    }
}
