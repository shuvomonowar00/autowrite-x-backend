<?php

namespace App\Http\Controllers\Article;

use App\Http\Requests\WordPressMultipleArticlesPostFormRequest;
use App\Http\Requests\WordPressSiteVerificationFormRequest;
use App\Http\Requests\WordPressSiteVerificationFormRequestWithArticle;
use App\Http\Requests\WordPressSiteVerificationFormRequestWithoutArticle;
use App\Models\LongArticle;
use App\Models\PostPlatform;
use App\Models\LongArticlePlatform;
use Illuminate\Support\Facades\Http;
use Parsedown;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ArticlePublishController extends Controller
{
    /**
     * Post the multiple articles content to WordPress
     */
    function postMultipleArticlesToWordPress(WordPressMultipleArticlesPostFormRequest $request)
    {
        try {
            $validated_data = $request->validated();

            // Log::info('WordPress Multiple Post Validated Data:', [
            //     'validated_data' => $validated_data['wordPressSites']
            // ]);

            $successCount = 0;
            $failureCount = 0;
            $results = [];

            foreach ($validated_data['wordPressSites'] as $site) {
                try {
                    $response = $this->postToWordPress([
                        'articleID' => $validated_data['articleID'],
                        'wpUrl' => $site['wpUrl'],
                        'wpUsername' => $site['wpUsername'],
                        'wpPassword' => $site['wpPassword']
                    ]);

                    if ($response->getStatusCode() === 200) {
                        $successCount++;
                        $results[] = [
                            'url' => $site['wpUrl'],
                            'status' => 'success'
                        ];
                    } else {
                        $failureCount++;
                        $results[] = [
                            'url' => $site['wpUrl'],
                            'status' => 'failed',
                            'error' => json_decode($response->getContent(), true)['error']
                        ];
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    $results[] = [
                        'url' => $site['wpUrl'],
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'message' => 'Multiple posting completed',
                'total_sites' => count($validated_data['wordPressSites']),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error processing multiple posts',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Post the individual article content to WordPress
     */
    function postToWordPress(array $validated_data)
    {
        try {
            // $validated_data = $request->validated();
            $longArticle = LongArticle::findOrFail($validated_data['articleID']);

            $title = $longArticle->article_heading;
            $content = $longArticle->article_content;

            $wpUrl = $validated_data['wpUrl'] . '/wp-json/wp/v2/posts';
            $wpUsername = $validated_data['wpUsername'];
            $wpPassword = $validated_data['wpPassword'];
            $auth = base64_encode("{$wpUsername}:{$wpPassword}");

            // Convert Markdown to HTML
            $parsedown = new Parsedown();
            $parsedown->setBreaksEnabled(true);
            $htmlContent = $parsedown->text($content);
            $htmlContent = str_replace("\n", "", $htmlContent);

            $data = [
                'title' => $title,
                'content' => $htmlContent,
                'status' => 'publish',
            ];

            // Post to WordPress
            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => "Basic {$auth}",
                    'Content-Type' => 'application/json',
                ])->post($wpUrl, $data);

            if ($response->successful()) {
                // Get WordPress platform
                $wordPressPlatform = PostPlatform::firstOrCreate([
                    'platform_name' => 'WordPress'
                ]);

                // Store in pivot table
                LongArticlePlatform::create([
                    'long_article_id' => $longArticle->id,
                    'post_platform_id' => $wordPressPlatform->id,
                    'post_url' => $validated_data['wpUrl']
                ]);

                // try {
                //     // Log data before insertion
                //     Log::info('Attempting to create LongArticlePlatform:', [
                //         'article_id' => $longArticle->id,
                //         'platform_id' => $wordPressPlatform->id,
                //         'post_url' => $validated_data['wpUrl']
                //     ]);

                //     $platform = LongArticlePlatform::create([
                //         'long_article_id' => $longArticle->id,
                //         'post_platform_id' => $wordPressPlatform->id,
                //         'post_url' => $validated_data['wpUrl']
                //     ]);

                //     Log::info('LongArticlePlatform created:', [
                //         'platform' => $platform
                //     ]);
                // } catch (\Exception $e) {
                //     Log::error('Failed to create LongArticlePlatform:', [
                //         'error' => $e->getMessage(),
                //         'trace' => $e->getTraceAsString()
                //     ]);
                //     throw $e;
                // }

                // Update article status
                $longArticle->update([
                    'publish_status' => 'Success'
                ]);

                return response()->json([
                    'message' => 'Article posted to WordPress successfully'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Failed to post to WordPress',
                    'error' => $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to post to WordPress',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Verify the WordPress site credentials
     */
    function verifyWordPressSite(WordPressSiteVerificationFormRequestWithoutArticle $request)
    {
        try {
            $validated_data = $request->validated();
            $wpUrl = $validated_data['wpUrl'];
            $wpUsername = $validated_data['wpUsername'];
            $wpPassword = $validated_data['wpPassword'];
            $auth = base64_encode("{$wpUsername}:{$wpPassword}");

            // Test site accessibility
            $testResponse = Http::withoutVerifying()
                ->timeout(30)
                ->get($wpUrl . '/wp-json');

            if (!$testResponse->successful()) {
                throw new \Exception('WordPress site not accessible');
            }

            // Test authentication and post permission
            $authResponse = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => "Basic {$auth}",
                    'Content-Type' => 'application/json',
                ])->get($wpUrl . '/wp-json/wp/v2/users/me');

            if (!$authResponse->successful()) {
                $statusCode = $authResponse->status();
                $responseBody = json_decode($authResponse->body(), true);

                if ($statusCode === 401) {
                    if (str_contains($responseBody['message'] ?? '', 'username')) {
                        return response()->json([
                            'message' => 'Authentication failed',
                            'error' => 'Invalid WordPress username'
                        ], 401);
                    } elseif (str_contains($responseBody['message'] ?? '', 'password')) {
                        return response()->json([
                            'message' => 'Authentication failed',
                            'error' => 'Invalid WordPress password'
                        ], 401);
                    } else {
                        return response()->json([
                            'message' => 'Authentication failed',
                            'error' => 'Both username and password are incorrect'
                        ], 401);
                    }
                }
            }

            return response()->json([
                'success' => 'Credentials verified successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error verifying WordPress site',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Show the article content in HTML format
     */

    // function showArticleHTMLFormat($id)
    // {
    //     try {
    //         $article = LongArticle::findOrFail($id);

    //         $title = $article->article_heading;
    //         $content = $article->article_content;

    //         // Convert Markdown to HTML with proper line breaks
    //         $parsedown = new Parsedown();
    //         $parsedown->setBreaksEnabled(true); // Enable line breaks

    //         $htmlContent = $parsedown->text($content);
    //         $htmlContent = str_replace("\n", "", $htmlContent); // Remove line breaks

    //         return response()->json([
    //             'title' => $title,
    //             'html_content' => $htmlContent,
    //             'message' => 'Article converted successfully'
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Error converting article',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
}
