<?php

namespace Tests\Feature\Children;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class ChildTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * Test children database has correct columns
     */
    public function test_children_database_has_expected_columns()
    {
        $this->assertTrue(
          Schema::hasColumns('children', [
            'country', 'city', 'description', 'created_at'
        ]), 1);
    }

    /**
     * A child can be created test
     */
    public function test_a_child_can_be_created(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $attributes = [
            'first_name'    => $this->faker->firstName(),
            'last_name'     => $this->faker->lastName(),
            'date_of_birth' => $this->faker->date('Y_m_d'),
            'country'       => $this->faker->country(),
            'city'          => $this->faker->city(),
            'description'   => $this->faker->paragraph(1),
            'team_id'       => $this->faker->numberBetween(0, 4),
        ];

        $response = $this->post('/children', $attributes);

        $this->assertDatabaseHas('children', $attributes);
    }
}
