<?php

namespace Tests\Unit;

use App\Services\Shoptok\ShoptokCategoryParserService;
use Tests\TestCase;

/**
 * Unit Tests for ShoptokCategoryParserService
 * 
 * Tests the service responsible for extracting subcategory links from HTML.
 */
class ShoptokCategoryParserServiceTest extends TestCase
{
    private ShoptokCategoryParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ShoptokCategoryParserService();
    }

    /**
     * Test: Parse subcategory links from HTML
     */
    public function test_parses_subcategory_links()
    {
        $html = <<<HTML
        <html>
            <body>
                <a href="/televizorji/cene/206">Televizorji</a>
                <a href="/tv-dodatki/cene/258">TV dodatki</a>
                <a href="/other-category/123">Other</a>
            </body>
        </html>
        HTML;

        $result = $this->parser->parseSubcategories($html);

        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Only Televizorji and TV dodatki

        foreach ($result as $item) {
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('url', $item);
        }
    }

    /**
     * Test: Returns empty array when no subcategories found
     */
    public function test_returns_empty_array_when_no_subcategories()
    {
        $html = '<html><body><p>No categories here</p></body></html>';

        $result = $this->parser->parseSubcategories($html);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test: Handles malformed HTML gracefully
     */
    public function test_handles_malformed_html()
    {
        $html = '<html><body><div class="broken';

        $result = $this->parser->parseSubcategories($html);

        $this->assertIsArray($result);
    }

    /**
     * Test: Filters out non-whitelisted categories
     */
    public function test_filters_non_whitelisted_categories()
    {
        $html = <<<HTML
        <html>
            <body>
                <a href="/televizorji/cene/206">Televizorji</a>
                <a href="/random-category/123">Random Category</a>
            </body>
        </html>
        HTML;

        $result = $this->parser->parseSubcategories($html);

        $this->assertCount(1, $result);
        $this->assertEquals('Televizorji', $result[0]['name']);
    }

    /**
     * Test: Handles empty HTML
     */
    public function test_handles_empty_html()
    {
        $result = $this->parser->parseSubcategories('');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
