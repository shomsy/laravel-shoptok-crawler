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

        $dom = new Crawler($html);
        $node = $dom->filter('.product')->first();

        // 2. Instantiate Service
        $parser = new ShoptokProductParserService();

        // 3. Act
        $result = $parser->parseItem($node);

        // 4. Assert
        $this->assertNotNull($result, "Parser returned null for valid HTML.");
        $this->assertEquals('Hisense 55E7HQ 55" 4K Smart TV', $result['name']);
        $this->assertEquals('Hisense', $result['brand']);
        $this->assertEquals(379.99, $result['price']);
        $this->assertEquals('https://www.shoptok.si/hisense-tv/cena/12345', $result['product_url']);
        // Check image fallback logic
        $this->assertEquals('https://img.cdn.com/hisense.jpg', $result['image_url']);
    }

    /**
     * Test logic: Parser correctly handles "Strict Class Matching" to avoid ads.
     */
    public function test_parser_ignores_invalid_nodes()
    {
        // HTML that looks like a product but missing key class or link
        $html = '<div class="ad-banner"><h3>Ad Title</h3></div>';

        $dom = new Crawler($html);
        $node = $dom->filter('div')->first();

        $parser = new ShoptokProductParserService();

        $result = $parser->parseItem($node);

        $this->assertNull($result, "Parser should return null for non-product nodes.");
    }

    /**
     * Test logic: Price extraction handles complex formats ("od 1.200,00 €").
     */
    public function test_price_extraction_robustness()
    {
        // Use reflection to test private method or just integration test via parseItem
        // We will use parseItem with manipulated HTML
        $html = <<<HTML
        <div class="product">
            <h3><a href="/p">Test</a></h3>
            <div>od 1.299,50 €</div>
        </div>
HTML;
        $dom = new Crawler($html);
        $node = $dom->filter('.product')->first();
        $parser = new ShoptokProductParserService();
        $result = $parser->parseItem($node);

        $this->assertEquals(1299.50, $result['price']);
    }
}
