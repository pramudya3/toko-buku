<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Models\User;
use App\Support\ActivityLogger;

final class UserObserver
{
    public function created(User $user): void
    {
        ActivityLogger::log(ActivityAction::UserCreate, "User '{$user->name}' dibuat", $user);
    }

    public function updated(User $user): void
    {
        ActivityLogger::log(ActivityAction::UserUpdate, "User '{$user->name}' diperbarui", $user);
    }

    public function deleted(User $user): void
    {
        ActivityLogger::log(ActivityAction::UserDelete, "User '{$user->name}' dihapus", $user);
    }
}
