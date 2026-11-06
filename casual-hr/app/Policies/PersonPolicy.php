<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function viewHr(User $user, Person $person): bool
    {
        return (int) $user->school_id === (int) $person->school_id
            && in_array($user->role, [User::HR, User::CONDUCTOR], true);
    }

    public function create(User $user): bool
    {
        return $user->role === User::HR;
    }

    public function update(User $user, Person $person): bool
    {
        return $user->role === User::HR && (int) $user->school_id === (int) $person->school_id;
    }

    public function updateAccreditation(User $user, Person $person): bool
    {
        return $this->update($user, $person);
    }

    public function align(User $user, Person $person): bool
    {
        return $user->role === User::CONDUCTOR && (int) $user->school_id === (int) $person->school_id;
    }

    public function updateWwcc(User $user, Person $person): bool
    {
        return $this->update($user, $person);
    }

    public function viewWwcc(User $user, Person $person): bool
    {
        return $user->role === User::HR && (int) $user->school_id === (int) $person->school_id;
    }

    public function invite(User $user, Person $person): bool
    {
        return $this->update($user, $person);
    }

    public function book(User $user, Person $person): bool
    {
        if ((int) $user->school_id !== (int) $person->school_id) {
            return false;
        }

        if ($user->role === User::OFFICE || $user->role === User::CONDUCTOR) {
            return true;
        }

        return $user->role === User::DIRECTOR
            && $user->neighbourhood_id !== null
            && (int) $user->neighbourhood_id === (int) $person->neighbourhood_id;
    }
}
