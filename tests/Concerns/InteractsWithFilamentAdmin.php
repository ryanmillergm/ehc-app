<?php

namespace Tests\Concerns;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

trait InteractsWithFilamentAdmin
{
    protected function loginAsSuperAdmin(): User
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Ensure role exists
        Role::findOrCreate('Super Admin');

        $user->assignRole('Super Admin');

        $this->actingAs($user);

        if (class_exists(Filament::class)) {
            Filament::setCurrentPanel(Filament::getPanel('admin'));
        }

        return $user;
    }
}
