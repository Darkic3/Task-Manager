<?php

namespace App\Policies;

use App\Models\NoteSubject;
use App\Models\User;

class NoteSubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, NoteSubject $noteSubject): bool
    {
        return (int) $noteSubject->user_id === (int) $user->id;
    }

    public function delete(User $user, NoteSubject $noteSubject): bool
    {
        return (int) $noteSubject->user_id === (int) $user->id;
    }
}