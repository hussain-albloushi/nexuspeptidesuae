<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Persist the customer's billing/shipping details on their account so the
     * checkout form can be pre-filled next time. Orders keep their own JSON
     * snapshot, so changes here never affect past orders.
     */
    public function updateAddress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'saved_address' => ['required', 'array'],
            'saved_address.name' => ['required', 'string', 'max:255'],
            'saved_address.email' => ['required', 'email', 'max:255'],
            'saved_address.phone' => ['required', 'string', 'max:255'],
            'saved_address.address' => ['required', 'string', 'max:255'],
            'saved_address.zip' => ['nullable', 'string', 'max:255'],
            'saved_address.city' => ['required', 'string', 'max:255'],
            'saved_address.country' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update(['saved_address' => $data['saved_address']]);

        return response()->json(['saved_address' => $user->saved_address]);
    }
}
