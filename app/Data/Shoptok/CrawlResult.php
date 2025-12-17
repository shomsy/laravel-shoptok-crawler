<?php

declare(strict_types=1);

namespace App\Data\Shoptok;

/**
 * 📦 **CrawlResult**
 *
 * A tiny “package” that carries crawl results from {@see \App\Services\Shoptok\ShoptokSeleniumService}.
 *
 * 🧠 Think of this as a courier box:
 * - The *HTML* is the main content you wanted.
 * - The *URL* tells you where it came from.
 * - The *executionTime* shows how long the delivery took.
 *
 * **Why this class exists:**
 * - Instead of returning multiple loose variables (e.g. `$html`, `$url`, `$time`),
 *   we return one simple, predictable object.
 * - This makes the API between Services and Actions more stable and type-safe.
 * - It also helps in logging, debugging, and testing.
 *
 * 🧩 Used by:
 * - {@see \App\Actions\Shoptok\CrawlShoptokCategoryAction}
 * - {@see \App\Services\Shoptok\ShoptokSeleniumService}
 */
readonly class CrawlResult
{
    /**
     * @param string $html The raw HTML content of the crawled page.
     * @param string $url The full URL that was fetched.
     * @param float $executionTime How long it took to fetch the page (in seconds).
     */
    public function __construct(
        public string $html,
        public string $url,
        public float  $executionTime,
    )
    {
        // No logic here — this class is just a data container.
        // Its immutability (readonly) ensures the data can’t be changed later.
    }
}
