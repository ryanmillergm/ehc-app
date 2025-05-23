<?php

namespace App\Policies;

use App\Models\PageTranslation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PageTranslationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('pages.read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PageTranslation $pageTranslation): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('pages.read');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('pages.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PageTranslation $pageTranslation): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('pages.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PageTranslation $pageTranslation): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('pages.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PageTranslation $pageTranslation): bool
    {
        return $user->hasRole(['Super Admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PageTranslation $pageTranslation): bool
    {
        return $user->hasRole(['Super Admin']);
    }
}
