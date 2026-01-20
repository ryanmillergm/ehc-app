<?php

namespace App\Livewire\Volunteers;

use App\Models\VolunteerApplication;
use App\Models\VolunteerNeed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Apply extends Component
{
    public ?VolunteerNeed $need = null;

    /**
     * Dynamic form answers (keyed by FormField::key)
     */
    public array $answers = [];

    /**
     * Optional weekly availability grid
     */
    public array $availability = [];

    /**
     * Optional interests selection
     */
    public array $interests = [];

    public bool $submitted = false;

    /**
     * "Form unavailable" state
     */
    public bool $unavailable = false;
    public string $unavailableTitle = 'Application not available';
    public string $unavailableMessage = 'This volunteer role is not accepting applications right now.';

    /**
     * Back-compat with older tests / callers that set $message directly.
     * Internally, we store it in answers['message'].
     */
    public string $message = '';

    public function mount(VolunteerNeed $need): void
    {
        if (! $need->is_active) {
            $this->need = $need;

            $this->setUnavailable(
                title: 'Volunteer role not available',
                message: 'This volunteer role is currently inactive.'
            );

            return;
        }

        $need->load([
            'applicationForm.fieldPlacements.field',
        ]);

        if (! $need->applicationForm || ! $need->applicationForm->is_active) {
            $this->need = $need;

            $this->setUnavailable(
                title: 'Application form not available',
                message: 'This volunteer role doesn’t have an application form configured yet.'
            );

            return;
        }

        $this->need = $need;

        $this->primeDefaults();
    }

    protected function setUnavailable(string $title, string $message): void
    {
        $this->unavailable = true;
        $this->unavailableTitle = $title;
        $this->unavailableMessage = $message;
    }

    protected function primeDefaults(): void
    {
        $form = $this->need->applicationForm;

        $placementsByKey = $form->placementMap(); // active-only map

        foreach ($form->fieldKeys() as $key) {
            $placement = $placementsByKey[$key] ?? null;
            if (! $placement || ! $placement->field) {
                continue;
            }

            $fieldType = $placement->field->type;

            $this->answers[$key] = $this->answers[$key]
                ?? ($fieldType === 'checkbox_group' ? [] : '');
        }

        // keep message alias in sync if form has a message field
        if (array_key_exists('message', $this->answers) && $this->message === '') {
            $this->message = (string) ($this->answers['message'] ?? '');
        }

        if ($form->use_availability) {
            $days = ['mon','tue','wed','thu','fri','sat','sun'];
            foreach ($days as $day) {
                $this->availability[$day]['am'] = (bool) ($this->availability[$day]['am'] ?? false);
                $this->availability[$day]['pm'] = (bool) ($this->availability[$day]['pm'] ?? false);
            }
        }
    }

    public function updatedMessage(string $value): void
    {
        $this->answers['message'] = $value;
    }

    public function submit(): void
    {
        if ($this->unavailable || ! $this->need?->applicationForm?->is_active) {
            throw ValidationException::withMessages([
                'form' => 'This application is not currently available.',
            ]);
        }

        $user = auth()->user();
        abort_unless($user, 401);

        // Ensure message alias is reflected in answers prior to validation
        if ($this->message !== '' || array_key_exists('message', $this->answers)) {
            $this->answers['message'] = $this->answers['message'] ?? $this->message;
            $this->message = (string) ($this->answers['message'] ?? '');
        }

        $data = $this->validate($this->buildRules());

        $answers = (array) ($data['answers'] ?? []);

        $availability = null;
        if ($this->need->applicationForm->use_availability) {
            $days = ['mon','tue','wed','thu','fri','sat','sun'];
            $normalized = [];
            foreach ($days as $day) {
                $normalized[$day] = [
                    'am' => (bool) data_get($this->availability, "{$day}.am", false),
                    'pm' => (bool) data_get($this->availability, "{$day}.pm", false),
                ];
            }
            $availability = $normalized;
        }

        $interests = $this->interests;
        if (empty($interests) && isset($answers['interests']) && is_array($answers['interests'])) {
            $interests = $answers['interests'];
        }

        if (array_key_exists('interests', $answers)) {
            unset($answers['interests']);
        }

        try {
            VolunteerApplication::create([
                'user_id' => $user->id,
                'volunteer_need_id' => $this->need->id,
                'status' => VolunteerApplication::STATUS_SUBMITTED,
                'answers' => $answers,
                'availability' => $availability,
                'interests' => empty($interests) ? null : array_values($interests),
            ]);
        } catch (QueryException $e) {
            $isDuplicate = (int) ($e->errorInfo[1] ?? 0) === 1062;

            if ($isDuplicate) {
                throw ValidationException::withMessages([
                    'duplicate' => 'You already submitted an application for this volunteer role.',
                ]);
            }

            throw $e;
        }

        $this->submitted = true;

        $success = __('Thanks! Your volunteer application has been submitted.');
        session()->flash('flash.banner', $success);
        session()->flash('flash.bannerStyle', 'success');

        $this->dispatch('banner-message', style: 'success', message: $success);
    }

    protected function buildRules(): array
    {
        $form = $this->need->applicationForm;

        $rules = [
            'answers' => ['array'],
        ];

        // Helpers: active-only placements, fast required lookup
        $placementsByKey = $form->placementMap(); // key => placement
        $requiredLookup = array_flip($form->requiredKeys());

        foreach ($placementsByKey as $fieldKey => $placement) {
            $field = $placement->field;

            $key = "answers.{$fieldKey}";
            $required = isset($requiredLookup[$fieldKey]) ? ['required'] : ['nullable'];

            $config = $placement->config();
            $min = Arr::get($config, 'min');
            $max = Arr::get($config, 'max');
            $options = array_keys($placement->options());

            switch ($field->type) {
                case 'text':
                case 'textarea':
                    $rules[$key] = array_merge($required, ['string']);
                    if ($min) { $rules[$key][] = "min:{$min}"; }
                    if ($max) { $rules[$key][] = "max:{$max}"; }
                    break;

                case 'select':
                case 'radio':
                    $rules[$key] = array_merge($required, ['string', Rule::in($options)]);
                    break;

                case 'checkbox_group':
                    $rules[$key] = array_merge($required, ['array']);
                    $rules["{$key}.*"] = ['string', Rule::in($options)];
                    break;

                case 'toggle':
                    $rules[$key] = array_merge($required, ['boolean']);
                    break;

                default:
                    $rules[$key] = $required;
                    break;
            }
        }

        if ($form->use_availability) {
            $days = ['mon','tue','wed','thu','fri','sat','sun'];
            foreach ($days as $day) {
                $rules["availability.{$day}.am"] = ['boolean'];
                $rules["availability.{$day}.pm"] = ['boolean'];
            }
        }

        $rules['interests'] = ['array'];
        $rules['interests.*'] = ['string'];

        return $rules;
    }

    public function render()
    {
        if ($this->unavailable) {
            return view('livewire.volunteers.apply-unavailable', [
                'need' => $this->need,
                'title' => $this->unavailableTitle,
                'message' => $this->unavailableMessage,
            ])->title('Volunteer Application');
        }

        return view('livewire.volunteers.apply', [
            'need'   => $this->need,
            'form'   => $this->need->applicationForm,
            'fields' => $this->need->applicationForm->activePlacements(),
        ])->title('Volunteer Application');
    }
}
