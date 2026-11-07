<?php

namespace App\Hr;

class InductionPack
{
    public static function body(string $title): string
    {
        return self::pages()[$title] ?? $title;
    }

    /**
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return [
            'Child protection and mandatory reporting' => <<<'TXT'
You are a mandatory reporter. If you have reasonable grounds to suspect a child is at risk of significant harm, report it the same day. Do not investigate it yourself, and do not promise a child you will keep a disclosure secret.

Tell the Learning Neighbourhood Director before you leave the site, and the office if the director is teaching. If a child is in immediate danger, call 000 first.

This page is the school record that you have been told. It is not the report itself.
TXT,
            'Reportable conduct' => <<<'TXT'
Reportable conduct is an allegation about a worker's behaviour towards a child, including sexual offence, sexual misconduct, ill-treatment, neglect, and assault. A day that went badly is not automatically reportable conduct. An allegation of this kind is.

If you see it, or someone alleges it about you or a colleague, tell the Conductor the same day. Do not interview the child for evidence. The school, not the casual teacher, decides the report to the Office of the Children's Guardian.
TXT,
            'Professional boundaries' => <<<'TXT'
Use your school role and the child's name. No private social media, no personal phone contact with a student, and no lifts home. Gifts, secrets, and being alone with a student behind a closed door are not part of the day.

Physical contact is for immediate care or safety, in sight of others, and then you stop. If you are unsure, ask the director before you act.
TXT,
            'Code of conduct' => <<<'TXT'
Treat students and staff with respect. Follow the class program you were given. Do not photograph students. Do not discuss a student's history in the staff room as a story.

Alcohol, other drugs, and weapons have no place on site. If you cannot teach safely today, call the office before the day starts and do not attend.
TXT,
            'Emergency, evacuation, and flood' => <<<'TXT'
The school day is 9:00 to 3:15. Visitors and casuals follow the same evacuation as the class they are with. Take the roll the class teacher left, walk the class to the assembly point, and stay with them until a director or the office checks you off.

If the site is flooding, follow the local flood instruction for that neighbourhood. Do not send students home on their own. The office number is (02) 5632 1218.
TXT,
            'How pay works and who to call' => <<<'TXT'
Each day is offered and accepted. Pay is for the day you worked, through payroll. This app does not hold a tax file number. If payroll does not yet have your tax declaration, tell HR. Do not email a tax file number.

Your director's name and mobile are on your casual record. The office number is (02) 5632 1218. Call the office before 8:00 if you cannot attend.
TXT,
            'How to raise a student incident' => <<<'TXT'
A student incident is not a note about your own teaching. If a student is hurt, harmed, or alleges harm, tell the director the same day and use the school's existing incident desk for that class. Do not write the student's story into a day note or into this HR file.

A day note is three lines about the teaching. Keep the two records apart.
TXT,
            'Attendance and the class admin you will use' => <<<'TXT'
Use the roll the class teacher left. Mark who is present. Tell the office about an unexplained absence. Do not invent a new roll in this HR app.
TXT,
            'Adjustments and disability obligations' => <<<'TXT'
Read the adjustments the class teacher left for the day. Follow them. If a student needs something the plan does not cover, ask the director. Do not remove an adjustment because the day is busy.
TXT,
            'Meeting your mentor' => <<<'TXT'
Your mentor is named on your record. Conditional and Provisional teachers use that mentor as Accreditation Supervisor when the mentor is Proficient or above. Arrange the first conversation within the first days you work.
TXT,
            'NESA path for Conditional and Provisional teachers' => <<<'TXT'
Proficient teachers keep their maintenance in eTAMS. Conditional and Provisional teachers collect evidence with their mentor: observation, student work, and the program they taught from. The school copy is not a second accreditation. eTAMS remains the record NESA audits.
TXT,
        ];
    }

    /**
     * Short checks. The answer is the choice index. The page above is the source.
     *
     * @return list<array{prompt: string, choices: list<string>, answer: int}>
     */
    public static function quiz(string $title): array
    {
        return self::quizzes()[$title] ?? [];
    }

    /**
     * @return array<string, list<array{prompt: string, choices: list<string>, answer: int}>>
     */
    public static function quizzes(): array
    {
        return [
            'Child protection and mandatory reporting' => [
                [
                    'prompt' => 'A child asks you to keep a disclosure secret. What do you do?',
                    'choices' => [
                        'Tell the director the same day, and do not promise secrecy',
                        'Promise to keep it secret so the child will keep talking',
                        'Interview the child until you have the full story',
                    ],
                    'answer' => 0,
                ],
                [
                    'prompt' => 'A child is in immediate danger. What is first?',
                    'choices' => ['Call 000', 'Write a day note', 'Email the parent'],
                    'answer' => 0,
                ],
            ],
            'Reportable conduct' => [
                [
                    'prompt' => 'Someone alleges a worker has harmed a child. What do you do?',
                    'choices' => [
                        'Tell the Conductor the same day',
                        'Decide yourself whether it is serious enough',
                        'Interview the child for evidence',
                    ],
                    'answer' => 0,
                ],
                [
                    'prompt' => 'Is a hard teaching day, by itself, reportable conduct?',
                    'choices' => ['Yes', 'No'],
                    'answer' => 1,
                ],
            ],
            'Professional boundaries' => [
                [
                    'prompt' => 'A student asks for your personal phone number. What is the boundary?',
                    'choices' => [
                        'No personal phone contact with a student',
                        'Only if the parent agrees by text',
                        'Only for that day',
                    ],
                    'answer' => 0,
                ],
            ],
            'Code of conduct' => [
                [
                    'prompt' => 'You cannot teach safely today. What do you do?',
                    'choices' => [
                        'Call the office before the day starts and do not attend',
                        'Come in and see how the morning goes',
                        'Ask a student to watch the class',
                    ],
                    'answer' => 0,
                ],
            ],
            'Emergency, evacuation, and flood' => [
                [
                    'prompt' => 'The evacuation alarm sounds. What do you take?',
                    'choices' => [
                        'The roll the class teacher left, and you stay with the class',
                        'Only your own bag',
                        'You send students to the office in pairs',
                    ],
                    'answer' => 0,
                ],
            ],
            'How pay works and who to call' => [
                [
                    'prompt' => 'Where does a tax file number belong?',
                    'choices' => [
                        'With payroll, not in this app and not in an email',
                        'On the day-one acknowledgement',
                        'In a text to the director',
                    ],
                    'answer' => 0,
                ],
            ],
            'How to raise a student incident' => [
                [
                    'prompt' => 'A student is hurt. Where does that record go?',
                    'choices' => [
                        'Tell the director the same day and use the school incident desk',
                        'Write it into a day note in this HR file',
                        'Keep it until the class teacher returns',
                    ],
                    'answer' => 0,
                ],
            ],
            'Attendance and the class admin you will use' => [
                [
                    'prompt' => 'Which roll do you use?',
                    'choices' => [
                        'The roll the class teacher left',
                        'A new roll in this HR app',
                        'A list you write from memory at the end of the day',
                    ],
                    'answer' => 0,
                ],
            ],
            'Adjustments and disability obligations' => [
                [
                    'prompt' => 'The day is busy. Can you drop an adjustment from the plan?',
                    'choices' => ['No. Follow the plan, and ask the director if something is missing', 'Yes, if the class is settled'],
                    'answer' => 0,
                ],
            ],
            'Meeting your mentor' => [
                [
                    'prompt' => 'Who is the Accreditation Supervisor for a Conditional or Provisional teacher?',
                    'choices' => [
                        'The named mentor, when that mentor is Proficient or above',
                        'Any casual on the list',
                        'The office',
                    ],
                    'answer' => 0,
                ],
            ],
            'NESA path for Conditional and Provisional teachers' => [
                [
                    'prompt' => 'Which record does NESA audit?',
                    'choices' => ['eTAMS', 'This HR app', 'The day note'],
                    'answer' => 0,
                ],
            ],
        ];
    }
}
