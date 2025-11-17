<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class DonationWidget extends Component
{
    /**
     * @var array<int, array{code: string, name: string}>
     */
    public array $countries;

    /**
     * @var array<string, array<int, array{code: string, name: string}>>
     */
    public array $states;

    public function __construct()
    {
        // config/geo.php -> ['US' => 'United States', ...]
        $this->countries = collect(config('geo.countries', []))
            ->map(fn (string $name, string $code) => [
                'code' => $code,
                'name' => $name,
            ])
            ->values()
            ->all();

        $usStates = collect(config('geo.us_states', []))
            ->map(fn (string $name, string $code) => [
                'code' => $code,
                'name' => $name,
            ])
            ->values()
            ->all();

        $caStates = collect(config('geo.ca_states', []))
            ->map(fn (string $name, string $code) => [
                'code' => $code,
                'name' => $name,
            ])
            ->values()
            ->all();

        // Map by country code so JS can look up by donor.address_country
        $this->states = [
            'US' => $usStates,
            'CA' => $caStates,
        ];

        // dd([
        //     'countries_count' => count($this->countries),
        //     'first_country'   => $this->countries[0] ?? null,
        // ]);
    }

    public function render(): View
    {
        return view('components.donation-widget');
    }
}
