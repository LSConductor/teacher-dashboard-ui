<?php

namespace App\Hr;

use App\Models\User;

class Access
{
    /**
     * What each kind of role can open. The casual list, the file, and this page all use these words.
     *
     * @return array<string, array{opens: string, closed: string}>
     */
    public static function matrix(): array
    {
        return [
            'manage' => [
                'opens' => 'Every casual. WWCC, date of birth, contracts, the casual-status letter, setup, audit, the NESA export, and the parents as partners course including the volunteer check.',
                'closed' => 'A red casual still cannot be booked. Pay and student records are not in this app.',
            ],
            'neighbourhood' => [
                'opens' => 'Casuals in their learning neighbourhood, including a casual who listed that neighbourhood, and a casual who works across the school. Find cover for a class and ask the people who are free. Call when the light is green. Book a green casual. A note from the day. Ready parent and grandparent helpers in that neighbourhood.',
                'closed' => 'Another neighbourhood. WWCC number, date of birth, and contract text. A helper’s clearance number, and a helper HR has not cleared. Setup, audit, and the casual-status letter.',
            ],
            'support' => [
                'opens' => 'The casuals who name them as mentor. The goal, the note from the day, the observation, the annual page, and professional learning hours.',
                'closed' => 'Any other casual. WWCC number, date of birth, and contract text. Booking, setup, and audit.',
            ],
            'front_desk' => [
                'opens' => 'Every casual’s light, phone, and today’s booking. Find cover for a class and ask the people who are free. Book a green casual. Book an amber casual only when the Conductor has written the note.',
                'closed' => 'WWCC number, date of birth, and contract text. Setup, audit, and the casual-status letter.',
            ],
            'casual' => [
                'opens' => 'Their own file, their own forms, their own contracts, and the people on site for their neighbourhood.',
                'closed' => 'Any other casual. Setup, audit, and booking.',
            ],
        ];
    }

    /** @return array{opens: string, closed: string} */
    public static function for(User $user): array
    {
        $kind = Roles::kind($user);
        $row = self::matrix()[$kind] ?? [
            'opens' => 'Nothing until a role is set.',
            'closed' => 'This sign-in cannot open a casual file.',
        ];
        if ($kind === 'neighbourhood' && Neighbourhood::coversAll($user->neighbourhood)) {
            $row['opens'] = 'Every learning neighbourhood. Find cover for a class and ask the people who are free. Call when the light is green. Book a green casual. A note from the day. Ready parent and grandparent helpers.';
        } elseif ($kind === 'neighbourhood' && filled($user->neighbourhood)) {
            $row['opens'] = 'Casuals in '.$user->neighbourhood.', including a casual who listed '.$user->neighbourhood.', and a casual who works across the school. Find cover for a class and ask the people who are free. Call when the light is green. Book a green casual. A note from the day. Ready parent and grandparent helpers in '.$user->neighbourhood.'.';
        }

        return $row;
    }
}
