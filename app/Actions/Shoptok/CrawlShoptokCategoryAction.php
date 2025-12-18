<?php

declare(strict_types=1);

namespace App\Actions\Shoptok;

use App\Models\Category;
use App\Services\ProductUpsertService;
use App\Services\Shoptok\ShoptokCategoryParserService;
use App\Services\Shoptok\ShoptokProductParserService;
use App\Services\Shoptok\ShoptokSeleniumService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * 🕹️ CrawlShoptokCategoryAction
 *
 * Coordinates the full crawl process for a single Shoptok category.
 * It delegates the actual work to services:
 * - **SeleniumService**: loads pages
 * - **ParserService**: extracts product and category data
 * - **UpsertService**: stores or updates products
 *
 * This class simply orchestrates — it decides *what to crawl next* and *when to stop*.
 */
final readonly class CrawlShoptokCategoryAction
{
    /** Delay between page fetches (microseconds) to avoid being rate-limited. */
    private const RATE_LIMIT_US = 1_500_000;

    public function __construct(
        private ShoptokSeleniumService       $seleniumService,
        private ShoptokProductParserService  $parserService,
        private ProductUpsertService         $upsertService,
        private ShoptokCategoryParserService $categoryParser,
    ) {}

    /**
     * Run the crawling process for a specific Shoptok category.
     *
     * Steps:
     *  1. Fetch and parse pages using Selenium + Parser services.
     *  2. Detect and crawl subcategories recursively.
     *  3. Save parsed products via batch upsert.
     *  4. Stop when no products are found or max pages reached.
     *  5. Clear cache after crawling.
     *
     * @param Category $category Local category where products are linked.
     * @param string   $baseUrl  Shoptok category URL.
     * @param int|null $maxPages Page limit for safety.
     * @param int      $depth    Recursion depth (prevents infinite loops).
     *
     * @return int Total number of imported or updated products.
     */
    public function handle(Category $category, string $baseUrl, int|null $maxPages = null, int $depth = 0) : int
    {
        $maxPages ??= 25;
        if ($depth > 5) {
            Log::warning(message: "Recursion depth limit reached at {$baseUrl}");

            return 0;
        }

        $totalImported = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            $url = $this->buildPageUrl(baseUrl: $baseUrl, page: $page);

            try {
                echo "🔄 Fetching page {$page}/{$maxPages}: {$url} ... ";

                $result = $this->seleniumService->getHtml(url: $url);
                $html   = trim(string: $result->html);

                if ($html === '') {
                    Log::warning(message: 'Empty HTML returned, skipping page', context: compact(var_name: 'url'));
                    continue;
                }

                // 🧩 Recursively crawl subcategories (only from the first page)
                if ($page === 1) {
                    $subcategories = $this->categoryParser->parseSubcategories(html: $html);

                    foreach ($subcategories as $sub) {
                        if ($sub['slug'] === $category->slug) {
                            continue;
                        }

                        Log::info(message: "Found subcategory: {$sub['name']} → crawling...");

                        $childCategory = Category::updateOrCreate(
                            ['slug' => $sub['slug']],
                            ['name' => $sub['name'], 'parent_id' => $category->id]
                        );

                        $totalImported += $this->handle(
                            category: $childCategory,
                            baseUrl : $sub['url'],
                            maxPages: $maxPages,
                            depth   : $depth + 1
                        );
                    }
                }

                // Parse product nodes
                $dom   = new Crawler(node: $html);
                $nodes = $this->parserService->findProductNodes(dom: $dom);

                if ($nodes->count() === 0) {
                    Log::info(message: 'No product nodes found — stopping crawl', context: compact('url', 'page'));
                    break;
                }

                $pageItems = [];
                foreach ($nodes as $node) {
                    $itemDom = new Crawler(node: $node);
                    $data    = $this->parserService->parseItem(item: $itemDom);

                    if ($data) {
                        $pageItems[] = $data;
                    }
                }

                // Bulk save
                if ($pageItems) {
                    $this->upsertService->upsertBatch(items: $pageItems, category: $category);
                }

                $imported      = count(value: $pageItems);
                $totalImported += $imported;

                Log::info(message: "✅ Page {$page} done.",
                          context: [
                                       'url'      => $url,
                                       'imported' => $imported,
                                       'total'    => $totalImported,
                                       'time'     => $result->executionTime,
                                   ]
                );

                echo "✅ Imported {$imported} items (Total: {$totalImported}) [{$result->executionTime}s]\n";

                if ($imported === 0) {
                    break;
                }

                // Wait before next page
                usleep(microseconds: self::RATE_LIMIT_US + random_int(min: 0, max: 300000));
            } catch (Throwable $e) {
                Log::error(message: "Failed to crawl page {$page}", context: [
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        // 🧹 Clear cache after crawl
        try {
            Cache::flush();
            Log::info(message: "Cache flushed after crawl.");
        } catch (Throwable $e) {
            Log::warning(message: "Could not flush cache: " . $e->getMessage());
        }

        return $totalImported;
    }

    /**
     * Build the URL for a specific page.
     *
     * @example
     * buildPageUrl('https://www.shoptok.si/televizorji/cene/206', 2)
     * // → "https://www.shoptok.si/televizorji/cene/206?page=2"
     */
    private function buildPageUrl(string $baseUrl, int $page) : string
    {
        if ($page <= 1) {
            return $baseUrl;
        }

        $separator = str_contains(haystack: $baseUrl, needle: '?') ? '&' : '?';

        return "{$baseUrl}{$separator}page={$page}";
    }
}
