<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_an_authenticated_user_can_render_the_user_resource_page(): void
    {
        $this->signIn();

        $this->get(UserResource::getUrl('index'))->assertSuccessful();
    }
}
