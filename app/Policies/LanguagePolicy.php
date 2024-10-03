<?php

namespace App\Policies;

use App\Models\Language;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LanguagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('languages.read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Language $language): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('languages.read');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('languages.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Language $language): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('languages.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Language $language): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('languages.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Language $language): bool
    {
        return $user->hasRole(['Super Admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Language $language): bool
    {
        return $user->hasRole(['Super Admin']);
    }
}
