<?php

namespace App\Livewire;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class EmailPreferencesPublicForm extends Component
{
    public string $token;

    public string $email = '';

    /** @var array<int, array<string,mixed>> */
    public array $marketingLists = [];

    /** @var array<int, array<string,mixed>> */
    public array $transactionalLists = [];

    /** @var array<int,bool> listId => subscribed? (marketing only) */
    public array $subscriptions = [];

    /** Global marketing opt-out (EmailSubscriber.unsubscribed_at) */
    public bool $optOutAllMarketing = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->loadState();
    }

    public function save(): void
    {
        $this->normalizeSelections();

        $now = now();

        DB::transaction(function () use ($now) {
            $subscriber = $this->resolveSubscriber();

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
                // Global unsubscribe
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

            // Global opt-in
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
                    // Locked marketing list (rare): always keep subscribed
                    $subscriber->lists()->syncWithoutDetaching([
                        $listId => [
                            'subscribed_at' => $subscribedAt,
                            'unsubscribed_at' => null,
                        ],
                    ]);
                    continue;
                }

                if ($selected) {
                    $subscriber->lists()->syncWithoutDetaching([
                        $listId => [
                            'subscribed_at' => $subscribedAt,
                            'unsubscribed_at' => null,
                        ],
                    ]);
                } else {
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
        $this->loadState(); // refresh from DB
    }

    private function loadState(): void
    {
        $subscriber = $this->resolveSubscriber();

        $this->email = (string) $subscriber->email;

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

        // Attach defaults if subscriber is not globally unsubscribed (same behavior as profile)
        if (is_null($subscriber->unsubscribed_at)) {
            $this->attachDefaultMarketingListsIfMissing($subscriber);
            $subscriber->refresh();
        }

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

    private function resolveSubscriber(): EmailSubscriber
    {
        return EmailSubscriber::query()
            ->where('unsubscribe_token', $this->token)
            ->firstOrFail();
    }

    private function normalizeSelections(): void
    {
        foreach ($this->subscriptions as $id => $val) {
            $this->subscriptions[(int) $id] = (bool) $val;
        }
    }

    public function render()
    {
        return view('livewire.email-preferences-public-form');
    }
}
