<?php

namespace App\Livewire\Volunteers;

use App\Models\VolunteerApplication;
use App\Models\VolunteerNeed;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Apply extends Component
{
    public VolunteerNeed $need;

    public string $message = '';
    public array $interests = [];
    public array $availability = [];

    public function mount(VolunteerNeed $need): void
    {
        abort_unless($need->is_active, 404);

        $this->need = $need;
    }

    public function submit(): void
    {
        $user = auth()->user();

        $data = $this->validate([
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'interests' => ['array'],
            'interests.*' => ['string'],
            'availability' => ['array'],
            'availability.*' => ['string'],
        ]);

        try {
            VolunteerApplication::create([
                'user_id' => $user->id,
                'volunteer_need_id' => $this->need->id,
                'status' => VolunteerApplication::STATUS_SUBMITTED,
                'message' => $data['message'],
                'interests' => $data['interests'] ?: null,
                'availability' => $data['availability'] ?: null,
            ]);
        } catch (QueryException $e) {
            // Unique constraint violation = duplicate
            throw ValidationException::withMessages([
                'duplicate' => 'You already submitted an application for this volunteer role.',
            ]);
        }

        $success = __('Thanks! Your volunteer application has been submitted.');
        session()->flash('flash.banner', $success);
        session()->flash('flash.bannerStyle', 'success');

        $this->reset(['message', 'interests', 'availability']);

        // If you’re using Jetstream x-banner on this layout, this triggers it instantly
        $this->dispatch('banner-message', style: 'success', message: $success);
    }

    public function render()
    {
        return view('livewire.volunteers.apply')
            ->title('Volunteer Application');
    }
}
