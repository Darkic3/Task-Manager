<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Note $note): bool
    {
        return $this->owns($user, $note);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Note $note): bool
    {
        return $this->owns($user, $note);
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->owns($user, $note);
    }

    public function restore(User $user, Note $note): bool
    {
        return $this->owns($user, $note);
    }

    private function owns(User $user, Note $note): bool
    {
        return (int) $note->user_id === (int) $user->id;
    }
}