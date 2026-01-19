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
    public VolunteerNeed $need;

    public array $answers = [];

    public array $availability = [];

    public function mount(VolunteerNeed $need): void
    {
        abort_unless($need->is_active, 404);

        $this->need = $need->load([
            'applicationForm.fields' => fn ($q) => $q->where('is_active', true)->orderBy('sort'),
        ]);

        abort_if(! $this->need->applicationForm?->is_active, 404);

        $this->primeDefaults();
    }

    protected function primeDefaults(): void
    {
        foreach ($this->need->applicationForm->fields as $field) {
            $this->answers[$field->key] = $this->answers[$field->key] ?? ($field->type === 'checkbox_group' ? [] : '');
        }

        if ($this->need->applicationForm->use_availability) {
            $days = ['mon','tue','wed','thu','fri','sat','sun'];
            foreach ($days as $day) {
                $this->availability[$day]['am'] = $this->availability[$day]['am'] ?? false;
                $this->availability[$day]['pm'] = $this->availability[$day]['pm'] ?? false;
            }
        }
    }

    public function submit(): void
    {
        $user = auth()->user();
        abort_unless($user, 401);

        $data = $this->validate($this->buildRules());

        $answers = $data['answers'];

        if ($this->need->applicationForm->use_availability) {
            $answers['availability'] = $this->availability;
        }

        try {
            VolunteerApplication::create([
                'user_id' => $user->id,
                'volunteer_need_id' => $this->need->id,
                'status' => VolunteerApplication::STATUS_SUBMITTED,
                'answers' => $answers,
            ]);
        } catch (QueryException $e) {
            // MySQL duplicate entry => 1062
            $isDuplicate = (int) ($e->errorInfo[1] ?? 0) === 1062;

            if ($isDuplicate) {
                throw ValidationException::withMessages([
                    'duplicate' => 'You already submitted an application for this volunteer role.',
                ]);
            }

            throw $e;
        }

        $success = __('Thanks! Your volunteer application has been submitted.');
        session()->flash('flash.banner', $success);
        session()->flash('flash.bannerStyle', 'success');

        $this->reset(['answers', 'availability']);
        $this->primeDefaults();

        $this->dispatch('banner-message', style: 'success', message: $success);
    }

    protected function buildRules(): array
    {
        $rules = [
            'answers' => ['array'],
        ];

        foreach ($this->need->applicationForm->fields as $field) {
            $key = "answers.{$field->key}";
            $required = $field->is_required ? ['required'] : ['nullable'];

            $config = $field->config ?? [];
            $min = Arr::get($config, 'min');
            $max = Arr::get($config, 'max');
            $options = array_keys($field->options());

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

        if ($this->need->applicationForm->use_availability) {
            $days = ['mon','tue','wed','thu','fri','sat','sun'];
            foreach ($days as $day) {
                $rules["availability.{$day}.am"] = ['boolean'];
                $rules["availability.{$day}.pm"] = ['boolean'];
            }
        }

        return $rules;
    }

    public function render()
    {
        return view('livewire.volunteers.apply', [
            'need' => $this->need,
            'form' => $this->need->applicationForm,
            'fields' => $this->need->applicationForm->fields,
        ])->title('Volunteer Application');
    }
}
