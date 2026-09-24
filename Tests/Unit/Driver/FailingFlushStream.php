<?php

declare(strict_types=1);

namespace AUS\AusDriverAmazonS3\Tests\Unit\Driver;

final class FailingFlushStream
{
    public $context;

    public static bool $closed = false;

    // phpcs:disable PSR1.Methods.CamelCapsMethodName
    public function stream_open(): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        return strlen($data);
    }

    public function stream_flush(): bool
    {
        return false;
    }

    public function stream_close(): void
    {
        self::$closed = true;
    }

    // phpcs:enable PSR1.Methods.CamelCapsMethodName
}
