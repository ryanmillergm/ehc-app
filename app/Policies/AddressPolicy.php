<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Address;

class AddressPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('addresses.read');
    }

    public function view(User $user, Address $address): bool
    {
        return $user->hasPermissionTo('addresses.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('addresses.create');
    }

    public function update(User $user, Address $address): bool
    {
        return $user->hasPermissionTo('addresses.update');
    }

    public function delete(User $user, Address $address): bool
    {
        return $user->hasPermissionTo('addresses.delete');
    }
}
