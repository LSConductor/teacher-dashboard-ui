<?php

namespace App\Policies;

use App\Hr\Neighbourhood;
use App\Hr\Roles;
use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function view(User $user, Person $person): bool
    {
        if ($user->school_id !== $person->school_id) {
            return false;
        }

        return match (Roles::kind($user)) {
            'casual' => (int) $user->person_id === (int) $person->id,
            'neighbourhood' => Neighbourhood::teaches($person, $user->neighbourhood),
            'support' => (int) $user->id === (int) $person->mentor_user_id,
            'manage', 'front_desk' => true,
            default => false,
        };
    }

    public function viewSensitive(User $user, Person $person): bool
    {
        if (! $this->view($user, $person)) {
            return false;
        }

        return in_array(Roles::kind($user), ['manage', 'casual'], true);
    }

    public function updateFile(User $user, Person $person): bool
    {
        return $this->view($user, $person) && Roles::manages($user);
    }
}
