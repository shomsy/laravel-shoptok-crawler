<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Shoptok\CrawlShoptokCategoryAction;
use App\Models\Category;
use Illuminate\Console\Command;

final class CrawlTvSprejemnikiCommand extends Command
{
    protected $signature = 'crawl:tv-sprejemniki {--max-pages=25}';

    protected $description = 'Crawl Shoptok “TV sprejemniki” root category and its subcategories';

    public function handle(CrawlShoptokCategoryAction $action): int
    {
        // Ensure Root Category exists
        $category = Category::firstOrCreate(
            ['slug' => 'tv-sprejemniki'],
            ['name' => 'TV sprejemniki']
        );

        $this->info("Starting crawl for Root Category: TV sprejemniki...");

        // Start from the root category URL provided in the task
        // Fixing typo: 'tv-prijamnici' -> 'tv-sprejemniki' (Slovenian)
        $count = $action->handle(
            category: $category,
            baseUrl: 'https://www.shoptok.si/tv-sprejemniki/cene/56',
            maxPages: (int)$this->option(key: 'max-pages')
        );

        $this->info("✅ Done. Total imported/updated: {$count} products.");

        return self::SUCCESS;
    }
}
