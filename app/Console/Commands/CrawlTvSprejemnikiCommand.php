<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Shoptok\CrawlShoptokCategoryAction;
use App\Models\Category;
use Illuminate\Console\Command;

/**
 * 🧭 CrawlTvSprejemnikiCommand
 *
 * Console command to crawl the "TV sprejemniki" category from Shoptok.
 *
 * Runs the crawling process through {@see CrawlShoptokCategoryAction},
 * which recursively collects products and subcategories.
 */
final class CrawlTvSprejemnikiCommand extends Command
{
    /** Command signature and supported options. */
    protected $signature = 'crawl:tv-sprejemniki {--max-pages=25}';

    /** Short description for `php artisan list`. */
    protected $description = 'Crawl Shoptok "TV sprejemniki" category and its subcategories.';

    /**
     * Execute the command.
     *
     * Ensures the root category exists, starts the crawl process,
     * and logs how many products were imported.
     */
    public function handle(CrawlShoptokCategoryAction $action) : int
    {
        // Ensure the root category exists in the database
        $category = Category::firstOrCreate(
            ['slug' => 'tv-sprejemniki'],
            ['name' => 'TV sprejemniki']
        );

        $this->info(string: "🚀 Starting crawl for root category: TV sprejemniki");

        // Run the crawler starting from the root Shoptok URL
        $count = $action->handle(
            category: $category,
            baseUrl : 'https://www.shoptok.si/tv-sprejemniki/cene/56',
            maxPages: (int) $this->option(key: 'max-pages')
        );

        $this->info(string: "✅ Crawl finished. Total imported/updated: {$count} products.");

        return self::SUCCESS;
    }
}
