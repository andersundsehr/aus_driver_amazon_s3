<?php

declare(strict_types=1);

namespace AUS\AusDriverAmazonS3\Tests\Unit\S3Adapter;

use AUS\AusDriverAmazonS3\S3Adapter\MultipartUploaderAdapter;
use PHPUnit\Framework\TestCase;

class MultipartUploaderAdapterTest extends TestCase
{
    private MultipartUploaderAdapter $multipartUploaderAdapter;

    public function setUp(): void
    {
        parent::setUp();
        $this->multipartUploaderAdapter = new MultipartUploaderAdapter();
    }

    /**
     * @test
     */
    public function detectContentTypeTest()
    {
        $fixtures = dirname(__FILE__) . '/../Fixtures/MultipartUploaderAdapter/';

        $this->assertEquals('application/javascript', $this->multipartUploaderAdapter->detectContentType($fixtures . 'js.js', 'js.js'));
        $this->assertEquals('image/png', $this->multipartUploaderAdapter->detectContentType($fixtures . 'png', 'png'));
        $this->assertEquals('image/png', $this->multipartUploaderAdapter->detectContentType($fixtures . 'png.png', 'png.png'));
        $this->assertEquals('image/svg+xml', $this->multipartUploaderAdapter->detectContentType($fixtures . 'svg.svg', 'svg.svg'));
        $this->assertEquals('image/svg+xml', $this->multipartUploaderAdapter->detectContentType($fixtures . 'svg-xml.svg', 'svg-xml.svg'));
        $this->assertEquals('text/plain', $this->multipartUploaderAdapter->detectContentType($fixtures . 'text.txt', 'text.txt'));
    }
}
