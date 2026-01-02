<?php

namespace Tests\Feature\Models;

use App\Models\EmailSubscriber;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailSubscriberCanonicalTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    #[Test]
    public function it_sets_email_canonical_on_create(): void
    {
        $sub = EmailSubscriber::factory()->create([
            'email' => 'User.Name+tag@gmail.com',
        ]);

        $this->assertSame('username@gmail.com', $sub->email_canonical);
    }

    #[Test]
    public function it_updates_email_canonical_when_email_changes(): void
    {
        $sub = EmailSubscriber::factory()->create([
            'email' => 'first+tag@example.com',
        ]);

        $this->assertSame('first@example.com', $sub->email_canonical);

        $sub->update(['email' => 'Second+zzz@Example.com']);

        $sub->refresh();
        $this->assertSame('second@example.com', $sub->email_canonical);
    }

    #[Test]
    public function it_does_not_change_email_canonical_when_email_is_unchanged(): void
    {
        $sub = EmailSubscriber::factory()->create([
            'email' => 'first+tag@example.com',
        ]);

        $original = $sub->email_canonical;

        $sub->update(['first_name' => 'NewName']);

        $sub->refresh();
        $this->assertSame($original, $sub->email_canonical);
    }

    #[Test]
    public function it_enforces_unique_email_canonical(): void
    {
        EmailSubscriber::factory()->create([
            'email' => 'user.name+tag@gmail.com',
        ]);

        $this->expectException(QueryException::class);

        EmailSubscriber::factory()->create([
            'email' => 'username@gmail.com',
        ]);
    }
}
