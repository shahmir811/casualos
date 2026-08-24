<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpoPushToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    /**
     * Registers (or re-registers) an Expo push token for the authenticated
     * customer. Upserts by token rather than customer_id: if the same device
     * was previously registered to a different customer (a shared device,
     * logged out and back in as someone else), this reassigns it rather than
     * leaving two rows pointing at the same physical token.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token'    => 'required|string|max:255',
            'platform' => 'nullable|string|in:ios,android',
        ]);

        ExpoPushToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'customer_id' => $request->user()->id,
                'platform'    => $validated['platform'] ?? null,
            ]
        );

        return response()->json(['status' => 'registered']);
    }

    /**
     * Unregisters a token, e.g. on logout — so a shared/reused device stops
     * receiving this customer's notifications immediately rather than
     * waiting for the next customer's registration to overwrite it. Scoped
     * to the authenticated customer's own tokens only.
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:255',
        ]);

        ExpoPushToken::where('token', $validated['token'])
            ->where('customer_id', $request->user()->id)
            ->delete();

        return response()->json(['status' => 'unregistered']);
    }
}
