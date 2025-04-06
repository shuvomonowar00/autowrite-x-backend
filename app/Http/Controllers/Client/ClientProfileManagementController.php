<?php

namespace App\Http\Controllers\Client;

use App\Models\Client\Client;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Http\Requests\Client\ClientRegisterRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\Client\VerificationEmail;
use Illuminate\Support\Facades\Password;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class ClientProfileManagementController extends Controller
{
    /**
     * Register a new client
     *
     * @param ClientRegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clientRegister(ClientRegisterRequest $request)
    {
        try {
            $validated_data = $request->validated();

            // Handle profile photo upload if present
            // $profilePhotoPath = null;
            // if ($request->hasFile('profile_photo')) {
            //     $profilePhotoPath = $request->file('profile_photo')
            //         ->store('profile-photos', 'public');
            // }

            // Create new client
            $client = Client::create([
                'first_name' => $validated_data['first_name'],
                'last_name' => $validated_data['last_name'],
                'profile_photo' => null,
                'username' => $validated_data['username'],
                'email' => $validated_data['email'],
                'password' => Hash::make($validated_data['password']),
                'verification_token' => Str::random(64),
                'verification_deadline' => Carbon::now()->addHours(24),
            ]);

            // Send verification email
            Mail::to($client->email)->send(new VerificationEmail($client));

            return response()->json([
                'message' => 'Registration successful. Please check your email to verify your account.',
                'client' => $client
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Login a client
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clientLogin(Request $request)
    {
        // $credentials = $request->validate([
        //     'login' => ['required', 'string'],
        //     'password' => ['required'],
        //     'remember_me' => ['nullable', 'boolean'],
        // ]);

        // // Log login request and credentials
        // Log::info('Login request: ' . $request->login);
        // Log::info('Password: ' . $request->password);
        // Log::info('Remember value: ' . $request->remember_me);

        // $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // // First check if user exists
        // $client = Client::where($loginType, $request->login)->first();

        // if (!$client) {
        //     return response()->json([
        //         'message' => 'Login failed',
        //         'errors' => [
        //             'login' => [$loginType === 'email' ?
        //                 'No account found with this email address.' :
        //                 'No account found with this username.']
        //         ]
        //     ], 401);
        // }

        // // Check if email is verified
        // if (!$client->email_verified_at) {
        //     return response()->json([
        //         'message' => 'Email not verified',
        //         'errors' => [
        //             'verification' => ['Please verify your email address to login.']
        //         ],
        //         'requires_verification' => true,
        //         'email' => $client->email
        //     ], 403);
        // }

        // // Now check password
        // if (!Hash::check($request->password, $client->password)) {
        //     return response()->json([
        //         'message' => 'Login failed',
        //         'errors' => [
        //             'password' => ['The password you entered is incorrect.']
        //         ]
        //     ], 401);
        // }

        // // Get remember value
        // $remember = $request->boolean('remember_me', false);

        // // Attempt login
        // Auth::guard('clients')->login($client, $remember);
        // $request->session()->regenerate();

        // // Apply the correct expiration right after login
        // if ($remember) {
        //     $expiration = 10080; // 7 days
        //     $request->session()->put('remember_expiration', $expiration);

        //     // Re-apply configuration after login
        //     Config::set('session.lifetime', $expiration);
        //     Config::set('sanctum.expiration', $expiration);
        //     Config::set('session.expire_on_close', false);
        // }

        // return response()->json([
        //     'message' => 'Logged in successfully',
        //     'client' => $client,
        //     'remember' => $remember,
        //     'remembered_session' => Auth::guard('clients')->viaRemember()
        // ], 200);


        try {
            $credentials = $request->validate([
                'login' => ['required', 'string'],
                'password' => ['required'],
                'remember_me' => ['nullable', 'boolean'],
            ]);

            // Log login request and credentials
            Log::info('Login request: ' . $request->login);
            Log::info('Remember value: ' . $request->remember_me);
            // Don't log passwords in production!
            // Log::info('Password: ' . $request->password);

            $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            Log::info('Login type: ' . $loginType);

            // First check if user exists
            $client = Client::where($loginType, $request->login)->first();

            if (!$client) {
                return response()->json([
                    'message' => 'Login failed',
                    'errors' => [
                        'login' => [$loginType === 'email' ?
                            'No account found with this email address.' :
                            'No account found with this username.']
                    ]
                ], 401);
            }

            // Check if email is verified
            if (!$client->email_verified_at) {
                return response()->json([
                    'requires_verification' => true,
                    'email' => $client->email
                ], 403);
            }

            // Now check password
            if (!Hash::check($request->password, $client->password)) {
                return response()->json([
                    'message' => 'Login failed',
                    'errors' => [
                        'password' => ['The password you entered is incorrect.']
                    ]
                ], 401);
            }

            // Get remember value
            $remember = $request->boolean('remember_me', false);
            Log::info('Using remember value: ' . ($remember ? 'true' : 'false'));

            // Attempt login
            Auth::guard('clients')->login($client, $remember);
            Log::info('Auth login completed successfully');

            $request->session()->regenerate();
            Log::info('Session regenerated successfully');

            // Session lifetime without remember
            Log::info('Configuration updated: session.lifetime=' . config('session.lifetime'));

            // Apply the correct expiration right after login
            if ($remember) {
                try {
                    $expiration = 10080; // 7 days
                    $request->session()->put('remember_expiration', $expiration);
                    Log::info('Added expiration to session: ' . $expiration);

                    // Re-apply configuration after login using helper instead of facade
                    config(['session.lifetime' => $expiration]);
                    config(['sanctum.expiration' => $expiration]);
                    config(['session.expire_on_close' => false]);

                    Log::info('Configuration updated: session.lifetime=' . config('session.lifetime'));
                } catch (Exception $e) {
                    Log::error('Error setting remember expiration: ' . $e->getMessage());
                    // Continue even if this part fails
                }
            }

            return response()->json([
                'message' => 'Logged in successfully',
                'client' => $client,
                'remember' => $remember
            ], 200);
        } catch (Exception $e) {
            Log::error('Login exception: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Login failed due to server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify a client's email
     */

    public function verifyEmail($token)
    {
        // $client = Client::where('verification_token', $token)
        //     ->where('verification_deadline', '>', Carbon::now())
        //     ->first();

        $client = Client::where('verification_token', $token)
            ->where(function ($query) {
                $query->where('verification_deadline', '>', Carbon::now())
                    ->orWhereNull('email_verified_at');
            })
            ->first();

        if (!$client) {
            return response()->json([
                'message' => 'Invalid or expired verification token',
                'errors' => [
                    'token' => ['The verification token provided is invalid or has expired.']
                ]
            ], 400);
        }

        $client->email_verified_at = Carbon::now();
        $client->verification_token = null;
        $client->verification_deadline = null;
        $client->save();

        return response()->json([
            'message' => 'Email verified successfully',
            'success' => true,
            'verified' => $client->email_verified_at
        ], 200);
    }

    /**
     * Summary of resendVerification
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $client = Client::where('email', $request->email)
            ->whereNull('email_verified_at')
            ->first();

        if (!$client) {
            return response()->json([
                'message' => 'No unverified account found with this email'
            ], 404);
        }

        // Generate new verification token and deadline
        $client->verification_token = Str::random(64);
        $client->verification_deadline = Carbon::now()->addHours(24);
        $client->save();

        // Resend verification email
        Mail::to($client->email)->send(new VerificationEmail($client));

        return response()->json([
            'message' => 'Verification email sent successfully'
        ]);
    }


    /**
     * Logout a client
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clientLogout(Request $request)
    {
        // Auth::guard('clients')->logout();

        // $request->session()->invalidate();
        // $request->session()->regenerateToken();

        // return response()->json([
        //     'message' => 'Logged out successfully'
        // ]);


        // $client = Auth::guard('clients')->user();

        // // Log what's happening
        // Log::info('Logout: User was remembered: ' . (Auth::guard('clients')->viaRemember() ? 'Yes' : 'No'));
        // Log::info('Logout: Session lifetime was: ' . config('session.lifetime'));

        // Auth::guard('clients')->logout();
        // $request->session()->invalidate();
        // $request->session()->regenerateToken();

        // // Delete remember cookie
        // $cookie = cookie()->forget('remember_clients_' . sha1('clients'));

        // return response()
        //     ->json(['message' => 'Logged out successfully'])
        //     ->withCookie($cookie);

        $client = Auth::guard('clients')->user();

        // Check if user exists
        if ($client) {
            // Get user ID for logging
            $clientId = $client->id;

            // Check for remember token directly in the database
            $hasRememberToken = !empty($client->remember_token);

            // Check for the remember cookie
            $hasCookie = $request->cookies->has('remember_clients_' . sha1('clients'));

            // Log all details
            Log::info("Logout: Client ID: {$clientId}");
            Log::info("Logout: viaRemember(): " . (Auth::guard('clients')->viaRemember() ? 'Yes' : 'No'));
            Log::info("Logout: Has remember token in DB: " . ($hasRememberToken ? 'Yes' : 'No'));
            Log::info("Logout: Has remember cookie: " . ($hasCookie ? 'Yes' : 'No'));
            Log::info("Logout: Session lifetime: " . config('session.lifetime'));
            Log::info("Logout: Session expire on close: " . (config('session.expire_on_close') ? 'Yes' : 'No'));
        } else {
            Log::info("Logout: No authenticated client found");
        }

        // Continue with logout...
        Auth::guard('clients')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $cookie = cookie()->forget('remember_clients_' . sha1('clients'));

        return response()
            ->json(['message' => 'Logged out successfully'])
            ->withCookie($cookie);
    }


    /**
     * Send a reset link to the given client email for reset password
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function sendResetPasswordLinkEmail(Request $request)
    {
        try {
            // Log::info('Password reset requested for email: ' . $request->email);

            $request->validate([
                'email' => 'required|email'
            ]);

            $client = Client::where('email', $request->email)->first();

            // Log::info('Client found status: ' . ($client ? 'Found' : 'Not found'));

            if (!$client) {
                return response()->json(['message' => 'No account found with this email address.'], 404);
            } elseif (!$client->email_verified_at) {
                return response()->json(['message' => 'Email not verified.'], 403);
            }

            // Log::info('Attempting to send password reset for client: ' . $client->id);

            $response = Password::broker('clients')->sendResetLink(
                $request->only('email')
            );

            // Log::info('Password broker response: ' . $response);

            return $response === Password::RESET_LINK_SENT
                ? response()->json(['message' => 'Reset link sent to your email.'], 200)
                : response()->json(['message' => 'Unable to send reset link. Please check your email address.'], 400);
        } catch (Exception $e) {
            // Log::error('Password reset error: ' . $e->getMessage());
            // Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'Unable to process password reset request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Verify if a password reset token is valid
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPasswordResetToken(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'token' => 'required|string'
            ]);

            Log::info('Password reset token verification requested for email: ' . $request->email);

            // Use the DB directly to check token existence and validity
            $tokenData = DB::table('client_password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            $tokenValid = false;

            if ($tokenData) {
                $tokenValid = Hash::check(
                    $request->token,
                    $tokenData->token
                ) && Carbon::parse($tokenData->created_at)
                    ->addMinutes(config('auth.passwords.clients.expire', 60))
                    ->isFuture();
            }

            Log::info('Token validation result for ' . $request->email . ': ' . ($tokenValid ? 'Valid' : 'Invalid'));

            return response()->json([
                'valid' => $tokenValid,
                'message' => $tokenValid ? 'Token is valid.' : 'Token is invalid or has expired.'
            ], $tokenValid ? 200 : 400);
        } catch (Exception $e) {
            Log::error('Token verification error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'Unable to verify reset token.',
                'valid' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Reset the client's password
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        try {
            // Validate the required input fields
            $validated = $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            Log::info('Password reset requested for email: ' . $request->email);
            Log::info('Password reset token: ' . $request->token);
            Log::info('Password reset new password: ' . $request->password);

            // Attempt to reset the password using the password broker for clients
            $response = Password::broker('clients')->reset(
                $validated,
                function ($client, $password) {
                    $client->password = Hash::make($password);
                    // $client->setRememberToken(Str::random(60));
                    $client->save();
                }
            );

            if ($response === Password::PASSWORD_RESET) {
                return response()->json(['message' => 'Password has been reset successfully.']);
            } else {
                // return response()->json(['message' => 'Password reset failed. Invalid token provided.'], 400);
                throw new Exception('Password reset failed. Invalid token provided.');
            }
        } catch (Exception $e) {
            Log::error('Password reset error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'Unable to reset password.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Summary of showProfile
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function showProfile()
    {
        // Get the authenticated client
        $client = Auth::guard('clients')->user();

        // Check if a client is logged in
        if (!$client) {
            return response()->json([
                'message' => 'Not authenticated'
            ], 401);
        }

        // Return the profile data
        return response()->json([
            'profile' => $client,
            'message' => 'Profile data retrieved successfully'
        ], 200);
    }

    /** */
    public function updateProfileGeneralInfo(Request $request)
    {
        // Get initial client ID
        $clientId = Auth::guard('clients')->id();

        if (!$clientId) {
            return response()->json([
                'message' => 'Not authenticated'
            ], 401);
        }

        // Log safely
        Log::info('Updating profile for client ID: ' . $clientId);

        // Validate the input data
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
        ]);

        try {
            // Re-query the client to ensure we have a fresh model instance
            $client = Client::find($clientId);

            if (!$client) {
                throw new Exception('Client not found');
            }

            // Update individual properties
            $client->first_name = $validated['first_name'];
            $client->last_name = $validated['last_name'];

            // Save the updated client data
            $client->save();

            // Return the updated profile data
            return response()->json([
                'profile' => $client,
                'message' => 'Profile data updated successfully'
            ], 200);
        } catch (Exception $e) {
            Log::error('Error updating profile: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Update client profile photo
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfilePhoto(Request $request)
    {
        // Get the authenticated client
        $client = Auth::guard('clients')->user();

        // Check if a client is logged in
        if (!$client) {
            return response()->json([
                'message' => 'Not authenticated'
            ], 401);
        }

        // Validate the input data
        $validated = $request->validate([
            'profile_photo' => 'required|image|max:2048',
        ]);

        try {
            // Handle profile photo upload
            if ($request->hasFile('profile_photo')) {
                // Log that we're updating the photo
                Log::info('Updating profile photo for client ID: ' . $client->id);

                // Delete old photo if exists (and it's not the default photo)
                if ($client->profile_photo && $client->profile_photo !== 'default.png') {
                    $oldPhotoPath = storage_path('app/public/' . $client->profile_photo);
                    Log::info('Checking for old photo at: ' . $oldPhotoPath);

                    if (file_exists($oldPhotoPath)) {
                        unlink($oldPhotoPath);
                        Log::info('Deleted old profile photo');
                    }
                }

                // Generate a custom filename with client ID to ensure uniqueness
                $file = $request->file('profile_photo');
                $extension = $file->getClientOriginalExtension();
                $filename = 'client_' . $client->id . '_' . time() . '.' . $extension;

                // Store with custom filename
                $profilePhotoPath = $file->storeAs('images/client/profile', $filename, 'public');
                Log::info('Stored new photo at: ' . $profilePhotoPath);

                // Construct the full URL for the profile photo
                $photoUrl = url('storage/' . $profilePhotoPath);
                Log::info('Profile photo url: ' . $photoUrl);

                // Update using DB to avoid possible save() issues
                DB::table('clients')
                    ->where('id', $client->id)
                    ->update([
                        'profile_photo' => $photoUrl,  // Store the path in DB
                        'updated_at' => now()
                    ]);

                // Refresh the client
                $client = Client::find($client->id);

                // Return success response
                return response()->json([
                    'profile' => $client,
                    'profile_photo_url' => $photoUrl,
                    'message' => 'Profile photo updated successfully'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'No profile photo provided',
                    'error' => 'The request must include a valid image file.'
                ], 400);
            }
        } catch (Exception $e) {
            Log::error('Error updating profile photo: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Failed to update profile photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
