<?php

namespace GuzzleHttp\Handler;

use GuzzleHttp\Utils;

/**
 * @internal
 */
final class HeaderProcessor
{
    /**
     * Returns the HTTP version, status code, reason phrase, and headers.
     *
     * @param string[] $headers
     *
     * @return array{0:string, 1:int, 2:?string, 3:array}
     *
     * @throws \RuntimeException
     */
    public static function parseHeaders(array $headers): array
    {
        if ($headers === []) {
            throw new \RuntimeException('Expected a non-empty array of header data', 4914296141);
        }

        $parts = \explode(' ', \array_shift($headers), 3);
        $version = \explode('/', $parts[0])[1] ?? null;

        if ($version === null) {
            throw new \RuntimeException('HTTP version missing from header data', 1366490620);
        }

        $status = $parts[1] ?? null;

        if ($status === null) {
            throw new \RuntimeException('HTTP status code missing from header data', 3817700241);
        }

        return [$version, (int) $status, $parts[2] ?? null, Utils::headersFromLines($headers)];
    }
}
