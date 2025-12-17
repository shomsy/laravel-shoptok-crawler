<?php

declare(strict_types=1);

namespace App\Services\Shoptok;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * 🧭 Shoptok Category Parser (Simplified Edition)
 *
 * This service is a focused HTML parser used for extracting **only** the two
 * specific subcategories that belong to the "TV sprejemniki" (TV receivers)
 * root category on the Shoptok website:
 *
 *   → "Televizorji"  (TVs)
 *   → "TV dodatki"   (TV accessories)
 *
 * Everything else (ads, unrelated links, etc.) is ignored.
 *
 * ------------------------------------------------------
 * 💡 TL;DR — What it does:
 * ------------------------------------------------------
 * 1. Takes raw HTML from a Shoptok category page (e.g., tv-sprejemniki)
 * 2. Scans for <a> links that lead to “/televizorji/” or “/tv-dodatki/”
 * 3. Cleans and normalizes those links (absolute URLs)
 * 4. Returns a clean PHP array like:
 *
 * [
 *   ['name' => 'Televizorji', 'slug' => 'televizorji', 'url' => 'https://www.shoptok.si/televizorji/cene/206'],
 *   ['name' => 'TV dodatki',  'slug' => 'tv-dodatki',  'url' => 'https://www.shoptok.si/tv-dodatki/cene/258']
 * ]
 *
 * This is later used by the crawler to visit both pages and scrape products.
 */
final class ShoptokCategoryParserService
{
    /**
     * Parse and return subcategories.
     *
     * @param string $html The full HTML of the Shoptok category page.
     *
     * @return array<int, array{name: string, slug: string, url: string}>
     *
     * The output array contains clean subcategory data ready to be stored
     * in the database or passed to the crawler for product extraction.
     */
    public function parseSubcategories(string $html): array
    {
        // Symfony DomCrawler: allows CSS/XPath queries on HTML documents
        $dom = new Crawler(node: $html);

        Log::info(message: "🔍 [ShoptokParser] Parsing simplified TV subcategories...");

        /**
         * 🎯 Step 1 — Target only what we care about.
         *
         * We know the page structure already. So instead of parsing all links,
         * we directly look for anchor tags that contain either:
         *   - "/televizorji/"
         *   - "/tv-dodatki/"
         *
         * This drastically simplifies the logic and avoids "noise" from other
         * parts of the page (like popular categories, ads, or recommendations).
         */
        $xpath = "//a[contains(@href, '/televizorji/') or contains(@href, '/tv-dodatki/')]";
        $links = $dom->filterXPath(xpath: $xpath);

        Log::info(message: "📦 [ShoptokParser] Found {$links->count()} relevant links (Televizorji + TV dodatki).");

        $out = [];

        /**
         * 🧹 Step 2 — Iterate through all matching <a> tags.
         * Extract text (link name) and href (URL).
         */
        foreach ($links as $a) {
            $name = trim(string: $a->textContent ?? '');
            $href = trim(string: $a->getAttribute(qualifiedName: 'href') ?? '');

            if ($name === '' || $href === '') continue;

            // Convert to lowercase for easy comparison
            $lowerName = mb_strtolower(string: $name);

            /**
             * ✅ Step 3 — Whitelist filter
             *
             * We *only* keep links that are literally named
             * "Televizorji" or "TV dodatki".
             *
             * This avoids mistakenly catching similar strings
             * (for example, "Nosilci za TV" or "OLED TV").
             */
            if (!in_array(needle: $lowerName, haystack: ['televizorji', 'tv dodatki'])) {
                continue;
            }

            // Make sure the URL is absolute (includes domain)
            $normalizedUrl = $this->normalizeUrl(url: $href);

            /**
             * 🧱 Step 4 — Store clean, ready-to-use data
             *
             * - name: human-readable name (e.g., “Televizorji”)
             * - slug: URL-friendly version (“televizorji”)
             * - url: full absolute link (https://www.shoptok.si/televizorji/cene/206)
             */
            $out[$normalizedUrl] = [
                'name' => $name,
                'slug' => Str::slug(title: $name),
                'url' => $normalizedUrl,
            ];
        }

        Log::info(message: "✅ [ShoptokParser] Accepted " . count(value: $out) . " clean subcategories (Televizorji / TV dodatki).");

        return array_values(array: $out);
    }

    /**
     * Normalize a given URL into a full absolute URL.
     *
     * ------------------------------------------------------
     * Example:
     *   Input:  "/televizorji/cene/206"
     *   Output: "https://www.shoptok.si/televizorji/cene/206"
     *
     * Some links in Shoptok's HTML are relative (start with "/"),
     * so we attach the main domain manually.
     * ------------------------------------------------------
     *
     * @param string $url The raw link extracted from the HTML.
     * @return string        A fully qualified URL.
     */
    private function normalizeUrl(string $url): string
    {
        // If the URL already starts with http/https, it’s full
        if (str_starts_with(haystack: $url, needle: 'http://') || str_starts_with(haystack: $url, needle: 'https://')) {
            return $url;
        }

        // Otherwise, append the main domain manually
        return 'https://www.shoptok.si' . $url;
    }
}
