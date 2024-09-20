<?php

namespace Tests\Setup;

use App\Application;
use App\Models\User;
use Facades\Tests\Setup\EventTestFactory;

class UserTestFactory
{
    protected $user;

    public function createUser()
    {
        $user = User::factory()->create();

        return $user;
    }
}
