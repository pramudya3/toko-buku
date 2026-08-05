<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AddressUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    /**
     * Show the user's address settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Address');
    }

    /**
     * Update the user's address.
     */
    public function update(AddressUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address updated.')]);

        return to_route('address.edit');
    }
}
