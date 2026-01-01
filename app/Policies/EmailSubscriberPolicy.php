<?php

namespace App\Policies;

use App\Models\EmailSubscriber;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmailSubscriberPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('email.read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmailSubscriber $emailSubscriber): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('email.read');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('email.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmailSubscriber $emailSubscriber): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('email.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmailSubscriber $emailSubscriber): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('email.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmailSubscriber $emailSubscriber): bool
    {
        return $user->hasRole(['Super Admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmailSubscriber $emailSubscriber): bool
    {
        return $user->hasRole(['Super Admin']);
    }
}
