<?php

namespace App\Policies;

use App\Models\Notebook;
use App\Models\User;

class NotebookPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Notebook $notebook): bool
    {
        return $this->owns($user, $notebook);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Notebook $notebook): bool
    {
        return $this->owns($user, $notebook);
    }

    public function delete(User $user, Notebook $notebook): bool
    {
        return $this->owns($user, $notebook);
    }

    private function owns(User $user, Notebook $notebook): bool
    {
        return (int) $notebook->user_id === (int) $user->id;
    }
}