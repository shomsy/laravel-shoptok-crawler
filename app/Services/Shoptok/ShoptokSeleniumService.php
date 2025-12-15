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
 * 🤖 **The Browser Operator (Selenium Service)**
 *
 * This class is responsible for driving the "invisible" Chrome browser.
 *
 * **Why use this instead of a simple request?**
 * Many modern sites use JavaScript to load products or have "Are you a robot?" checks.
 * This service launches a real browser session, waits for the page to "settle" (load JS),
 * and tricks the server into thinking we are a real human on a laptop.
 *
 * **Key responsibilities:**
 * 1. Start a fresh browser (with a fake User-Agent).
 * 2. Go to a URL.
 * 3. Wait for the content to actually appear.
 * 4. Return the raw HTML for parsing.
 */
final readonly class ShoptokSeleniumService
{
    private const DEFAULT_TIMEOUT_MS = 15000;
    private const REQUEST_TIMEOUT_MS = 45000;
    private const WAIT_TIMEOUT_SEC = 10;

    public function __construct(
        private LoggerInterface  $logger,
        private ConfigRepository $config,
    ) {}

    /**
     * Fetch HTML content from the target URL via Selenium.
     *
     * @param string $url
     * @return CrawlResult Result object containing HTML and metadata.
     * @throws RuntimeException If crawling fails definitively.
     */
    public function getHtml(string $url): CrawlResult
    {
        $startTime = microtime(true);
        $driver = null;

        try {
            $this->logger->info('Initializing Selenium fetch', ['url' => $url]);

            // 1. Initialize Driver
            $driver = $this->createDriver();

            // 2. Navigate
            $driver->get($url);

            // 3. Wait Strategy (Explicit Wait preferred over sleep)
            $this->waitForPageLoad($driver);

            // 4. Capture Content
            $html = $driver->getPageSource();

            // 5. Validation / Checks
            $this->ensureContentIsValid($html, $url);

            $duration = microtime(true) - $startTime;

            return new CrawlResult(
                html: $html,
                url: $url,
                executionTime: round($duration, 4)
            );
        } catch (Throwable $e) {
            $this->logger->error('Selenium crawl failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException("Failed to crawl URL: {$url}", previous: $e);
        } finally {
            // Guarantee resource cleanup
            $driver?->quit();
        }
    }

    /**
     * Configures and creates the WebDriver instance.
     */
    private function createDriver(): RemoteWebDriver
    {
        $seleniumUrl = $this->config->get('services.selenium.host', 'http://selenium:4444/wd/hub');

        $options = new ChromeOptions();
        $options->addArguments([
            '--headless',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-blink-features=AutomationControlled',
            '--remote-debugging-port=' . random_int(9000, 9999),
            '--user-agent=' . $this->getUserAgent(),
        ]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        // Ideally, RemoteWebDriver::create should be wrapped in a factory to be mockable,
        // but for a pragmatic Laravel service, this is acceptable if integration tests are used.
        return RemoteWebDriver::create(
            selenium_server_url: $seleniumUrl,
            desired_capabilities: $capabilities,
            connection_timeout_in_ms: self::DEFAULT_TIMEOUT_MS,
            request_timeout_in_ms: self::REQUEST_TIMEOUT_MS,
        );
    }

    private function getUserAgent(): string
    {
        return $this->config->get(key: 'shoptok.headers.User-Agent', default: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');
    }

    private function waitForPageLoad(RemoteWebDriver $driver): void
    {
        // Wait until <body> is present in DOM
        $driver->wait(self::WAIT_TIMEOUT_SEC)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('body'))
        );

        // Heuristic delay for Hydration/JS-heavy sites (Cloudflare check might need this)
        usleep(500000); // 500ms
    }

    private function ensureContentIsValid(string $html, string $url): void
    {
        if (str_contains($html, 'Just a moment...') || str_contains($html, 'cf-browser-verification')) {
            $this->logger->warning('Cloudflare challenge detected.', ['url' => $url]);
            // We specifically do NOT throw here if we want to return the challenge HTML for debugging,
            // OR we throw a specialized RateLimitException if we want to retry later.
            // For now, logging behavior matches your original intent.
        }
    }
}
