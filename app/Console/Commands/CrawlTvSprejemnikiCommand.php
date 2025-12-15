<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Shoptok\CrawlShoptokCategoryAction;
use App\Models\Category;
use App\Services\Shoptok\ShoptokCategoryParserService;
use App\Services\Shoptok\ShoptokSeleniumService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 🎯 **CrawlTvSprejemnikiCommand**
 *
 * This Artisan command crawls **the entire Shoptok "TV Sprejemniki" category** —
 * including all its *subcategories* (like “Televizorji” and “TV dodatki”).
 *
 * 🧩 What it does:
 * 1. Opens the main “TV Sprejemniki” page with {@see ShoptokSeleniumService}.
 * 2. Uses {@see ShoptokCategoryParserService} to find all subcategories.
 * 3. Creates database entries for those subcategories.
 * 4. Delegates actual crawling of each subcategory to {@see CrawlShoptokCategoryAction}.
 * 5. Logs and reports how many products were imported in total.
 *
 * 🧠 Why this exists:
 * - Some categories on Shoptok are hierarchical (have subcategories).
 * - This command automates crawling of *all* relevant subcategories,
 *   instead of you running multiple manual commands.
 */
final class CrawlTvSprejemnikiCommand extends Command
{
    /**
     * 🏷️ Command signature and optional arguments.
     *
     * Example usage:
     * ```
     * php artisan crawl:tv-sprejemniki --max-pages=10
     * ```
     *
     * - `{--max-pages=25}`: limits how deep each subcategory will be crawled.
     */
    protected $signature = 'crawl:tv-sprejemniki {--max-pages=25}';

    /**
     * 📝 Description shown in `php artisan list`.
     */
    protected $description = 'Crawl Shoptok TV sprejemniki (root + subcategories)';

    /**
     * 🚀 The main command handler — entry point when you run the command.
     *
     * The command performs a “two-level” crawl:
     *  1️⃣ Discover all subcategories under “TV Sprejemniki”.
     *  2️⃣ Crawl each of them individually using {@see CrawlShoptokCategoryAction}.
     *
     * @param ShoptokSeleniumService       $selenium The service that opens pages and returns HTML.
     * @param ShoptokCategoryParserService $parser   The parser that extracts subcategory links from the root page.
     * @param CrawlShoptokCategoryAction   $action   The orchestrator that crawls each subcategory page-by-page.
     *
     * @return int Laravel exit code (0 = success, nonzero = failure).
     */
    public function handle(
        ShoptokSeleniumService       $selenium,
        ShoptokCategoryParserService $parser,
        CrawlShoptokCategoryAction   $action
    ): int {
        // 🧭 Step 0: Define root URL for "TV Sprejemniki" category.
        $rootUrl = config(key: 'shoptok.categories.tv_sprejemniki.url');

        // 🧱 Step 1: Ensure a root category exists in the database.
        $root = Category::firstOrCreate(
            ['slug' => 'tv-sprejemniki'],
            ['name' => 'TV sprejemniki']
        );

        // 🌐 Step 2: Fetch the root page and look for subcategories.
        $result = $selenium->getHtml(url: $rootUrl);
        $subs = $parser->parseSubcategories(html: $result->html);

        // 🕳️ If nothing is found, warn and exit gracefully.
        if (empty($subs)) {
            $this->warn(string: '⚠️ No subcategories found — skipping crawl.');
            return self::FAILURE;
        }

        // Initialize counters for total imports and page limits.
        $total = 0;
        $maxPages = (int) $this->option(key: 'max-pages');

        // 🔁 Step 3: Loop through every discovered subcategory.
        foreach ($subs as $sub) {
            // Ensure the subcategory exists in the database (linked to root).
            $cat = Category::firstOrCreate(
                ['slug' => $sub['slug']],
                ['name' => $sub['name'], 'parent_id' => $root->id]
            );

            // 🎬 Step 4: Crawl that subcategory (delegated to Action).
            $count = $action->handle(
                category: $cat,
                baseUrl: $sub['url'],
                maxPages: $maxPages
            );

            // Update running totals.
            $total += $count;

            // Log result for each subcategory.
            Log::info(message: 'Crawled subcategory', context: [
                'slug' => $cat->slug,
                'items' => $count,
                'parent' => $root->slug
            ]);

            // Optional CLI feedback — makes it easier to follow progress.
            $this->line(string: "✅ Crawled {$cat->name} → {$count} products.");
        }

        // 🏁 Step 5: Display final summary message.
        $this->info(string: "🎉 Done. Imported/updated total: {$total} products.");

        // Return success code (Laravel standard).
        return self::SUCCESS;
    }
}
