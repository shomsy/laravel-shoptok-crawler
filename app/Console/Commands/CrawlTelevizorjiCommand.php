<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Shoptok\CrawlShoptokCategoryAction;
use App\Models\Category;
use Illuminate\Console\Command;

/**
 * 🎯 **CrawlTelevizorjiCommand**
 *
 * This Artisan command serves as the CLI entry point for crawling
 * the **Shoptok “Televizorji”** category and storing its products
 * into the local database.
 *
 * 🧩 **What it does**
 * - Ensures a `Category` record for "Televizorji" exists.
 * - Delegates all crawling logic to {@see CrawlShoptokCategoryAction}.
 * - Prints a friendly report of how many products were imported or updated.
 *
 * 🧠 **Why it exists**
 * - Keeps crawling logic out of HTTP controllers (pure CLI task).
 * - Enables automation via Laravel Scheduler or cron jobs.
 * - Allows quick manual testing via terminal commands.
 *
 * 💡 **Usage Example**
 * ```bash
 * php artisan crawl:televizorji --max-pages=10
 * ```
 *
 * 📘 **Typical Scenario**
 * 1. The dev or cron job triggers this command.
 * 2. It ensures “Televizorji” exists in the DB.
 * 3. It launches a crawl through {@see CrawlShoptokCategoryAction}.
 * 4. The products are saved (upserted) into the database.
 */
final class CrawlTelevizorjiCommand extends Command
{
    /**
     * 🏷️ The command’s signature (name + arguments + options).
     *
     * Options:
     * - `{--max-pages=25}` → Optional flag to limit pagination depth.
     *   Useful for testing partial crawls or avoiding timeouts.
     *
     * Example:
     * ```
     * php artisan crawl:televizorji --max-pages=5
     * ```
     */
    protected $signature = 'crawl:televizorji {--max-pages=25}';

    /**
     * 📝 Short description visible in `php artisan list`.
     */
    protected $description = 'Crawl Shoptok “Televizorji” and store products into the database';

    /**
     * 🚀 **Main entry point for the command.**
     *
     * This method does three simple but critical steps:
     *
     * 1. Ensures the "Televizorji" category exists in the DB.
     * 2. Delegates the actual crawling to {@see CrawlShoptokCategoryAction}.
     * 3. Outputs a summary of imported or updated products.
     *
     * @param CrawlShoptokCategoryAction $action
     *        The orchestrator responsible for crawling and data persistence.
     *
     * @return int
     *         Exit code: `0` for success, non-zero for failure (per POSIX standard).
     */
    public function handle(CrawlShoptokCategoryAction $action): int
    {
        // 🧱 Step 1: Ensure “Televizorji” category exists (create it if missing).
        $category = Category::firstOrCreate(
            attributes: ['slug' => 'televizorji'],
            values: ['name' => 'Televizorji']
        );

        // 🌐 Step 2: Start the crawl process.
        // The Action handles Selenium fetching, parsing, and DB upserting.
        $count = $action->handle(
            category: $category,
            baseUrl: 'https://www.shoptok.si/televizorji/cene/206',
            maxPages: (int) $this->option(key: 'max-pages')
        );

        // ✅ Step 3: Output a clean, readable result for humans.
        $this->info(string: "✅ Done. Imported or updated: {$count} products.");

        // Return success (exit code 0).
        return self::SUCCESS;
    }
}
