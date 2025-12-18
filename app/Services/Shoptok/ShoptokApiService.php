<?php

declare(strict_types=1);

namespace App\Services\Shoptok;

use App\Data\Shoptok\CrawlResult;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🌐 ShoptokApiService
 *
 * Handles low-level HTTP communication with the Shoptok website.
 * Used by crawler services to fetch raw HTML pages safely and efficiently.
 *
 * Responsibilities:
 * - Sends GET requests with retry logic and custom headers.
 * - Maintains cookies between requests to appear as a real browser.
 * - Detects blocks by Shoptok’s WAF (403).
 * - Wraps the result in a {@see CrawlResult} object for consistency.
 */
final class ShoptokApiService
{
    /** Default HTTP timeout (in seconds). */
    private const TIMEOUT_SECONDS = 20;

    /** Number of automatic retries for transient errors. */
    private const RETRY_TIMES = 2;

    /** Delay between retries (in milliseconds). */
    private const RETRY_SLEEP_MS = 1500;

    /** Shared cookie jar for all requests (simulates browser session). */
    private readonly CookieJar $cookieJar;

    public function __construct()
    {
        $this->cookieJar = new CookieJar();
    }

    /**
     * Fetches a Shoptok page and returns its HTML content.
     *
     * - Returns `null` if blocked by WAF (403).
     * - Retries failed requests up to {@see RETRY_TIMES}.
     * - Throws for unrecoverable network or server errors.
     *
     * @param string $url The full URL to fetch.
     *
     * @return CrawlResult|null The HTML and metadata, or null if blocked.
     *
     * @throws RequestException|ConnectionException
     */
    public function getHtml(string $url) : CrawlResult|null
    {
        $startTime = microtime(as_float: true);

        $response = $this->makeRequest(url: $url);

        // Block detection (403 Forbidden → WAF)
        if ($this->isBlocked(response: $response)) {
            Log::warning(message: 'Shoptok request blocked by WAF', context: [
                'url'    => $url,
                'status' => $response->status(),
            ]);

            return null;
        }

        // Throw for other HTTP or connection issues
        if (! $response->successful()) {
            $response->throw();
        }

        $duration = microtime(as_float: true) - $startTime;

        return new CrawlResult(
            html         : $response->body(),
            url          : $url,
            executionTime: round(num: $duration, precision: 4)
        );
    }

    /**
     * Performs the actual HTTP request with retry and cookie persistence.
     *
     * @throws ConnectionException
     */
    private function makeRequest(string $url) : Response
    {
        return Http::timeout(seconds: self::TIMEOUT_SECONDS)
            ->retry(
                times            : self::RETRY_TIMES,
                sleepMilliseconds: self::RETRY_SLEEP_MS,
                throw            : false // Don’t throw on 4xx (handled manually)
            )
            ->withOptions(options: [
                                       'cookies' => $this->cookieJar,
                                       'debug'   => false,
                                   ])
            ->withHeaders(headers: $this->defaultHeaders())
            ->get(url: $url);
    }

    /**
     * Default HTTP headers to mimic a real browser.
     *
     * These headers (User-Agent, Accept-Language, etc.)
     * are loaded from `config/shoptok.php` for easy maintenance.
     */
    private function defaultHeaders() : array
    {
        return config(key: 'shoptok.headers', default: []);
    }

    /**
     * Detects if the request was blocked by Shoptok’s firewall.
     */
    private function isBlocked(Response $response) : bool
    {
        return $response->status() === 403;
    }
}
