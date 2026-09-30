<?php

namespace App\Policies;

use App\Models\NoteLabel;
use App\Models\User;

class NoteLabelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, NoteLabel $noteLabel): bool
    {
        return $this->owns($user, $noteLabel);
    }

    public function delete(User $user, NoteLabel $noteLabel): bool
    {
        return $this->owns($user, $noteLabel);
    }

    private function owns(User $user, NoteLabel $noteLabel): bool
    {
        return (int) $noteLabel->user_id === (int) $user->id;
    }
}