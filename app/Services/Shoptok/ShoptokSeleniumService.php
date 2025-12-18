<?php

declare(strict_types=1);

namespace App\Services\Shoptok;

use App\Data\Shoptok\CrawlResult;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * 🧠 ShoptokSeleniumService
 *
 * Controls the headless Chrome browser (via Selenium) to fetch pages that
 * require JavaScript execution, CAPTCHA handling, or bot evasion.
 *
 * Used when simple HTTP requests can’t return the full rendered HTML.
 *
 * Responsibilities:
 * - Launches a fresh headless Chrome instance.
 * - Navigates to the given URL.
 * - Waits for the page to fully load and render.
 * - Returns clean HTML for parsing.
 */
final readonly class ShoptokSeleniumService
{
    /** Connection timeout for Selenium (in ms). */
    private const DEFAULT_TIMEOUT_MS = 15000;

    /** Maximum request duration (in ms). */
    private const REQUEST_TIMEOUT_MS = 60000;

    /** Explicit wait time for page readiness (in seconds). */
    private const WAIT_TIMEOUT_SEC = 10;

    public function __construct(
        private LoggerInterface  $logger,
        private ConfigRepository $config,
    ) {}

    /**
     * Fetch a fully rendered HTML page through Selenium.
     *
     * Used for Shoptok pages that require JS execution or dynamic rendering.
     *
     * @param string $url
     *
     * @return CrawlResult
     *
     * @throws RuntimeException If the crawl fails irrecoverably.
     */
    public function getHtml(string $url) : CrawlResult
    {
        $startTime = microtime(as_float: true);
        $driver    = null;

        try {
            $this->logger->info(message: 'Starting Selenium fetch', context: ['url' => $url]);

            // Create a Chrome WebDriver session
            $driver = $this->createDriver();

            // Navigate to the target URL
            $driver->get(url: $url);

            // Wait for page to finish rendering
            $this->waitForPageLoad(driver: $driver);

            // Extract full rendered HTML
            $html = $driver->getPageSource();

            // Validate that the HTML is not blocked or incomplete
            $this->ensureContentIsValid(html: $html, url: $url);

            $duration = microtime(as_float: true) - $startTime;

            return new CrawlResult(
                html         : $html,
                url          : $url,
                executionTime: round(num: $duration, precision: 4)
            );
        } catch (Throwable $e) {
            $this->logger->error(message: 'Selenium crawl failed', context: [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(message: "Failed to crawl URL: {$url}", code: 0, previous: $e);
        } finally {
            // Always close the browser session, even on failure
            $driver?->quit();
        }
    }

    /**
     * Initialize a new headless Chrome WebDriver session.
     */
    private function createDriver() : RemoteWebDriver
    {
        $seleniumUrl = $this->config->get(key: 'services.selenium.host', default: 'http://selenium:4444/wd/hub');

        $options = new ChromeOptions();
        $options->addArguments(arguments: [
                                              '--headless',
                                              '--no-sandbox',
                                              '--disable-dev-shm-usage',
                                              '--disable-blink-features=AutomationControlled',
                                              '--user-agent=' . $this->getUserAgent(),
                                          ]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(name: ChromeOptions::CAPABILITY, value: $options);

        return RemoteWebDriver::create(
            selenium_server_url     : $seleniumUrl,
            desired_capabilities    : $capabilities,
            connection_timeout_in_ms: self::DEFAULT_TIMEOUT_MS,
            request_timeout_in_ms   : self::REQUEST_TIMEOUT_MS
        );
    }

    /**
     * Get the User-Agent string for Selenium sessions.
     *
     * This helps disguise requests as coming from a real browser.
     */
    private function getUserAgent() : string
    {
        return $this->config->get(
            key    : 'shoptok.headers.User-Agent',
            default: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        );
    }

    /**
     * Wait until the page’s <body> is rendered and stable.
     *
     * Prevents parsing before JavaScript content loads.
     */
    private function waitForPageLoad(RemoteWebDriver $driver) : void
    {
        $driver->wait(timeout_in_second: self::WAIT_TIMEOUT_SEC)->until(
            func_or_ec: WebDriverExpectedCondition::presenceOfElementLocated(
                          by: WebDriverBy::cssSelector(css_selector: 'body')
                      )
        );

        // Small delay for heavy JS hydration (Cloudflare, dynamic content)
        usleep(microseconds: 500_000); // 0.5s
    }

    /**
     * Check if the HTML content looks like a Cloudflare or CAPTCHA block.
     *
     * Logs a warning for visibility — doesn’t throw immediately.
     */
    private function ensureContentIsValid(string $html, string $url) : void
    {
        if (
            str_contains(haystack: $html, needle: 'Just a moment...')
            || str_contains(haystack: $html, needle: 'cf-browser-verification')
        ) {
            $this->logger->warning(message: 'Cloudflare challenge detected', context: compact(var_name: 'url'));
        }
    }
}
