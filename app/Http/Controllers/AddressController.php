<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    /**
     * List addresses + show new-address form.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $addresses = $user->addresses()
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        $countries      = config('geo.countries');
        $usStates       = config('geo.us_states');
        $caStates       = config('geo.ca_states');
        $defaultCountry = $user->getPrimaryAddressOrFirstAttribute()?->country ?? 'US';

        return view('addresses.index', compact(
            'user',
            'addresses',
            'countries',
            'usStates',
            'caStates',
            'defaultCountry'
        ));
    }

    /**
     * Create a new address.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'label'       => ['nullable', 'string', 'max:255'],
            'first_name'  => ['required', 'string', 'max:255'],
            'last_name'   => ['required', 'string', 'max:255'],
            'company'     => ['nullable', 'string', 'max:255'],
            'line1'       => ['required', 'string', 'max:255'],
            'line2'       => ['nullable', 'string', 'max:255'],
            'city'        => ['required', 'string', 'max:255'],
            'state'       => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country'     => [
                'required',
                'string',
                'size:2',
                Rule::in(array_keys(config('geo.countries'))),
            ],
            'phone'      => ['nullable', 'string', 'max:50'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $validated['user_id']    = $user->id;
        $validated['is_primary'] = $request->boolean('is_primary');

        $address = Address::create($validated);

        if ($validated['is_primary']) {
            // Clear other primaries for this user
            $user->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_primary' => false]);
        }

        return redirect()
            ->route('addresses.index')
            ->with('status', 'address-created');
    }

    /**
     * Edit form.
     */
    public function edit(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $user      = $request->user();
        $countries = config('geo.countries');
        $usStates  = config('geo.us_states');
        $caStates  = config('geo.ca_states');

        return view('addresses.edit', compact(
            'user',
            'address',
            'countries',
            'usStates',
            'caStates'
        ));
    }

    /**
     * Update an address.
     */
    public function update(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'label'       => ['nullable', 'string', 'max:255'],
            'first_name'  => ['required', 'string', 'max:255'],
            'last_name'   => ['required', 'string', 'max:255'],
            'company'     => ['nullable', 'string', 'max:255'],
            'line1'       => ['required', 'string', 'max:255'],
            'line2'       => ['nullable', 'string', 'max:255'],
            'city'        => ['required', 'string', 'max:255'],
            'state'       => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country'     => [
                'required',
                'string',
                'size:2',
                Rule::in(array_keys(config('geo.countries'))),
            ],
            'phone'      => ['nullable', 'string', 'max:50'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $validated['is_primary'] = $request->boolean('is_primary');

        $address->update($validated);

        if ($validated['is_primary']) {
            $request->user()->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_primary' => false]);
        }

        return redirect()
            ->route('addresses.index')
            ->with('status', 'address-updated');
    }

    public function destroy(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $address->delete();

        return back()->with('status', 'address-deleted');
    }

    public function makePrimary(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $user = $request->user();

        $user->addresses()->update(['is_primary' => false]);
        $address->update(['is_primary' => true]);

        return back()->with('status', 'address-primary-updated');
    }
}
