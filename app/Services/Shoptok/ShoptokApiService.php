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

final class ShoptokApiService
{
    private const TIMEOUT_SECONDS = 20;
    private const RETRY_TIMES     = 2;
    private const RETRY_SLEEP_MS  = 1500;

    // Persist cookies across requests
    private readonly CookieJar $cookieJar;

    public function __construct()
    {
        $this->cookieJar = new CookieJar();
    }

    /**
     * Fetch raw HTML from Shoptok via HTTP.
     *
     * - Returns null if request is blocked (403 / WAF)
     * - Throws for unexpected HTTP errors (5xx, network issues)
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public function getHtml(string $url) : ?CrawlResult
    {
        $startTime = microtime(true);

        $response = $this->makeRequest(url: $url);

        if ($this->isBlocked(response: $response)) {
            Log::warning(message: 'Shoptok request blocked by WAF (403)', context: [
                'url'    => $url,
                'status' => $response->status(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            // Legitimate application or network failure
            $response->throw();
        }

        $duration = microtime(true) - $startTime;

        return new CrawlResult(
            html         : $response->body(),
            url          : $url,
            executionTime: round($duration, 4)
        );
    }

    /**
     * @throws ConnectionException
     */
    private function makeRequest(string $url) : Response
    {
        return Http::timeout(seconds: self::TIMEOUT_SECONDS)
            ->retry(
                times            : self::RETRY_TIMES,
                sleepMilliseconds: self::RETRY_SLEEP_MS,
                throw            : false // 🔑 critical: do NOT throw on 4xx
            )
            ->withOptions([
                              'cookies' => $this->cookieJar, // 🍪 Use persistent cookie jar
                              'debug'   => false,
                          ])
            ->withHeaders(headers: $this->defaultHeaders())
            ->get(url: $url);
    }

    private function defaultHeaders() : array
    {
        return config(key: 'shoptok.headers', default: []);
    }

    private function isBlocked(Response $response) : bool
    {
        return $response->status() === 403;
    }
}
