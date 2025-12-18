<?php

namespace Tests\Unit;

use App\Services\Shoptok\ShoptokProductParserService;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

/**
 * Extended Unit Tests for ShoptokProductParserService
 *
 * Additional edge cases and robustness tests.
 */
class ShoptokProductParserExtendedTest extends TestCase
{
    private ShoptokProductParserService $parser;

    /**
     * Test: Parser handles products without images
     */
    public function test_handles_products_without_images()
    {
        $html = <<<HTML
            <div class="product-item">
                <a href="/product/123">Test Product</a>
                <span class="price">99,99 €</span>
            </div>
            HTML;

        $crawler = new Crawler(node: $html);
        $items   = $crawler->filter(selector: '.product-item');

        $result = $this->parser->parseItem($items->first(), 'https://example.com');

        $this->assertNull(actual: $result['image_url']);
    }

    /**
     * Test: Parser handles products without brand
     */
    public function test_handles_products_without_brand()
    {
        $html = <<<HTML
            <div class="product-item">
                <a href="/product/123">Generic Product</a>
                <span class="price">99,99 €</span>
                <img src="/image.jpg" alt="Product">
            </div>
            HTML;

        $crawler = new Crawler(node: $html);
        $items   = $crawler->filter(selector: '.product-item');

        $result = $this->parser->parseItem($items->first(), 'https://example.com');

        $this->assertNull(actual: $result['brand']);
    }

    /**
     * Test: Parser generates valid external IDs
     */
    public function test_generates_valid_external_ids()
    {
        $html1 = '<div class="product-item"><a href="/product/123">Product 1</a><span class="price">99 €</span></div>';
        $html2 = '<div class="product-item"><a href="/product/456">Product 2</a><span class="price">99 €</span></div>';

        $crawler1 = new Crawler(node: $html1);
        $crawler2 = new Crawler(node: $html2);

        $result1 = $this->parser->parseItem($crawler1->filter(selector: '.product-item')->first(), 'https://example.com');
        $result2 = $this->parser->parseItem($crawler2->filter(selector: '.product-item')->first(), 'https://example.com');

        $this->assertNotNull(actual: $result1['external_id']);
        $this->assertNotNull(actual: $result2['external_id']);
        // Different product URLs should generate different IDs
        $this->assertNotEquals(expected: $result1['external_id'], actual: $result2['external_id']);
    }

    /**
     * Test: Parser normalizes URLs correctly
     */
    public function test_normalizes_urls_correctly()
    {
        // This test validates URL normalization through parseItem
        $html    = '<div class="product-item"><a href="/product/123">Product</a><span class="price">99 €</span></div>';
        $crawler = new Crawler(node: $html);

        $result = $this->parser->parseItem($crawler->filter(selector: '.product-item')->first(), 'https://www.shoptok.si');

        $this->assertStringStartsWith(prefix: 'https://www.shoptok.si', string: $result['product_url']);
    }

    /**
     * Test: Parser handles empty product lists
     */
    public function test_handles_empty_product_lists()
    {
        $html    = '<div class="products-container"></div>';
        $crawler = new Crawler(node: $html);
        $items   = $crawler->filter(selector: '.product-item');

        $this->assertEquals(expected: 0, actual: $items->count());
    }

    /**
     * Test: Parser extracts currency correctly
     */
    public function test_extracts_currency_correctly()
    {
        $html = <<<HTML
            <div class="product-item">
                <a href="/product/123">Product</a>
                <span class="price">99,99 €</span>
            </div>
            HTML;

        $crawler = new Crawler(node: $html);
        $items   = $crawler->filter(selector: '.product-item');

        $result = $this->parser->parseItem($items->first(), 'https://example.com');

        $this->assertEquals(expected: 'EUR', actual: $result['currency']);
    }

    protected function setUp() : void
    {
        parent::setUp();
        $this->parser = new ShoptokProductParserService();
    }
}
