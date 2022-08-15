<?php

namespace Sunlight\Util;

use Kuria\Debug\Dumper;
use Kuria\Url\Url;
use Sunlight\Core;

/**
 * Minimalistic HTTP client
 *
 * Supported $options:
 *
 *  timeout     request timeout in seconds (float, default = 60, null/<=0 means unlimited)
 *  headers     numerically indexed list of additional header strings
 *  user_agent  user agent string
 */
class HttpClient
{
    /**
     * Perform a GET request
     *
     * @param array $options see class description
     * @throws HttpClientException on failure
     */
    static function get(string $url, array $options = []): string
    {
        return self::request($url, null, $options);
    }

    /**
     * Perform a POST request
     *
     * @param array $options see class description
     * @throws HttpClientException on failure
     */
    static function post(string $url, string $body, array $options = []): string
    {
        return self::request($url, $body, $options);
    }

    private static function request(string $url, ?string $body, array $options): string
    {
        self::validateUrl($url);

        $options += [
            'timeout' => 60,
            'user_agent' => 'SLHttpClient/' . Core::VERSION,
            'headers' => [],
        ];

        if (isset($options['timeout']) && $options['timeout'] <= 0) {
            $options['timeout'] = null;
        }

        if (extension_loaded('curl')) {
            return self::curlRequest($url, $body, $options);
        }

        if (ini_get('allow_url_fopen')) {
            return self::nativeRequest($url, $body, $options);
        }

        throw new HttpClientException('No available transport');
    }

    private static function validateUrl(string $url): void
    {
        try {
            $url = Url::parse($url);
        } catch (\InvalidArgumentException $e) {
            throw new HttpClientException($e->getMessage(), 0, $e);
        }

        if (!$url->hasHost()) {
            throw new HttpClientException('URL does not have a host');
        }

        if ($url->getScheme() !== 'http' && $url->getScheme() !== 'https') {
            throw new HttpClientException(sprintf('Expected http or https scheme, got %s', Dumper::dump($url->getScheme())));
        }
    }

    private static function curlRequest(string $url, ?string $body, array $options): string
    {
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] * 1000 : 0;
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT_MS => $timeout,
            CURLOPT_TIMEOUT_MS => $timeout,
            CURLOPT_USERAGENT => $options['user_agent'],
            CURLOPT_HTTPHEADER => $options['headers'],
        ];

        if ($body !== null) {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = 'POST';
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        $c = curl_init($url);
        curl_setopt_array($c, $curlOptions);

        $response = curl_exec($c);

        if ($response === false) {
            throw new HttpClientException(
                curl_error($c),
                (int) curl_getinfo($c, CURLINFO_HTTP_CODE)
            );
        }

        return $response;
    }

    private static function nativeRequest(string $url, ?string $body, array $options): string
    {
        $contextOptions = [
            'http' => [
                'timeout' => isset($options['timeout']) ? (float) $options['timeout'] : -1,
                'user_agent' => $options['user_agent'],
                'header' => $options['headers'],
            ],
        ];

        if ($body !== null) {
            $contextOptions['http'] += [
                'method' => 'POST',
                'content' => $body,
            ];
        }

        $response = @file_get_contents(
            $url,
            false,
            stream_context_create($contextOptions)
        );

        if ($response === false) {
            if (isset($http_response_header[0]) && preg_match('{^HTTP/[^ ]+ +(\d+)}', $http_response_header[0], $statusMatch)) {
                $statusCode = (int) $statusMatch[1];
                $message = sprintf('Request failed - HTTP %d', $statusCode);
            } else {
                $statusCode = 0;
                $message = 'Request failed';
            }

            throw new HttpClientException($message, $statusCode);
        }

        return $response;
    }
}
