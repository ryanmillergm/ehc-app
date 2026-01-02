<?php

namespace App\Policies;

use App\Models\EmailCampaign;
use App\Models\User;

class EmailCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('email.read');
    }

    public function view(User $user, EmailCampaign $emailCampaign): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('email.read');
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('email.create');
    }

    public function update(User $user, EmailCampaign $emailCampaign): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('email.update');
    }

    public function delete(User $user, EmailCampaign $emailCampaign): bool
    {
        return $user->hasRole(['Super Admin']) || $user->hasPermissionTo('email.delete');
    }

    public function restore(User $user, EmailCampaign $emailCampaign): bool
    {
        return $user->hasRole(['Super Admin']);
    }

    public function forceDelete(User $user, EmailCampaign $emailCampaign): bool
    {
        return $user->hasRole(['Super Admin']);
    }
}
