<?php

namespace App\Policies;

use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PrintJobPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PrintJob $printJob): bool|Response
    {
        return $user->isAdmin() || $printJob->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isEmployee() && $user->is_active;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PrintJob $printJob): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PrintJob $printJob): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PrintJob $printJob): bool
    {
        return false;
    }

    public function cancel(User $user, PrintJob $printJob): bool
    {
        return $printJob->user_id === $user->id && $printJob->status === PrintJobStatus::Pending;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PrintJob $printJob): bool
    {
        return false;
    }
}
