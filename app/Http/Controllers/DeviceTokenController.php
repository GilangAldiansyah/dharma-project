<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token'    => 'required|string',
            'platform' => 'nullable|string|in:android,ios',
        ]);

        DeviceToken::saveToken(
            $request->user()->id,
            $validated['token'],
            $validated['platform'] ?? 'android'
        );

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        DeviceToken::removeToken($validated['token']);

        return response()->json(['success' => true]);
    }
}
