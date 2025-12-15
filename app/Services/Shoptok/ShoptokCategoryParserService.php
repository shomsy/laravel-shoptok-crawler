<?php

declare(strict_types=1);

namespace App\Services\Shoptok;

use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * 🗺️ **The Navigator (Category Parser)**
 *
 * This class looks at the main page and figures out where else we need to go.
 * It finds links to sub-categories (like "OLED TV", "Soundbars").
 *
 * **Goal:**
 * Turn a list of messy HTML links into a clean list of destinations (URLs) for the Crawler.
 */
final class ShoptokCategoryParserService
{
    /**
     * Vraća subkategorije kao: name, slug, url
     * @return array<string, array{name: string, slug: string, url: string}>
     */
    public function parseSubcategories(string $html): array
    {
        $dom = new Crawler(node: $html);

        // heuristika: sidebar/menu linkovi ka kategorijama često imaju /cene/ u href-u
        $links = $dom->filterXPath(xpath: "//a[contains(@href, '/cene/')]");

        $out = [];

        foreach ($links as $a) {
            $name = trim(string: $a->textContent ?? '');
            $href = $a->getAttribute(qualifiedName: 'href');

            if ($name === '' || $href === '') {
                continue;
            }

            // filtriraj očigledne non-category linkove
            $lower = mb_strtolower(string: $name);
            if (str_contains(haystack: $lower, needle: 'primerjaj cene') || str_contains(haystack: $lower, needle: 'več o ponudbi') || str_contains(haystack: $lower, needle: 'vec o ponudbi')) {
                continue;
            }

            // Zadrži kratke i relevantne nazive (Televizorji, TV dodatki) kao u bonus zahtevu.
            if (!str_contains(haystack: $lower, needle: 'tv') && !str_contains(haystack: $lower, needle: 'telev')) {
                continue;
            }

            $out[$href] = [
                'name' => $name,
                'slug' => Str::slug(title: $name),
                'url' => $this->normalizeUrl(url: $href),
            ];
        }

        return array_values(array: $out);
    }

    private function normalizeUrl(string $url): string
    {
        if (str_starts_with(haystack: $url, needle: 'http://') || str_starts_with(haystack: $url, needle: 'https://')) {
            return $url;
        }

        return 'https://www.shoptok.si' . $url;
    }
}
