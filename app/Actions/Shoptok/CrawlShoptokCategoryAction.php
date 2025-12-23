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
use Symfony\Component\Console\Output\OutputInterface;
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
     * @param Category $category Local category where products are linked.
     * @param string   $baseUrl  Shoptok category URL.
     * @param int|null $maxPages Page limit for safety.
     * @param int      $depth    Recursion depth (prevents infinite loops).
     * @param array                     $visitedUrls Tracker for already crawled URLs.
     * @param OutputInterface|null $output Optional command output interface.
     *
     * @return int Total number of imported or updated products.
     */
    public function handle(
        Category $category,
        string $baseUrl,
        int|null $maxPages = null,
        int $depth = 0,
        array &$visitedUrls = [],
        ?OutputInterface $output = null
    ): int {
        $maxPages ??= 25;

        // Guard: Check if URL was already visited in this session
        if (isset($visitedUrls[$baseUrl])) {
            Log::info(message: "Skipping already visited category: {$category->name} ({$baseUrl})");

            return 0;
        }

        // Mark current URL as visited
        $visitedUrls[$baseUrl] = true;

        if ($depth > 5) {
            Log::warning(message: "Recursion depth limit reached at {$baseUrl}");

            return 0;
        }

        $totalImported = 0;
        $emptyStreak   = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            $url = $this->buildPageUrl(baseUrl: $baseUrl, page: $page);

            try {
                $this->writeln(message: "🔄 Fetching page {$page}/{$maxPages}: {$url} ... ", output: $output);

                $result = $this->seleniumService->getHtml(url: $url);
                $html   = trim(string: $result->html);

                // 🧱 Problem 7: Minimal protection against malformed HTML
                if ($html === '' || ! str_contains(haystack: $html, needle: '<body')) {
                    Log::warning(message: 'Malformed or empty HTML returned', context: compact(var_name: 'url'));
                    $emptyStreak++;
                    if ($emptyStreak >= 3) {
                        break;
                    }
                    continue;
                }

                // 🧩 Recursively crawl subcategories (only from the first page)
                if ($page === 1) {
                    $subcategories = $this->categoryParser->parseSubcategories(html: $html);

                    foreach ($subcategories as $sub) {
                        // 🧱 Problem 3: Validate subcategory URL
                        if (empty($sub['url']) || str_starts_with(haystack: $sub['url'], needle: '#')) {
                            continue;
                        }

                        // Skip if subcategory URL already visited
                        if (isset($visitedUrls[$sub['url']])) {
                            continue;
                        }

                        if ($sub['slug'] === $category->slug) {
                            continue;
                        }

                        Log::info(message: "Found subcategory: {$sub['name']} → crawling...");

                        // 🛡️ Safeguard: Prevent circular parent relationship
                        $childCategory = Category::where('slug', $sub['slug'])->first();
                        $parentId      = $category->id;

                        if ($childCategory) {
                            // 1. ROOT CLAIM: If current category is a root (no parent),
                            // it "claims" this child even if it's currently linked elsewhere.
                            if ($category->parent_id === null) {
                                $parentId = $category->id;
                            }
                            // 2. Lock parent: If it already has a parent, don't overwrite it
                            elseif ($childCategory->parent_id !== null) {
                                $parentId = $childCategory->parent_id;
                            }
                            // 3. Prevent cycles: If current category is already a descendant of the found subcategory
                            else {
                                // 🧱 Problem 6: Avoid false positives in circular check
                                $descendantsOfChild = array_diff(
                                    Category::getDescendantIds($childCategory->id),
                                    [$childCategory->id]
                                );

                                if (in_array(needle: $category->id, haystack: $descendantsOfChild)) {
                                    Log::warning("⚠️ Prevented circular dependency: {$category->slug} is already a descendant of {$childCategory->slug}.");
                                    $parentId = $childCategory->parent_id;
                                }
                            }
                        }

                        $childCategory = Category::updateOrCreate(
                            ['slug' => $sub['slug']],
                            ['name' => $sub['name'], 'parent_id' => $parentId]
                        );

                        $totalImported += $this->handle(
                            category: $childCategory,
                            baseUrl: $sub['url'],
                            maxPages: $maxPages,
                            depth: $depth + 1,
                            visitedUrls: $visitedUrls,
                            output: $output
                        );
                    }
                }

                // Parse product nodes
                $dom   = new Crawler(node: $html);
                $nodes = $this->parserService->findProductNodes(dom: $dom);

                // 🧱 Problem 2: emptyStreak instead of immediate break
                if ($nodes->count() === 0) {
                    Log::info(message: 'No product nodes found', context: compact('url', 'page'));
                    $emptyStreak++;
                    if ($emptyStreak >= 3) {
                        break;
                    }
                    continue;
                }

                $emptyStreak = 0; // Reset streak on success

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

                Log::info(
                    message: "✅ Page {$page} done.",
                    context: [
                        'url'      => $url,
                        'imported' => $imported,
                        'total'    => $totalImported,
                        'time'     => $result->executionTime,
                    ]
                );

                $this->writeln(message: "✅ Imported {$imported} items (Total: {$totalImported}) [{$result->executionTime}s]", output: $output);

                if ($imported === 0) {
                    $emptyStreak++;
                    if ($emptyStreak >= 3) {
                        break;
                    }
                    continue;
                }

                // 🧱 Problem 4: Configurable throttling
                if (config(key: 'shoptok.throttle.enabled', default: true)) {
                    $delay = (int) config(key: 'shoptok.throttle.delay_us', default: 1_500_000);
                    usleep(microseconds: $delay + random_int(min: 0, max: 300_000));
                }
            } catch (Throwable $e) {
                Log::error(message: "Failed to crawl page {$page}", context: [
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        // 🧱 Problem 1: Targeted cache flush
        $this->flushCache();

        return $totalImported;
    }

    /**
     * 🧱 Problem 5: Proper output handling
     */
    private function writeln(string $message, ?OutputInterface $output = null): void
    {
        if ($output) {
            $output->writeln($message);

            return;
        }

        if (app()->runningInConsole()) {
            echo $message . (str_ends_with($message, "\n") ? '' : "\n");
        }
    }

    /**
     * 🧱 Problem 1: Better cache management
     */
    private function flushCache(): void
    {
        try {
            // If the driver supports tags, be surgical.
            // Otherwise, we might have to flush everything, but let's avoid global flush if possible.
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['products', 'categories'])->flush();
                Log::info(message: "Tagged cache flushed.");
            } else {
                // If no tags, we avoid Cache::flush() to not kill sessions/etc.
                // We could selectively forget specific keys if we had them.
                Log::info(message: "Selective cache flush skipped (tags not supported).");
            }
        } catch (Throwable $e) {
            Log::warning(message: "Could not flush cache: " . $e->getMessage());
        }
    }

    /**
     * Build the URL for a specific page.
     *
     * @example
     * buildPageUrl('https://www.shoptok.si/televizorji/cene/206', 2)
     * // → "https://www.shoptok.si/televizorji/cene/206?page=2"
     */
    private function buildPageUrl(string $baseUrl, int $page): string
    {
        if ($page <= 1) {
            return $baseUrl;
        }

        $separator = str_contains(haystack: $baseUrl, needle: '?') ? '&' : '?';

        return "{$baseUrl}{$separator}page={$page}";
    }
}
