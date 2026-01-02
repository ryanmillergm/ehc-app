<?php

namespace App\Livewire\Profile;

use Livewire\Component;
use App\Models\EmailList;
use Illuminate\Support\Str;
use App\Models\EmailSubscriber;
use Illuminate\Support\Facades\DB;
use App\Support\EmailCanonicalizer;

class EmailPreferencesForm extends Component
{
    /** @var array<int, array<string,mixed>> */
    public array $marketingLists = [];

    /** @var array<int, array<string,mixed>> */
    public array $transactionalLists = [];

    /** @var array<int,bool> listId => subscribed? (marketing only) */
    public array $subscriptions = [];

    /** Global marketing opt-out (EmailSubscriber.unsubscribed_at) */
    public bool $optOutAllMarketing = false;

    public string $email = '';

    public function mount(): void
    {
        // Tests expect a subscriber row (and defaults) to exist on first render.
        $this->loadState(ensureSubscriber: true);
    }

    public function save(): void
    {
        $this->normalizeSelections();

        $now = now();

        DB::transaction(function () use ($now) {
            $subscriber = $this->resolveSubscriber(createIfMissing: true);

            $marketingIds = collect($this->marketingLists)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $existingPivots = collect();
            if (! empty($marketingIds)) {
                $existingPivots = DB::table('email_list_subscriber')
                    ->where('email_subscriber_id', $subscriber->id)
                    ->whereIn('email_list_id', $marketingIds)
                    ->get(['email_list_id', 'subscribed_at', 'unsubscribed_at'])
                    ->keyBy('email_list_id');
            }

            if ($this->optOutAllMarketing) {
                // Global marketing unsubscribe
                $subscriber->forceFill([
                    'unsubscribed_at' => $now,
                    'subscribed_at' => $subscriber->subscribed_at ?? $now,
                ])->save();

                // Mark all marketing lists unsubscribed (create pivot rows if missing)
                foreach ($marketingIds as $listId) {
                    $subscribedAt = data_get($existingPivots->get($listId), 'subscribed_at') ?? $now;

                    $subscriber->lists()->syncWithoutDetaching([
                        $listId => [
                            'subscribed_at' => $subscribedAt,
                            'unsubscribed_at' => $now,
                        ],
                    ]);
                }

                return;
            }

            // Global marketing opt-in (but still respect per-list checkboxes)
            $subscriber->forceFill([
                'unsubscribed_at' => null,
                'subscribed_at' => $subscriber->subscribed_at ?? $now,
            ])->save();

            foreach ($this->marketingLists as $list) {
                $listId = (int) $list['id'];
                $optOutable = (bool) $list['is_opt_outable'];
                $selected = (bool) ($this->subscriptions[$listId] ?? false);

                $existingSubscribedAt = data_get($existingPivots->get($listId), 'subscribed_at');
                $subscribedAt = $existingSubscribedAt ?: $now;

                if (! $optOutable) {
                    // Locked marketing list (rare): always keep subscribed when not globally opted out
                    $subscriber->lists()->syncWithoutDetaching([
                        $listId => [
                            'subscribed_at' => $subscribedAt,
                            'unsubscribed_at' => null,
                        ],
                    ]);
                    continue;
                }

                if ($selected) {
                    // Subscribe / re-subscribe
                    $subscriber->lists()->syncWithoutDetaching([
                        $listId => [
                            'subscribed_at' => $subscribedAt,
                            'unsubscribed_at' => null,
                        ],
                    ]);
                } else {
                    // Unsubscribe (create pivot row if missing)
                    $subscriber->lists()->syncWithoutDetaching([
                        $listId => [
                            'subscribed_at' => $subscribedAt,
                            'unsubscribed_at' => $now,
                        ],
                    ]);
                }
            }
        });

        $this->dispatch('saved');
        $this->loadState(ensureSubscriber: true);
    }

    private function loadState(bool $ensureSubscriber = true): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $this->email = (string) $user->email;

        $marketing = EmailList::query()
            ->where('purpose', 'marketing')
            ->orderBy('label')
            ->get();

        $transactional = EmailList::query()
            ->where('purpose', 'transactional')
            ->orderBy('label')
            ->get();

        $this->marketingLists = $marketing->map(fn (EmailList $l) => [
            'id' => $l->id,
            'key' => $l->key,
            'label' => $l->label,
            'description' => $l->description,
            'is_opt_outable' => (bool) $l->is_opt_outable,
            'is_default' => (bool) $l->is_default,
        ])->all();

        $this->transactionalLists = $transactional->map(fn (EmailList $l) => [
            'id' => $l->id,
            'key' => $l->key,
            'label' => $l->label,
            'description' => $l->description,
            'is_opt_outable' => (bool) $l->is_opt_outable,
        ])->all();

        $subscriber = $this->resolveSubscriber(createIfMissing: $ensureSubscriber);

        if (! $subscriber) {
            $this->optOutAllMarketing = false;
            $this->subscriptions = [];

            foreach ($this->marketingLists as $list) {
                $this->subscriptions[(int) $list['id']] = (bool) $list['is_default'];
            }

            return;
        }

        // On first view, attach default marketing lists (but never override existing pivot rows).
        if (is_null($subscriber->unsubscribed_at)) {
            $this->attachDefaultMarketingListsIfMissing($subscriber);
        }

        $subscriber->refresh();
        $this->optOutAllMarketing = (bool) $subscriber->unsubscribed_at;

        $listModelsById = $subscriber->lists()->get()->keyBy('id');

        $this->subscriptions = [];

        foreach ($this->marketingLists as $list) {
            $id = (int) $list['id'];

            if ($this->optOutAllMarketing) {
                $this->subscriptions[$id] = false;
                continue;
            }

            if (! (bool) $list['is_opt_outable']) {
                $this->subscriptions[$id] = true;
                continue;
            }

            if (! $listModelsById->has($id)) {
                $this->subscriptions[$id] = (bool) $list['is_default'];
                continue;
            }

            $pivot = $listModelsById->get($id)->pivot;
            $this->subscriptions[$id] = is_null($pivot->unsubscribed_at);
        }
    }

    private function attachDefaultMarketingListsIfMissing(EmailSubscriber $subscriber): void
    {
        $now = now();

        $defaultIds = collect($this->marketingLists)
            ->filter(fn ($l) => (bool) ($l['is_default'] ?? false))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($defaultIds)) {
            return;
        }

        $existing = DB::table('email_list_subscriber')
            ->where('email_subscriber_id', $subscriber->id)
            ->whereIn('email_list_id', $defaultIds)
            ->pluck('email_list_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missing = array_values(array_diff($defaultIds, $existing));

        foreach ($missing as $listId) {
            $subscriber->lists()->syncWithoutDetaching([
                $listId => [
                    'subscribed_at' => $now,
                    'unsubscribed_at' => null,
                ],
            ]);
        }
    }

    private function resolveSubscriber(bool $createIfMissing = true): ?EmailSubscriber
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $canonicalEmail = EmailCanonicalizer::canonicalize((string) $user->email) ?? Str::lower(trim((string) $user->email));

        $subscriber = EmailSubscriber::query()
            ->where('user_id', $user->id)
            ->orWhere('email_canonical', $canonicalEmail)
            ->orWhere('email', $canonicalEmail)
            ->first();

        if (! $subscriber && ! $createIfMissing) {
            return null;
        }

        if (! $subscriber) {
            return EmailSubscriber::create([
                'email' => $canonicalEmail,
                'user_id' => $user->id,
                'unsubscribe_token' => Str::random(64),
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);
        }

        $update = [];

        if (! $subscriber->user_id) {
            $update['user_id'] = $user->id;
        }

        if (! $subscriber->unsubscribe_token) {
            $update['unsubscribe_token'] = Str::random(64);
        }

        if (! $subscriber->subscribed_at) {
            $update['subscribed_at'] = now();
        }

        if ($subscriber->email !== $canonicalEmail) {
            $update['email'] = $canonicalEmail;
        }

        if ($update) {
            $subscriber->update($update);
        }

        return $subscriber->refresh();
    }

    private function normalizeSelections(): void
    {
        foreach ($this->subscriptions as $id => $val) {
            $this->subscriptions[(int) $id] = (bool) $val;
        }
    }

    public function render()
    {
        return view('livewire.profile.email-preferences-form');
    }
}
