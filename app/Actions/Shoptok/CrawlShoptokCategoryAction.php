<?php

declare(strict_types=1);

namespace App\Actions\Shoptok;

use App\Models\Category;
use App\Services\ProductUpsertService;
use App\Services\Shoptok\ShoptokProductParserService;
use App\Services\Shoptok\ShoptokSeleniumService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * 🎬 **CrawlShoptokCategoryAction**
 *
 * This class is the *conductor* of the crawling operation.
 * It doesn’t know how to fetch pages, parse HTML, or save products —
 * it just tells the right Services *when* and *how* to do their job.
 *
 * Think of it as the “Director” in a movie:
 * - The camera crew (Selenium Service) fetches the footage.
 * - The editor (Parser Service) finds and cleans the useful parts.
 * - The producer (Upsert Service) stores everything safely in the database.
 *
 * Then the director yells: “Next page!” — and it all happens again.
 *
 * 🧩 Why this exists:
 * - Keeps crawling orchestration out of controllers or console commands.
 * - Centralizes business rules (how to crawl, when to stop, when to sleep).
 * - Easy to test and reuse for any Shoptok category.
 */
final readonly class CrawlShoptokCategoryAction
{
    /**
     * 🕓 Microseconds to sleep between requests.
     * Helps avoid WAF/rate-limit detection by crawling politely.
     */
    private const RATE_LIMIT_US = 1_500_000;

    /**
     * @param ShoptokSeleniumService      $seleniumService Handles actual browser fetching (via Selenium).
     * @param ShoptokProductParserService $parserService   Extracts product info from HTML.
     * @param ProductUpsertService        $upsertService   Saves or updates products in database.
     */
    public function __construct(
        private ShoptokSeleniumService      $seleniumService,
        private ShoptokProductParserService $parserService,
        private ProductUpsertService        $upsertService,
    ) {}

    /**
     * 🚀 Start the crawling process for a given category.
     *
     * This is the main entry point — it loops through pages,
     * fetches them, parses the products, and saves them to the database.
     *
     * Flow:
     *  1. Build the page URL.
     *  2. Ask Selenium to fetch HTML.
     *  3. Parse products with the Parser Service.
     *  4. Save products with the Upsert Service.
     *  5. Repeat for the next page (with random delay).
     *
     * @param Category $category The category record where products will be linked.
     * @param string   $baseUrl  The Shoptok category URL to start from.
     * @param int      $maxPages Optional page limit (safety against infinite loops).
     *
     * @return int The total number of imported or updated products.
     */
    public function handle(Category $category, string $baseUrl, int $maxPages = 25) : int
    {
        $totalImported = 0;

        // Loop through each page, one by one.
        for ($page = 1; $page <= $maxPages; $page++) {
            // Build URL for the current page (adds ?page=2, etc.)
            $url = $this->buildPageUrl(baseUrl: $baseUrl, page: $page);

            try {
                // Ask Selenium to open the page and return HTML.
                $result = $this->seleniumService->getHtml(url: $url);
                $html   = $result->html;

                // Sanity check — if page is empty, log and skip.
                if (trim($html) === '') {
                    Log::warning(message: 'Empty HTML returned, skipping page', context: compact('url'));
                    continue;
                }

                // Parse the DOM and look for product boxes.
                $dom        = new Crawler(node: $html);
                $itemBlocks = $this->parserService->findProductNodes(dom: $dom);

                // If there are no more product boxes — we're done.
                if ($itemBlocks->count() === 0) {
                    Log::info(
                        message: 'No more product blocks found, stopping crawl',
                        context: compact('url', 'page')
                    );
                    break;
                }

                $pageImported = 0;
                $pageItems    = []; // 📦 Buffer for bulk insert

                // Loop through each product block on the page.
                foreach ($itemBlocks as $node) {
                    $itemDom = new Crawler(node: $node);

                    // Parse item into clean data array (name, price, url, etc.).
                    $data = $this->parserService->parseItem(item: $itemDom);

                    // If parsing failed or data incomplete, skip safely.
                    if ($data === null) {
                        continue;
                    }

                    // Add to our buffer instead of saving immediately
                    $pageItems[] = $data;
                    $pageImported++;
                }

                // 🚀 Mass save! (Batch Upsert)
                // We send all specific page items to the database in one go.
                if (! empty($pageItems)) {
                    $this->upsertService->upsertBatch(items: $pageItems, category: $category);
                }

                $totalImported += $pageImported;

                // Log what happened on this page (success summary).
                Log::info(message: 'Crawled page successfully', context: [
                    'url'         => $url,
                    'page'        => $page,
                    'itemsStored' => $pageImported,
                    'totalStored' => $totalImported,
                    'duration'    => $result->executionTime
                ]);

                // If a page loads but no new products were found, stop gracefully.
                if ($pageImported === 0) {
                    break;
                }

                // 🕊️ Wait a little before the next page — polite crawling with small randomness.
                usleep(microseconds: self::RATE_LIMIT_US + random_int(min: 0, max: 300000));
            } catch (Throwable $e) {
                /**
                 * ⚠️ If something explodes:
                 * - We log detailed info (page, URL, message, trace).
                 * - Then we stop the crawl, because retrying blindly can loop forever.
                 */
                Log::error(
                    message: "Failed to crawl page {$page}",
                    context: [
                                 'url'   => $url,
                                 'error' => $e->getMessage(),
                                 'trace' => $e->getTraceAsString()
                             ]
                );

                break; // stop crawling after a serious error
            }
        }

        // Return total products imported across all pages.
        return $totalImported;
    }

    /**
     * 🧮 Build the URL for a specific page number.
     *
     * Example:
     * - Base: https://www.shoptok.si/televizorji/cene/206
     * - Page 2 → https://www.shoptok.si/televizorji/cene/206?page=2
     *
     * @param string $baseUrl The root category URL.
     * @param int    $page    Page number (1 = first page).
     *
     * @return string The full URL for the requested page.
     */
    private function buildPageUrl(string $baseUrl, int $page) : string
    {
        // Page 1 is the base URL, no query string needed.
        if ($page <= 1) {
            return $baseUrl;
        }

        // Add ?page=2 or &page=2 depending on whether the base already has a query.
        $separator = str_contains(haystack: $baseUrl, needle: '?') ? '&' : '?';

        return $baseUrl . $separator . 'page=' . $page;
    }
}
