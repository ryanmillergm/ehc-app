<?php

namespace App\Policies;

use App\Models\ApplicationForm;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApplicationFormPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('applications.read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ApplicationForm $applicationForm): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('applications.read');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('applications.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ApplicationForm $applicationForm): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('applications.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ApplicationForm $applicationForm): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('applications.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ApplicationForm $applicationForm): bool
    {
        return $user->hasRole(['Super Admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ApplicationForm $applicationForm): bool
    {
        return $user->hasRole(['Super Admin']);
    }
}
