<?php

namespace App\Http\Controllers\Article;

use App\Http\Requests\GenerateContentFormRequest;
use App\Models\LongArticle;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class LongArticleController extends Controller
{
    /**
     * Generate content from OpenAI API
     */
    public function generateArticleContent(GenerateContentFormRequest $request)
    {
        try {
            // Validate request
            $validated_data = $request->validated();

            // Log for debugging
            Log::info('Generate Content Validated Data:', [
                'validated_data' => $validated_data
            ]);

            /**
             * Simplified prompt based on title preference
             * Enhanced prompt based on title preference
             */
            $basePrompt = "Act as an expert content writer for a high-traffic website. Generate a comprehensive, engaging article that:
            - Uses natural, conversational tone
            - Includes well-organized sections
            - Provides actionable insights
            - Optimizes for SEO and readability
            - Includes practical examples and statistics";

            // Add FAQ requirement if numFAQs > 0
            if (
                $validated_data['numFAQs'] > 0
            ) {
                $basePrompt .= "\n- Include {$validated_data['numFAQs']} frequently asked questions with answers at the end";
            }

            // Add AI-generated title requirement if aiGeneratedTitle is true
            $prompt = $validated_data['aiGeneratedTitle']
                ? $basePrompt . "\n\nFormat the response as:\nTITLE: [Your Title]\nCONTENT: [Your Article Content]"
                : $basePrompt . "\n\nFormat the response as:\nCONTENT: [Your Article Content]";

            // Call OpenAI API to generate article content
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . config('openai.api_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $validated_data['gptVersion'],
                'messages' => [[
                    'role' => 'user',
                    'content' => $prompt . " Write in {$validated_data['language']} about {$validated_data['keywords']}."
                ]],
                'temperature' => config('openai.temperature'),
                'max_tokens' => $validated_data['wordCount'],
            ]);

            if (!$response->successful()) {
                throw new Exception('OpenAI API call failed: ' . $response->body());
            }

            // Extract title and content
            $content = $response->json()['choices'][0]['message']['content'];
            preg_match('/TITLE:\s*(.*?)\s*CONTENT:\s*(.*)/s', $content, $matches);

            // Check if AI-generated title is present
            $aiGeneratedTitleCheck = "No";
            if ($validated_data['aiGeneratedTitle']) {
                $aiGeneratedTitleCheck = "Yes";
            }

            // Get the authenticated client
            $client = Auth::guard('clients')->user();

            // Check if a client is authenticated
            if (!$client) {
                return response()->json([
                    'message' => 'Not authenticated',
                    'error' => 'You must be logged in to generate articles'
                ], 401);
            }

            // Get the client ID
            $clientId = $client->id;

            // Log the client ID for debugging
            Log::info('Generating article for client:', [
                'client_id' => $clientId,
                'username' => $client->username
            ]);

            // Save the article to the database
            $article = LongArticle::create([
                'client_id' => $clientId,
                'article_heading' => $matches[1] ?? "Enter an associated title",
                'article_content' => $matches[2] ?? $content,
                'article_type' => $validated_data['articleType'],
                'article_status' => "Success",
                'publish_status' => "Draft",
                'gpt_version' => $validated_data['gptVersion'],
                'ai_generated_title' => $aiGeneratedTitleCheck,
                'article_language' => $validated_data['language'],
                'faqs' => $validated_data['numFAQs'],
            ]);


            if ($article) {
                if (isset($validated_data['wordPressSites']) && count($validated_data['wordPressSites']) > 0) {
                    try {
                        $articleID = $article->id;

                        // Call ArticlePublishController
                        $publishController = new ArticlePublishController();
                        $results = [];
                        $successCount = 0;
                        $failureCount = 0;

                        foreach ($validated_data['wordPressSites'] as $site) {
                            try {
                                $response = $publishController->postToWordPress([
                                    'articleID' => $articleID,
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
                                }
                            } catch (Exception $e) {
                                $failureCount++;
                                $results[] = [
                                    'url' => $site['wpUrl'],
                                    'status' => 'failed',
                                    'error' => $e->getMessage()
                                ];
                            }
                        }

                        return response()->json([
                            'message' => 'Article generated and published successfully',
                            'article' => $article,
                            'publish_results' => [
                                'success_count' => $successCount,
                                'failure_count' => $failureCount,
                                'results' => $results
                            ]
                        ], 201);
                    } catch (Exception $e) {
                        Log::error('WordPress Publish Error:', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);

                        return response()->json([
                            'message' => 'Article generated but publishing failed',
                            'error' => $e->getMessage(),
                        ], 500);
                    }
                }
            }

            return response()->json([
                'message' => 'Article generated successfully',
                'article' => $article
            ], 201);
        } catch (Exception $e) {
            // Log error for debugging
            Log::error('Article Generation Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Error generating article',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display a paginated listing of the articles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexArticleContent()
    {
        // Get the authenticated client
        $client = Auth::guard('clients')->user();

        // Check if a client is logged in
        if (!$client) {
            return response()->json([
                'message' => 'Not authenticated'
            ], 401);
        }

        // Get client ID
        $clientId = $client->id;

        // Log for debugging
        Log::info('Fetching articles for client:', [
            'client_id' => $clientId,
            'username' => $client->username
        ]);

        // Fetch paginated articles from the database (6 per page)
        // Only get articles belonging to the current client
        $articles = LongArticle::where('client_id', $clientId)
            ->with(['articlePlatforms' => function ($query) {
                $query->join('post_platforms', 'post_platforms.id', '=', 'long_article_platforms.post_platform_id')
                    ->select('long_article_platforms.*', 'post_platforms.platform_name');
            }])
            ->orderBy('created_at', 'desc')  // Most recent first
            ->paginate(7);

        // Return the paginated articles as a JSON response
        return response()->json([
            'data' => $articles->items(),
            'current_page' => $articles->currentPage(),
            'last_page' => $articles->lastPage(),
            'total' => $articles->total(),
        ]);
    }


    /**
     * Display the specified article.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showSpecificArticleContent(Request $request, $id)
    {
        try {
            // Find article
            $article = LongArticle::with(['articlePlatforms' => function ($query) {
                $query->join('post_platforms', 'post_platforms.id', '=', 'long_article_platforms.post_platform_id')
                    ->select('long_article_platforms.*', 'post_platforms.platform_name');
            }])->findOrFail($id);

            // Return the article as a JSON response
            return response()->json($article);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error fetching article',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Update the article content
     */
    public function updateArticleContent(Request $request, $id)
    {
        try {
            // Find article
            $article = LongArticle::findOrFail($id);

            // Validate request
            $request->validate([
                'article_content' => 'required|string'
            ]);

            // Update content
            $article->update([
                'article_content' => $request->article_content
            ]);

            return response()->json([
                'message' => 'Article updated successfully',
                'article' => $article
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error updating article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified article from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyArticleContent($id)
    {
        try {
            // Find article
            $article = LongArticle::findOrFail($id);

            // Delete article
            $article->delete();

            return response()->json([
                'message' => 'Article deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error deleting article',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
