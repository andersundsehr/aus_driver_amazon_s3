<?php

declare(strict_types=1);

namespace AUS\AusDriverAmazonS3\Tests\Unit\Driver;

use ReflectionProperty;
use RuntimeException;
use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

final class AmazonS3DriverMutationTest extends TestCase
{
    private string $directory;

    private VariableFrontend $metadata;

    private VariableFrontend $requests;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/s3-mutation-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->metadata = new VariableFrontend('metadata', new TransientMemoryBackend('Testing'));
        $this->requests = new VariableFrontend('requests', new TransientMemoryBackend('Testing'));
        $this->requests->set('listing', ['Contents' => [['Key' => 'source.txt']]]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    private function driver(array $extraMethods = []): AmazonS3Driver
    {
        $driver = $this->getMockBuilder(AmazonS3Driver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array_merge(['getStreamWrapperPath', 'sanitizeFileName'], $extraMethods))
            ->getMock();
        $driver->method('getStreamWrapperPath')->willReturnCallback(
            fn($identifier): string => $this->directory . '/' . basename($identifier)
        );
        $driver->method('sanitizeFileName')->willReturnArgument(0);
        foreach (['metaInfoCache' => $this->metadata, 'requestCache' => $this->requests] as $property => $value) {
            (new ReflectionProperty(AmazonS3Driver::class, $property))->setValue($driver, $value);
        }

        return $driver;
    }

    public static function contents(): array
    {
        return [['longer contents'], ['']];
    }

    #[DataProvider('contents')]
    public function testWritingInvalidatesMetadataAndListings(string $contents): void
    {
        $driver = $this->driver();
        $this->metadata->set(md5('source.txt'), ['size' => 3]);
        file_put_contents($this->directory . '/source.txt', 'old');

        self::assertSame(strlen($contents), $driver->setFileContents('/source.txt', $contents));
        self::assertSame($contents, file_get_contents($this->directory . '/source.txt'));
        self::assertFalse($this->metadata->has(md5('source.txt')));
        self::assertFalse($this->requests->has('listing'));
    }

    public function testEmptyReplacementSucceedsAndInvalidatesCaches(): void
    {
        $driver = $this->driver();
        $this->metadata->set(md5('target.txt'), ['size' => 3]);
        file_put_contents($this->directory . '/target.txt', 'old');
        touch($this->directory . '/empty.txt');

        self::assertTrue($driver->replaceFile('target.txt', $this->directory . '/empty.txt'));
        self::assertSame('', file_get_contents($this->directory . '/target.txt'));
        self::assertFalse($this->metadata->has(md5('target.txt')));
        self::assertFalse($this->requests->has('listing'));
    }

    #[DataProvider('contents')]
    public function testFlushFailureThrowsAndClosesStream(string $contents): void
    {
        stream_wrapper_register('s3flushfailure', FailingFlushStream::class);
        FailingFlushStream::$closed = false;
        $driver = $this->getMockBuilder(AmazonS3Driver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStreamWrapperPath', 'flushMetaInfoCache'])
            ->getMock();
        $driver->method('getStreamWrapperPath')->willReturn('s3flushfailure://file');
        $driver->expects(self::never())->method('flushMetaInfoCache');

        try {
            $this->expectException(RuntimeException::class);
            $driver->setFileContents('file', $contents);
        } finally {
            stream_wrapper_unregister('s3flushfailure');
            self::assertTrue(FailingFlushStream::$closed);
        }
    }

    public function testAddFileMoveInvalidatesBothIdentifiers(): void
    {
        $driver = $this->driver();
        $this->metadata->set(md5('source.txt'), ['size' => 3]);
        $this->metadata->set(md5('target.txt'), ['size' => 6]);
        file_put_contents($this->directory . '/source.txt', 'new');
        file_put_contents($this->directory . '/target.txt', 'oldold');

        self::assertSame('target.txt', $driver->addFile('source.txt', '', 'target.txt', true));
        self::assertFileDoesNotExist($this->directory . '/source.txt');
        self::assertSame('new', file_get_contents($this->directory . '/target.txt'));
        self::assertFalse($this->metadata->has(md5('source.txt')));
        self::assertFalse($this->metadata->has(md5('target.txt')));
        self::assertFalse($this->requests->has('listing'));
    }

    public function testRecursiveDeletionInvalidatesChildMetadata(): void
    {
        $driver = $this->driver(['getListObjects', 'deleteObject']);
        $driver->method('getListObjects')->willReturn(['Contents' => [['Key' => 'folder/child.txt']]]);
        $driver->expects(self::once())->method('deleteObject')->with('folder/')->willReturn(true);
        $this->metadata->set(md5('folder/child.txt'), ['size' => 3]);
        file_put_contents($this->directory . '/child.txt', 'old');

        self::assertTrue($driver->deleteFolder('folder/', true));
        self::assertFileDoesNotExist($this->directory . '/child.txt');
        self::assertFalse($this->metadata->has(md5('folder/child.txt')));
    }
}
