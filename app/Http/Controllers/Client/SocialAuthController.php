<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect the user to the authentication page for the specified provider.
     *
     * @param string $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToProvider($provider)
    {
        // Validate if provider is supported
        if (!in_array($provider, ['google', 'facebook'])) {
            return redirect()->back()->with('error', 'Unsupported provider');
        }

        try {
            // Use stateless approach - key change here
            return Socialite::driver($provider)->stateless()->redirect();
        } catch (Exception $e) {
            Log::error("Failed to generate {$provider} auth URL: " . $e->getMessage());
            return redirect()->back()->with('error', 'Authentication failed');
        }
    }

    /**
     * Handle the callback from social provider.
     *
     * @param Request $request
     * @param string $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleProviderCallback(Request $request, $provider)
    {
        // Validate if provider is supported
        if (!in_array($provider, ['google', 'facebook'])) {
            return redirect(env('FRONTEND_URL', 'http://127.0.0.1:5173') . '/client/login?error=unsupported_provider');
        }

        try {
            // Create a custom HTTP client with SSL verification disabled
            $httpClient = new \GuzzleHttp\Client(['verify' => false]);

            // Get the Socialite driver and set our custom HTTP client
            $driver = Socialite::driver($provider);

            // CRITICAL FIX: Use reflection to replace the HTTP client in the Socialite provider
            $providerClass = new \ReflectionClass($driver);

            // First try to find the guzzle property
            try {
                $guzzleProperty = $providerClass->getProperty('guzzle');
                $guzzleProperty->setAccessible(true);
                $guzzleProperty->setValue($driver, ['verify' => false]);
            } catch (Exception $e) {
                // If not found, try to access the http client via the getHttpClient method
                try {
                    $getHttpClientMethod = $providerClass->getMethod('getHttpClient');
                    $getHttpClientMethod->setAccessible(true);
                    $originalClient = $getHttpClientMethod->invoke($driver);

                    // Try to find setHttpClient method
                    $setHttpClientMethod = $providerClass->getMethod('setHttpClient');
                    $setHttpClientMethod->setAccessible(true);
                    $setHttpClientMethod->invoke($driver, $httpClient);
                } catch (Exception $e) {
                    // If all else fails, find and set all client-like properties
                    foreach ($providerClass->getProperties() as $property) {
                        $property->setAccessible(true);
                        $value = $property->getValue($driver);
                        if ($value instanceof \GuzzleHttp\Client) {
                            $property->setValue($driver, $httpClient);
                        }
                    }
                }
            }

            // Get the user with stateless OAuth approach
            $socialUser = $driver->stateless()->user();

            // Rest of your code remains the same
            // Log the user info for debugging
            Log::info("{$provider} user data: ", [
                'id' => $socialUser->getId(),
                'email' => $socialUser->getEmail(),
                'name' => $socialUser->getName(),
            ]);

            // Process user based on provider
            switch ($provider) {
                case 'google':
                    $client = $this->processGoogleUser($socialUser);
                    break;
                case 'facebook':
                    $client = $this->processFacebookUser($socialUser);
                    break;
                default:
                    throw new Exception("Unsupported provider: {$provider}");
            }

            // Login the user using Laravel's session authentication
            Auth::guard('clients')->login($client, true);

            $expiration = 10080; // 7 days in minutes
            // Set the session expiration time
            $request->session()->put('remember_expiration', $expiration);

            // Re-apply configuration after login
            config(['session.lifetime' => $expiration]);
            config(['sanctum.expiration' => $expiration]);
            config(['session.expire_on_close' => false]);

            // Store social provider info
            session(['social_auth_provider' => $provider]);

            // Regenerate session for security
            $request->session()->regenerate();

            // Frontend URL to redirect to dashboard directly
            $frontendUrl = env('FRONTEND_URL', 'http://127.0.0.1:5173/dashboard');

            // Redirect directly to dashboard
            return redirect("{$frontendUrl}/dashboard");
        } catch (Exception $e) {
            Log::error("{$provider} auth callback error: " . $e->getMessage());
            Log::error($e->getTraceAsString());

            $frontendUrl = env('FRONTEND_URL', 'http://127.0.0.1:5173');
            return redirect("{$frontendUrl}/client/login?error=authentication_failed&provider={$provider}");
        }
    }

    /**
     * Process Google user
     *
     * @param object $googleUser
     * @return Client
     */
    protected function processGoogleUser($googleUser)
    {
        // Check if user exists with this google_id
        $client = Client::where('google_id', $googleUser->getId())->first();

        // If not, check if user exists with same email
        if (!$client) {
            $client = Client::where('email', $googleUser->getEmail())->first();

            // If user with email exists, update their google_id
            if ($client) {
                $client->update([
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                    'google_avatar' => $googleUser->getAvatar() ?: null,
                ]);
            } else {
                // Create new user
                $names = $this->splitName($googleUser->getName());
                $username = $this->generateUniqueUsername($googleUser->getName());

                $client = Client::create([
                    'first_name' => $names['first_name'],
                    'last_name' => $names['last_name'],
                    'email' => $googleUser->getEmail(),
                    'username' => $username,
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make(Str::random(24)), // Random password
                    'email_verified_at' => now(), // Mark email as verified
                    'profile_photo' => 'google_avatar',
                    'google_avatar' => $googleUser->getAvatar() ?: null,
                ]);
            }
        } else {
            // Update existing user's avatar if needed
            if ($googleUser->getAvatar() && $client->google_avatar !== $googleUser->getAvatar()) {
                $client->update([
                    'google_avatar' => $googleUser->getAvatar()
                ]);
            }
        }

        return $client;
    }

    /**
     * Process Facebook user
     *
     * @param object $facebookUser
     * @return Client
     */
    protected function processFacebookUser($facebookUser)
    {
        // Check if user exists with this facebook_id
        $client = Client::where('facebook_id', $facebookUser->getId())->first();

        // If not, check if user exists with same email
        if (!$client) {
            // Facebook might not always provide email!
            $email = $facebookUser->getEmail() ?: "{$facebookUser->getId()}@facebook.com";

            $client = Client::where('email', $email)->first();

            // If user with email exists, update their facebook_id
            if ($client) {
                $client->update([
                    'facebook_id' => $facebookUser->getId(),
                    'email_verified_at' => now(),
                ]);
            } else {
                // Create new user
                $names = $this->splitName($facebookUser->getName());
                $username = $this->generateUniqueUsername($facebookUser->getName());

                $client = Client::create([
                    'first_name' => $names['first_name'],
                    'last_name' => $names['last_name'],
                    'email' => $email,
                    'username' => $username,
                    'facebook_id' => $facebookUser->getId(),
                    'password' => Hash::make(Str::random(24)), // Random password
                    'email_verified_at' => now(), // Mark email as verified
                    'profile_photo' => $facebookUser->getAvatar() ? 'facebook_avatar' : 'default.png',
                ]);

                // Store the Facebook avatar URL if available
                if ($facebookUser->getAvatar()) {
                    $client->facebook_avatar = $facebookUser->getAvatar();
                    $client->save();
                }
            }
        } else {
            // Update profile picture if available and changed
            if ($facebookUser->getAvatar() && (!isset($client->facebook_avatar) || $client->facebook_avatar !== $facebookUser->getAvatar())) {
                $client->update([
                    'facebook_avatar' => $facebookUser->getAvatar()
                ]);
            }
        }

        return $client;
    }

    /**
     * Split full name into first and last name
     *
     * @param string $name
     * @return array
     */
    protected function splitName($name)
    {
        $parts = explode(' ', $name, 2);
        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }

    /**
     * Generate a unique username based on the name
     * 
     * @param string $name
     * @return string
     */
    protected function generateUniqueUsername($name)
    {
        // Convert to lowercase and replace spaces with underscores
        $baseUsername = Str::slug($name, '_');

        // Check if username exists
        $username = $baseUsername;
        $counter = 1;

        while (Client::where('username', $username)->exists()) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
        }

        return $username;
    }
}
