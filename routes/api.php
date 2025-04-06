<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Article\LongArticleController;
use App\Http\Controllers\Article\ArticlePublishController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\ClientProfileManagementController;
use App\Http\Controllers\Client\ClientSettingsController;
use App\Http\Controllers\Client\SocialAuthController;

/**
 * Client user all routes
 */

// Client auth routes (public)
Route::post('/client/register', [ClientProfileManagementController::class, 'clientRegister']);
Route::post('/client/login', [ClientProfileManagementController::class, 'clientLogin']);
// Social authentication routes with web middleware
Route::middleware(['web'])->group(function () {
    Route::get('/client/login/{provider}', [SocialAuthController::class, 'redirectToProvider']);
    Route::get('/client/login/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback']);
});
// Route::get('/client/login/{provider}', [SocialAuthController::class, 'redirectToProvider']);
// Route::get('/client/login/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback']);
//Route::post('/client/logout', [ClientProfileManagementController::class, 'clientLogout']);
Route::middleware(['auth:sanctum,clients', 'remember.duration'])->group(function () {
    Route::post('/client/logout', [ClientProfileManagementController::class, 'clientLogout']);
});

/**
 * Define a route to fetch all long articles
 */
Route::middleware(['auth:sanctum,clients'])->group(function () {
    // Check if the client is authenticated route
    Route::get('/client/check-auth', function () {
        return response()->json([
            'authenticated' => true,
            'user' => auth('clients')->user()
        ]);
    });
    // Get the client dashboard statistics
    Route::get('/client/dashboard/stats', [ClientDashboardController::class, 'getDashboardStats']);
    Route::get('/client/dashboard/platform-stats', [ClientDashboardController::class, 'getPlatformStats']);
    Route::get('/client/dashboard/article-metrics', [ClientDashboardController::class, 'getArticleMetrics']);
    // Show the client profile
    Route::get('/client/profile/show', [ClientProfileManagementController::class, 'showProfile']);
    // Update the client profile general information
    Route::put('/client/profile/update/profile-info', [ClientProfileManagementController::class, 'updateProfileGeneralInfo']);
    // Update the client profile photo
    Route::post('/client/profile/update/profile-photo', [ClientProfileManagementController::class, 'updateProfilePhoto']);
    // Check the client username availability 
    Route::post('/client/settings/check-username', [ClientSettingsController::class, 'checkUsernameAvailability']);
    // Update the client username
    Route::put('/client/settings/update-username', [ClientSettingsController::class, 'updateClientUsername']);
    // Check the client email address availability
    Route::post('/client/settings/check-email', [ClientSettingsController::class, 'checkEmailAvailability']);
    // Update the client email address
    Route::put('/client/settings/update-email', [ClientSettingsController::class, 'updateEmailAddress']);
    // Update the client password
    Route::put('/client/settings/update-password', [ClientSettingsController::class, 'updatePassword']);
    // Article management routes
    Route::prefix('/contents/articles')->group(function () {
        // Generate content from OpenAI API
        Route::post('/generate', [LongArticleController::class, 'generateArticleContent']);
        // Show the paginated articles
        Route::get('/show', [LongArticleController::class, 'indexArticleContent']);
        // Show the specific article content
        Route::get('/show/{id}', [LongArticleController::class, 'showSpecificArticleContent']);
        // Update the article content
        Route::put('/update/{id}', [LongArticleController::class, 'updateArticleContent']);
        // Delete the article content
        Route::delete('/delete/{id}', [LongArticleController::class, 'destroyArticleContent']);
        // Publish the article content to WordPress
        Route::post('/publish', [ArticlePublishController::class, 'postMultipleArticlesToWordPress']);
        // Verify the WordPress site
        Route::post('/verify-wordpress-site', [ArticlePublishController::class, 'verifyWordPressSite']);
    });
});

// Verify email routes
Route::post('/client/verify-email/{token}', [ClientProfileManagementController::class, 'verifyEmail']);
Route::post('/client/resend-verification', [ClientProfileManagementController::class, 'resendVerification']);

// Password reset routes
Route::post('/client/forgot-password', [ClientProfileManagementController::class, 'sendResetPasswordLinkEmail']);
Route::post('/client/verify-password-reset-token', [ClientProfileManagementController::class, 'verifyPasswordResetToken']);
Route::post('/client/reset-password', [ClientProfileManagementController::class, 'resetPassword']);
