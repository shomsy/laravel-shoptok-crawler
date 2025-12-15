<?php

declare(strict_types=1);

namespace App\Services\Shoptok;

use Symfony\Component\DomCrawler\Crawler;

/**
 * 🧐 **The Detective (Parser Service)**
 *
 * This class looks at the chaotic raw HTML code and searches for clues.
 * Its job is to find structure where there is none.
 *
 * **What it does:**
 * - It scans the HTML for blocks that look like products (cards).
 * - It extracts the *Price*, *Name*, and *Image* from those blocks.
 * - It cleans up messy data (like "1.299,00 €" -> 1299.00).
 */
final class ShoptokProductParserService
{
    /**
     * **Investigate a single Item Block**
     *
     * Takes a specific piece of HTML (one product card) and extracts the facts.
     *
     * @return array|null Returns the organized data, or NULL if it's just an ad/junk.
     */
    public function parseItem(Crawler $item): ?array
    {
        $name = $this->firstLinkText(item: $item);
        $url = $this->firstLinkHref(item: $item);

        if ($name === null || $url === null) {
            return null;
        }

        $price = $this->extractPriceFromText(text: $item->text(default: ''));

        return [
            'external_id' => $this->makeExternalId(url: $url),
            'name' => $name,
            'price' => $price,
            'currency' => 'EUR',
            'image_url' => $this->firstImageSrc(item: $item),
            'product_url' => $this->normalizeUrl(url: $url),
        ];
    }

    private function firstLinkText(Crawler $item): ?string
    {
        $links = $item->filter(selector: 'a');
        if ($links->count() === 0) {
            return null;
        }

        // Prvi “normalan” link koji nije CTA tipa “Primerjaj cene” / “Več o ponudbi”
        foreach ($links as $a) {
            $text = trim(string: $a->textContent ?? '');
            if ($text === '' || $this->isCtaText(text: $text)) {
                continue;
            }
            return $text;
        }

        return null;
    }

    private function isCtaText(string $text): bool
    {
        $t = mb_strtolower(string: $text);

        return str_contains(haystack: $t, needle: 'primerjaj cene')
            || str_contains(haystack: $t, needle: 'več o ponudbi')
            || str_contains(haystack: $t, needle: 'vec o ponudbi');
    }

    private function firstLinkHref(Crawler $item): ?string
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

    private function extractPriceFromText(string $text): float
    {
        // hvata npr: "od 1.799,99 €" ili "412,90 €"
        if (!preg_match(pattern: '/(\d{1,3}(\.\d{3})*|\d+),\d{2}\s*€/', subject: $text, matches: $m)) {
            if (!preg_match(pattern: '/(\d{1,3}(\.\d{3})*|\d+)\s*€/', subject: $text, matches: $m2)) {
                return 0.0;
            }
            $rawInt = str_replace(search: '.', replace: '', subject: $m2[1]);
            return (float)$rawInt;
        }

        $raw = str_replace(search: '.', replace: '', subject: $m[1]);       // "1.799" -> "1799"
        $raw = str_replace(search: ',', replace: '.', subject: $raw);       // "1799,99" -> "1799.99"

        return (float)$raw;
    }

    private function makeExternalId(string $url): string
    {
        return hash(algo: 'sha256', data: $this->normalizeUrl(url: $url));
    }

    private function normalizeUrl(string $url): string
    {
        // Shoptok često daje relativne linkove, a nekad absolute. Normalizujemo.
        if (str_starts_with(haystack: $url, needle: 'http://') || str_starts_with(haystack: $url, needle: 'https://')) {
            return $url;
        }

        return 'https://www.shoptok.si' . $url;
    }

    private function firstImageSrc(Crawler $item): ?string
    {
        $img = $item->filter(selector: 'img');
        if ($img->count() === 0) {
            return null;
        }

        $src = $img->first()->attr(attribute: 'src');
        if ($src === null || $src === '') {
            return null;
        }

        return $src;
    }

    /**
     * Heuristic extraction of product containers.
     * We locate CTA links ("Primerjaj cene", "Več o ponudbi")
     * and walk up the DOM tree to find a stable container node.
     */
    public function findProductNodes(Crawler $dom): Crawler
    {
        $ctaLinks = $dom->filterXPath(
            xpath: "//a[
                contains(
                    translate(normalize-space(.),
                        'ABCDEFGHIJKLMNOPQRSTUVWXYZČŠŽ',
                        'abcdefghijklmnopqrstuvwxyzčšž'
                    ),
                    'primerjaj cene'
                )
                or
                contains(
                    translate(normalize-space(.),
                        'ABCDEFGHIJKLMNOPQRSTUVWXYZČŠŽ',
                        'abcdefghijklmnopqrstuvwxyzčšž'
                    ),
                    'več o ponudbi'
                )
                or
                contains(
                    translate(normalize-space(.),
                        'ABCDEFGHIJKLMNOPQRSTUVWXYZČŠŽ',
                        'abcdefghijklmnopqrstuvwxyzčšž'
                    ),
                    'vec o ponudbi'
                )
            ]"
        );

        if ($ctaLinks->count() === 0) {
            return new Crawler();
        }

        $containers = [];

        foreach ($ctaLinks as $link) {
            $node = $link;

            // climb up DOM tree (limited depth)
            for ($depth = 0; $depth < 6; $depth++) {
                if ($node === null || $node->parentNode === null) {
                    break;
                }

                $node = $node->parentNode;

                $tag = strtolower((string)$node->nodeName);

                if (in_array(needle: $tag, haystack: ['li', 'article', 'div'], strict: true)) {
                    $containers[spl_object_hash(object: $node)] = $node;
                    break;
                }
            }
        }

        return new Crawler(node: array_values(array: $containers));
    }
}
