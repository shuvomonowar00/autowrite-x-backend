<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class ClientSettingsController extends Controller
{
    /**
     * Checks if a username is available
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkUsernameAvailability(Request $request)
    {
        // Get the authenticated client
        $client = Auth::guard('clients')->user();

        // Validate the username
        $request->validate([
            'username' => [
                'required',
                'string',
                'max:30',
                'min:3',
                'regex:/^[a-zA-Z0-9_]+$/' // Only letters, numbers and underscores
            ],
        ]);

        $username = $request->input('username');

        // Check if username exists but ignore current client
        $exists = DB::table('clients')
            ->where('username', $username)
            // ->where('id', '!=', $client ? $client->id : 0)
            ->exists();

        return response()->json([
            'username' => $username,
            'available' => !$exists,
            'message' => $exists ? 'Username is already taken' : 'Username is available'
        ]);
    }


    /**
     * Update client username
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientUsername(Request $request)
    {
        // Get the authenticated client
        $client = Auth::guard('clients')->user();

        // Check if a client is logged in
        if (!$client) {
            return response()->json([
                'message' => 'Not authenticated'
            ], 401);
        }

        try {
            // Validate the username with proper unique check
            $validated = $request->validate([
                'username' => [
                    'required',
                    'string',
                    'max:30',
                    'min:3',
                    'regex:/^[a-zA-Z0-9_]+$/', // Only letters, numbers and underscores
                    Rule::unique('clients', 'username')->ignore($client->id)
                ],
            ]);

            // Update the username using DB query to avoid any save() method issues
            DB::table('clients')
                ->where('id', $client->id)
                ->update([
                    'username' => $validated['username'],
                    'updated_at' => now()
                ]);

            // Refresh client data
            $client = Auth::guard('clients')->user();

            // Return success response
            return response()->json([
                'message' => 'Username updated successfully',
                'username' => $validated['username'],
                'profile' => $client
            ], 200);
        } catch (ValidationException $e) {
            // Handle validation errors specifically
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            // Log and return other errors
            Log::error('Error updating username: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to update username',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Checks if an email address is available
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkEmailAvailability(Request $request)
    {
        // Get the authenticated client
        $client = Auth::guard('clients')->user();

        // Validate the email
        $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        $email = $request->input('email');

        // Check if email exists but ignore current client
        $exists = DB::table('clients')
            ->where('email', $email)
            ->where('id', '!=', $client ? $client->id : 0)
            ->exists();

        return response()->json([
            'email' => $email,
            'available' => !$exists,
            'message' => $exists ? 'Email address is already taken' : 'Email address is available'
        ]);
    }

    /**
     * Update client email address with password verification
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEmailAddress(Request $request)
    {
        // Get the authenticated client
        $client = Auth::guard('clients')->user();

        // Check if a client is logged in
        if (!$client) {
            return response()->json([
                'message' => 'Not authenticated'
            ], 401);
        }

        try {
            // Validate the email and password
            $validated = $request->validate([
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('clients')->ignore($client->id),
                ],
                'password' => [
                    'required',
                    'string',
                ],
            ]);

            // Verify the current password
            if (!Hash::check($validated['password'], $client->password)) {
                return response()->json([
                    'message' => 'Current password is incorrect',
                    'errors' => [
                        'password' => ['The provided password does not match our records.']
                    ]
                ], 422);
            }

            // Update the email address using DB query
            DB::table('clients')
                ->where('id', $client->id)
                ->update([
                    'email' => $validated['email'],
                    'updated_at' => now()
                ]);

            // Refresh client data
            $client = Auth::guard('clients')->user();

            // Log the email update
            Log::info('Email address updated for client ID: ' . $client->id);

            // Return success response
            return response()->json([
                'message' => 'Email address updated successfully',
                'email' => $validated['email'],
                'profile' => $client
            ], 200);
        } catch (ValidationException $e) {
            // Handle validation errors specifically
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            // Log and return other errors
            Log::error('Error updating email: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to update email address',
                'errors' => $e->getMessage()
            ], 500);
        }
    }


    public function updatePassword(Request $request)
    {
        // Get the authenticated client
        $client = Auth::guard('clients')->user();

        // Check if a client is logged in
        if (!$client) {
            return response()->json([
                'message' => 'Not authenticated'
            ], 401);
        }

        try {
            // Validate the password
            $validated = $request->validate([
                'current_password' => [
                    'required',
                    'string',
                ],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],
            ]);

            // Verify the current password
            if (!Hash::check($validated['current_password'], $client->password)) {
                return response()->json([
                    'message' => 'Current password is incorrect',
                    'errors' => [
                        'current_password' => ['The provided password does not match our records.']
                    ]
                ], 422);
            }

            // Update the password using DB query
            DB::table('clients')
                ->where('id', $client->id)
                ->update([
                    'password' => Hash::make($validated['password']),
                    'updated_at' => now()
                ]);

            // Refresh client data
            $client = Auth::guard('clients')->user();

            // Log the password update
            Log::info('Password updated for client ID: ' . $client->id);

            // Return success response
            return response()->json([
                'message' => 'Password updated successfully',
                'profile' => $client
            ], 200);
        } catch (ValidationException $e) {
            // Handle validation errors specifically
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            // Log and return other errors
            Log::error('Error updating password: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to update password',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
