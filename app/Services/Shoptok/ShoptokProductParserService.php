<?php

declare(strict_types=1);

namespace App\Services\Shoptok;

use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

/**
 * 🕵️‍♂️ Shoptok Product Parser Service
 * ==================================
 *
 * Think of this class as the “detective” 🕵️‍♂️ for our crawler.
 * It takes raw, messy HTML from a Shoptok product listing page
 * and extracts **clean, structured product information**.
 *
 * ------------------------------------------------------
 * 💡 What it does:
 * ------------------------------------------------------
 * ✅ Finds product cards in the HTML (each card = one product)
 * ✅ Extracts:
 *    - Name (product title)
 *    - Brand (if possible)
 *    - Price (converted to a float)
 *    - Image URL (the best possible version)
 *    - Product URL
 * ✅ Cleans, normalizes, and returns this data as a simple PHP array
 *
 * Example of final output:
 * [
 *   'external_id' => 'a unique hash',
 *   'name' => 'Hisense 55E7HQ 55" 4K Smart TV',
 *   'brand' => 'Hisense',
 *   'price' => 379.99,
 *   'currency' => 'EUR',
 *   'image_url' => 'https://img.ep-cdn.com/...jpg',
 *   'product_url' => 'https://www.shoptok.si/hisense-55e7hq-tv/cena/123456'
 * ]
 */
final class ShoptokProductParserService
{
    /**
     * 🔍 Parse a single product item (one HTML block)
     *
     * Takes one product card (a <div> that contains image, name, and price)
     * and tries to extract all key information.
     *
     * @param Crawler $item A DomCrawler node representing one product.
     *
     * @return array|null      Clean product data, or NULL if it's not a valid item.
     */
    public function parseItem(Crawler $item) : array|null
    {
        $name = $this->firstLinkText(item: $item);
        $url  = $this->firstLinkHref(item: $item);

        if ($name === null || $url === null) {
            return null; // Not a valid product card (probably an ad or empty box)
        }

        $price    = $this->extractPriceFromText(text: $item->text(default: ''));
        $brand    = $this->extractBrandFromText(text: $name, item: $item);
        $imageUrl = $this->firstImageSrc(item: $item);

        Log::info(message: "Parsed Item: Name='{$name}', Brand='{$brand}', Image='{$imageUrl}'");

        return [
            'external_id' => $this->makeExternalId(url: $url),
            'name'        => $name,
            'brand'       => $brand,
            'price'       => $price,
            'currency'    => 'EUR',
            'image_url'   => $imageUrl,
            'product_url' => $this->normalizeUrl(url: $url),
        ];
    }

    /**
     * 📄 Finds the first “real” product name link inside the item block.
     * Skips CTA links like “Compare prices” or “More offers”.
     */
    private function firstLinkText(Crawler $item) : string|null
    {
        $links = $item->filter(selector: 'a');
        if ($links->count() === 0) {
            return null;
        }

        foreach ($links as $a) {
            $text = trim(string: $a->textContent ?? '');
            if ($text === '' || $this->isCtaText(text: $text)) {
                continue;
            }

            return $text;
        }

        return null;
    }

    /** Checks if a link text is a “call-to-action” (like “Compare prices”) */
    private function isCtaText(string $text) : bool
    {
        $t = mb_strtolower(string: $text);

        return str_contains(haystack: $t, needle: 'primerjaj cene') // “Compare prices”
            || str_contains(haystack: $t, needle: 'več o ponudbi')   // “More offers”
            || str_contains(haystack: $t, needle: 'vec o ponudbi');
    }

    /** Finds the href (URL) of the first “real” product link */
    private function firstLinkHref(Crawler $item) : string|null
    {
        $links = $item->filter(selector: 'a');
        if ($links->count() === 0) {
            return null;
        }

        foreach ($links as $a) {
            $text = trim(string: $a->textContent ?? '');
            $href = $a->getAttribute(qualifiedName: 'href');
            if ($href === '' || $this->isCtaText(text: $text)) {
                continue;
            }

            return $href;
        }

        return null;
    }

    /**
     * 💰 Extracts price from the product’s text.
     * Handles various formats like “od 1.799,99 €” or “412,90 €”.
     * Converts it into a clean float: 1799.99
     */
    private function extractPriceFromText(string $text) : float
    {
        // Pattern 1: Standard Euro format with thousands dot and decimal comma "1.799,99 €"
        // Regex Explanation:
        // (\d{1,3}(\.\d{3})*)   -> Matches 1.799 or 179 (Integer part with dots)
        // (,\d{2})?             -> Matches ,99 (Decimal part, optional)
        if (preg_match(pattern: '/(\d{1,3}(\.\d{3})*)(,\d{2})?\s*€/u', subject: $text, matches: $matches)) {
            // We want the full number string "1.799,99"
            // $matches[0] contains the full match including €, so we reconstruct it from parts
            // $matches[1] is integer part "1.799"
            // $matches[3] is decimal part ",99"

            $intPart = str_replace(search: '.', replace: '', subject: $matches[1]);
            $decPart = isset($matches[3]) ? str_replace(search: ',', replace: '.', subject: $matches[3]) : '';

            return (float) ($intPart . $decPart);
        }

        // Pattern 2: Fallback for spaces or other formats "1 799 €"
        if (preg_match(pattern: '/(\d[\d\s\.]*),(\d{2})\s*€/u', subject: $text, matches: $matches)) {
            $intPart = preg_replace(pattern: '/[^\d]/', replacement: '', subject: $matches[1]);
            $decPart = '.' . $matches[2];

            return (float) ($intPart . $decPart);
        }

        return 0.0;
    }

    /**
     * 🏷️ Extracts the brand name.
     *
     * Strategy:
     * 1️⃣ Try to get it from the “event-viewitem-brand” attribute inside <h3>.
     * 2️⃣ If missing, fall back to detecting brand keywords in the name (Samsung, LG, etc.)
     */
    private function extractBrandFromText(string $text, Crawler $item) : string|null
    {
        $h3 = $item->filter(selector: 'h3[event-viewitem-brand]');
        if ($h3->count() > 0) {
            $attrBrand = $h3->attr(attribute: 'event-viewitem-brand');
            if (! empty($attrBrand)) {
                return trim(string: $attrBrand);
            }
        }

        $pattern = '/\b(Samsung|LG|Sony|Hisense|Philips|TCL|Panasonic|Vox|Grundig|Sharp|Xiaomi|Vivax|Tesla)\b/i';
        if (preg_match(pattern: $pattern, subject: $text, matches: $matches)) {
            return ucfirst(string: strtolower(string: $matches[1]));
        }

        return null;
    }

    /**
     * 🖼️ Extracts the product image URL.
     *
     * Tries several fallbacks:
     * 1️⃣ <source srcset="...">
     * 2️⃣ <img src="...">
     * 3️⃣ <div style="background-image:url(...)">
     */
    private function firstImageSrc(Crawler $item) : string|null
    {
        try {
            // Try <picture><source srcset="...">
            $source = $item->filter(selector: 'picture source')->first();
            if ($source->count() > 0) {
                $srcset = $source->attr(attribute: 'srcset');
                if (! empty($srcset)) {
                    $url = explode(separator: ' ', string: trim(string: $srcset))[0];

                    return str_starts_with(haystack: $url, needle: 'http') ? $url : 'https://www.shoptok.si' . $url;
                }
            }

            // Try <img>
            $img = $item->filter(selector: 'picture img, img')->first();
            if ($img->count() > 0) {
                $src = $img->attr(attribute: 'data-src')
                    ?? $img->attr(attribute: 'data-original')
                    ?? $img->attr(attribute: 'srcset')
                    ?? $img->attr(attribute: 'src');
                if (! empty($src)) {
                    $src = explode(separator: ' ', string: trim(string: $src))[0];
                    if (str_starts_with(haystack: $src, needle: 'http')) {
                        return $src;
                    }

                    return 'https://www.shoptok.si' . $src;
                }
            }

            // Fallback: background-image
            $div = $item->filter(selector: 'div.img_');
            if ($div->count() > 0) {
                $style = $div->attr(attribute: 'style');
                if (preg_match(pattern: '/url\((.*?)\)/', subject: $style, matches: $matches)) {
                    $url = trim(string: $matches[1], characters: '\'" ');

                    return str_starts_with(haystack: $url, needle: 'http') ? $url : 'https://www.shoptok.si' . $url;
                }
            }

            return null;
        } catch (Exception $e) {
            Log::warning(message: 'Image parse failed', context: ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * 🧩 Generates a unique, stable external ID for each product.
     * Uses a SHA256 hash of its normalized URL.
     */
    private function makeExternalId(string $url) : string
    {
        return hash(algo: 'sha256', data: $this->normalizeUrl(url: $url));
    }

    /**
     * 🌐 Normalizes URLs (adds domain if missing)
     */
    private function normalizeUrl(string $url) : string
    {
        if (str_starts_with(haystack: $url, needle: 'http://') || str_starts_with(haystack: $url, needle: 'https://')) {
            return $url;
        }

        return 'https://www.shoptok.si' . $url;
    }

    /**
     * 🔎 Finds all DOM nodes that represent products on the page.
     *
     * It uses a robust selector that matches multiple possible layouts
     * Shoptok uses for product grids.
     *
     * Example:
     * <div class="product">...</div>
     * <div class="b-paging-product">...</div>
     */
    public function findProductNodes(Crawler $dom) : Crawler
    {
        return $dom->filterXPath(
            xpath: "//div[contains(concat(' ', normalize-space(@class), ' '), ' product ')]
             | //div[contains(concat(' ', normalize-space(@class), ' '), ' b-paging-product ')]"
        );
    }
}
