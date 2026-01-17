<?php

namespace Tests\Unit\Policies;

use App\Models\EmailSubscriber;
use App\Models\User;
use App\Policies\EmailSubscriberPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmailSubscriberPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['email.read', 'email.create', 'email.update', 'email.delete'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
    }

    #[Test]
    public function it_denies_access_without_permissions(): void
    {
        $user = User::factory()->create();
        $sub  = EmailSubscriber::factory()->create();

        $policy = new EmailSubscriberPolicy();

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->view($user, $sub));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, $sub));
        $this->assertFalse($policy->delete($user, $sub));
    }

    #[Test]
    public function it_allows_access_with_permissions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['email.read', 'email.create', 'email.update', 'email.delete']);

        $sub = EmailSubscriber::factory()->create();

        $policy = new EmailSubscriberPolicy();

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $sub));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $sub));
        $this->assertTrue($policy->delete($user, $sub));
    }
}
