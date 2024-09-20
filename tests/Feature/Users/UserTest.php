<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class UserTest extends TestCase
{
    /**
     * Test users database has correct columns
     */
    public function test_user_database_has_expected_columns()
    {
        $this->assertTrue(
          Schema::hasColumns('users', [
            'first_name', 'last_name', 'email', 'email_verified_at', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token', 'current_team_id', 'profile_photo_path', 'full_name', 'details', 'created_at'
        ]), 1);
    }
}
