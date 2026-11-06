<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function view(User $user, Person $person): bool
    {
        if ($user->school_id !== $person->school_id) {
            return false;
        }

        return match ($user->role) {
            'casual' => (int) $user->person_id === (int) $person->id,
            'director' => $user->neighbourhood === $person->neighbourhood,
            'mentor' => (int) $user->id === (int) $person->mentor_user_id,
            'hr', 'conductor', 'office' => true,
            default => false,
        };
    }

    public function viewSensitive(User $user, Person $person): bool
    {
        if (! $this->view($user, $person)) {
            return false;
        }

        return in_array($user->role, ['hr', 'conductor', 'casual'], true);
    }

    public function updateFile(User $user, Person $person): bool
    {
        return $this->view($user, $person) && in_array($user->role, ['hr', 'conductor'], true);
    }
}
