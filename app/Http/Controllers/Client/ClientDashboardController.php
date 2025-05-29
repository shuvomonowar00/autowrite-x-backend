<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LongArticle;
use App\Models\PostPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientDashboardController extends Controller
{
    /**
     * Get dashboard statistics for the logged-in client
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStats()
    {
        try {
            // Get the authenticated client
            $client = Auth::guard('clients')->user();

            // Check if a client is logged in
            if (!$client) {
                return response()->json([
                    'message' => 'Not authenticated'
                ], 401);
            }

            $clientId = $client->id;

            // Total articles count
            $totalArticles = LongArticle::where('client_id', $clientId)->count();

            // Published articles count
            $publishedArticles = LongArticle::where('client_id', $clientId)
                ->where('publish_status', 'Published')
                ->count();

            // Calculate total words across all articles
            $articles = LongArticle::where('client_id', $clientId)->get();
            $totalWords = 0;

            foreach ($articles as $article) {
                // Count words in the article content
                $totalWords += str_word_count(strip_tags($article->article_content));
            }

            // Get 10 most recent articles
            $recentArticles = LongArticle::where('client_id', $clientId)
                ->orderBy('created_at', 'desc')
                ->take(9)
                ->get();

            // Get platform statistics
            // $platformStats = DB::table('post_platforms')
            //     ->leftJoin('long_article_platforms', 'post_platforms.id', '=', 'long_article_platforms.post_platform_id')
            //     ->leftJoin('long_articles', function ($join) use ($clientId) {
            //         $join->on('long_articles.id', '=', 'long_article_platforms.long_article_id')
            //             ->where('long_articles.client_id', '=', $clientId);
            //     })
            //     ->select('post_platforms.id', 'post_platforms.platform_name', DB::raw('COUNT(DISTINCT long_article_platforms.long_article_id) as article_count'))
            //     ->groupBy('post_platforms.id', 'post_platforms.platform_name')
            //     ->get();

            // Improved query to get platform statistics
            $platformStats = DB::table('post_platforms')
                ->leftJoin('long_article_platforms', 'post_platforms.id', '=', 'long_article_platforms.post_platform_id')
                ->leftJoin('long_articles', function ($join) use ($clientId) {
                    $join->on('long_article_platforms.long_article_id', '=', 'long_articles.id')
                        ->where('long_articles.client_id', '=', $clientId);
                })
                ->select(
                    'post_platforms.id',
                    'post_platforms.platform_name',
                    DB::raw('COUNT(DISTINCT CASE WHEN long_articles.id IS NOT NULL THEN long_article_platforms.long_article_id ELSE NULL END) as article_count')
                )
                ->groupBy('post_platforms.id', 'post_platforms.platform_name')
                ->get();

            // Return all statistics
            return response()->json([
                'stats' => [
                    'total_articles' => $totalArticles,
                    'published_articles' => $publishedArticles,
                    'total_words' => $totalWords,
                    'publication_rate' => $totalArticles > 0 ? round(($publishedArticles / $totalArticles) * 100, 1) : 0
                ],
                'recent_articles' => $recentArticles,
                'platform_stats' => $platformStats
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching dashboard stats: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to load dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get more detailed platform statistics for charts
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPlatformStats()
    {
        try {
            // Get the authenticated client
            $client = Auth::guard('clients')->user();

            // Check if a client is logged in
            if (!$client) {
                return response()->json([
                    'message' => 'Not authenticated'
                ], 401);
            }

            $clientId = $client->id;

            // Get more detailed platform statistics for visualization
            $platformStats = PostPlatform::select('post_platforms.id', 'post_platforms.platform_name')
                ->withCount(['articles' => function ($query) use ($clientId) {
                    $query->where('client_id', $clientId);
                }])
                ->get();

            // Get monthly article counts for the last 6 months
            $sixMonthsAgo = now()->subMonths(6);

            $monthlyStats = LongArticle::where('client_id', $clientId)
                ->where('created_at', '>=', $sixMonthsAgo)
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as article_count')
                )
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get();

            // Format for easy chart rendering
            $formattedMonthlyStats = [];
            foreach ($monthlyStats as $stat) {
                $monthName = date('F', mktime(0, 0, 0, $stat->month, 1));
                $formattedMonthlyStats[] = [
                    'month' => $monthName . ' ' . $stat->year,
                    'article_count' => $stat->article_count
                ];
            }

            return response()->json([
                'platform_distribution' => $platformStats,
                'monthly_articles' => $formattedMonthlyStats
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching platform stats: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to load platform statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get article quality metrics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getArticleMetrics()
    {
        try {
            // Get the authenticated client
            $client = Auth::guard('clients')->user();

            // Check if a client is logged in
            if (!$client) {
                return response()->json([
                    'message' => 'Not authenticated'
                ], 401);
            }

            $clientId = $client->id;

            // Get articles with their length information
            $articles = LongArticle::where('client_id', $clientId)->get();

            $articleMetrics = [];
            $totalWords = 0;
            $articlesWithFaqs = 0;

            foreach ($articles as $article) {
                $wordCount = str_word_count(strip_tags($article->article_content));
                $totalWords += $wordCount;

                if (!empty($article->faqs) && $article->faqs > 0) {
                    $articlesWithFaqs++;
                }

                $articleMetrics[] = [
                    'id' => $article->id,
                    'title' => $article->article_heading,
                    'word_count' => $wordCount,
                    'has_faqs' => !empty($article->faqs) && $article->faqs > 0,
                    'type' => $article->article_type,
                    'created_at' => $article->created_at
                ];
            }

            // Calculate average word count
            $avgWordCount = $articles->count() > 0 ? round($totalWords / $articles->count()) : 0;

            // Calculate percentage of articles with FAQs
            $faqPercentage = $articles->count() > 0 ? round(($articlesWithFaqs / $articles->count()) * 100, 1) : 0;

            // Group by article type
            $articleTypeDistribution = $articles
                ->groupBy('article_type')
                ->map(function ($group) {
                    return count($group);
                });

            return response()->json([
                'article_metrics' => [
                    'avg_word_count' => $avgWordCount,
                    'faq_percentage' => $faqPercentage,
                    'article_type_distribution' => $articleTypeDistribution
                ],
                'articles' => $articleMetrics
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching article metrics: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to load article metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
