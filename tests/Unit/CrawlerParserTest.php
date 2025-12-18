<?php

namespace Tests\Unit;

use App\Services\Shoptok\ShoptokProductParserService;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class CrawlerParserTest extends TestCase
{
    /**
     * Test logic: Parser Service correctly scrapes data from HTML block.
     */
    public function test_parser_extracts_product_data()
    {
        // 1. Mock HTML (Simulating a real Shoptok product card)
        $html = <<<HTML
            <div class="product">
                <h3 event-viewitem-brand="Hisense">
                    <a href="/hisense-tv/cena/12345">Hisense 55E7HQ 55" 4K Smart TV</a>
                </h3>
                <div class="price-box">
                    <span class="price">379,99 €</span>
                </div>
                <div class="img_" style="background-image:url('https://img.cdn.com/hisense.jpg')"></div>
            </div>
            HTML;

        $dom  = new Crawler(node: $html);
        $node = $dom->filter(selector: '.product')->first();

        // 2. Instantiate Service
        $parser = new ShoptokProductParserService();

        // 3. Act
        $result = $parser->parseItem(item: $node);

        // 4. Assert
        $this->assertNotNull(actual: $result, message: "Parser returned null for valid HTML.");
        $this->assertEquals(expected: 'Hisense 55E7HQ 55" 4K Smart TV', actual: $result['name']);
        $this->assertEquals(expected: 'Hisense', actual: $result['brand']);
        $this->assertEquals(expected: 379.99, actual: $result['price']);
        $this->assertEquals(expected: 'https://www.shoptok.si/hisense-tv/cena/12345', actual: $result['product_url']);
        // Check image fallback logic
        $this->assertEquals(expected: 'https://img.cdn.com/hisense.jpg', actual: $result['image_url']);
    }

    /**
     * Test logic: Parser correctly handles "Strict Class Matching" to avoid ads.
     */
    public function test_parser_ignores_invalid_nodes()
    {
        // HTML that looks like a product but missing key class or link
        $html = '<div class="ad-banner"><h3>Ad Title</h3></div>';

        $dom  = new Crawler(node: $html);
        $node = $dom->filter(selector: 'div')->first();

        $parser = new ShoptokProductParserService();

        $result = $parser->parseItem(item: $node);

        $this->assertNull(actual: $result, message: "Parser should return null for non-product nodes.");
    }

    /**
     * Test logic: Price extraction handles complex formats ("od 1.200,00 €").
     */
    public function test_price_extraction_robustness()
    {
        // Use reflection to test private method or just integration test via parseItem
        // We will use parseItem with manipulated HTML
        $html   = <<<HTML
            <div class="product">
                <h3><a href="/p">Test</a></h3>
                <div>od 1.299,50 €</div>
            </div>
            HTML;
        $dom    = new Crawler(node: $html);
        $node   = $dom->filter(selector: '.product')->first();
        $parser = new ShoptokProductParserService();
        $result = $parser->parseItem(item: $node);

        $this->assertEquals(expected: 1299.50, actual: $result['price']);
    }
}
