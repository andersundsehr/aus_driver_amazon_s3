<?php

/***
 *
 * This file is part of an extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2020 Markus Hölzle <typo3@markus-hoelzle.de>
 *
 ***/

namespace AUS\AusDriverAmazonS3\Tests\Unit\Driver;

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use Aws\Api\DateTimeResult;
use Aws\Result;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

/**
 * Class AmazonS3DriverTest
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\Tests\Unit\Driver
 */
class AmazonS3DriverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var AmazonS3Driver
     */
    protected $driver = null;

    /**
     * @var ObjectProphecy
     */
    protected $s3Client = null;

    /**
     * @var string[]
     */
    protected $testConfiguration = [
        'protocol' => 'https://',
        'publicBaseUrl' => 'www.example.com',
        'bucket' => 'test-bucket',
        'region' => 'test-region',
        'key' => 'test-key',
        'secretKey' => 'test-secretKey',
    ];

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][AmazonS3Driver::EXTENSION_KEY] = [];
        $GLOBALS['TYPO3_CONF_VARS']['LOG'] = [];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['FileInfo']['fileExtensionToMimeType']['youtube'] = 'video/youtube';
        $GLOBALS['TSFE'] = new \stdClass();

        Environment::initialize(
            $this->prophesize(ApplicationContext::class)->reveal(),
            false,
            true,
            '',
            '',
            '',
            '',
            '',
            ''
        );
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute('applicationType')->willReturn(SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $GLOBALS['TYPO3_REQUEST'] = $request->reveal();

        $this->s3Client = $this->prophesize(S3Client::class);
        $eventDispatcher = $this->prophesize(EventDispatcher::class);
        $this->driver = new AmazonS3Driver($this->testConfiguration, $this->s3Client->reveal(), $eventDispatcher->reveal());
        $this->driver->setStorageUid(42);
        $this->driver->initialize();
    }

    public function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST'], $GLOBALS['TSFE']);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function testPublicUrlGetter()
    {
        $assertedMappings = [
            '/foo/bar/test.file' => 'https://www.example.com/foo/bar/test.file', // start with slash
            'foo/bar/test.file' => 'https://www.example.com/foo/bar/test.file', // start without slash
            '/foo//bar/test.file' => 'https://www.example.com/foo/bar/test.file', // duplicated slash in path
            '/test.file' => 'https://www.example.com/test.file', // file in root, starts with slash
            'test.file' => 'https://www.example.com/test.file', // file in root, starts without slash
        ];
        foreach ($assertedMappings as $input => $expectedOutput) {
            $this->assertEquals($expectedOutput, $this->driver->getPublicUrl($input));
        }
    }

    /**
     * @test
     */
    public function testDefaultFolderGetter()
    {
        $this->assertEquals('/', $this->driver->getDefaultFolder());
    }

    /**
     * @test
     */
    public function testRootLevelFolderGetter()
    {
        $this->assertEquals('/', $this->driver->getRootLevelFolder());
    }

    /**
     * @test
     */
    public function testGetFileInfoByIdentifier()
    {
        $fileIdentifier = 'foo/bar/test.file';
        $lastModifiedDateTime = new DateTimeResult();
        $result = new Result([
            'LastModified' => $lastModifiedDateTime,
            'ContentType' => 'image/png',
            'ContentLength' => 123,
        ]);
        $expectedInfoKeys = ['name', 'identifier', 'ctime', 'mtime', 'extension', 'mimetype', 'size', 'identifier_hash', 'folder_hash', 'storage'];
        $this->s3Client->headObject([
            'Bucket' => $this->testConfiguration['bucket'],
            'Key' => $fileIdentifier
        ])->willReturn($result);
        $info = $this->driver->getFileInfoByIdentifier($fileIdentifier);
        $infoKeys = array_keys($info);

        sort($infoKeys);
        sort($expectedInfoKeys);
        $this->assertEquals($expectedInfoKeys, $infoKeys);
        $this->assertEquals(basename($fileIdentifier), $info['name']);
        $this->assertEquals($fileIdentifier, $info['identifier']);
        $this->assertEquals($lastModifiedDateTime->getTimestamp(), $info['ctime']);
        $this->assertEquals($lastModifiedDateTime->getTimestamp(), $info['mtime']);
        $this->assertEquals(sha1('/' . $fileIdentifier), $info['identifier_hash']);
        $this->assertEquals(sha1('/' . dirname($fileIdentifier)), $info['folder_hash']);
        $this->assertEquals('file', $info['extension']);
        $this->assertEquals('image/png', $info['mimetype']);
        $this->assertEquals(123, $info['size']);
        $this->assertEquals($this->driver->getStorageUid(), $info['storage']);
    }

    /**
     * @test
     */
    public function testGetFileInfoByIdentifierWithLimitedProperties()
    {
        $fileIdentifier = 'foo/bar/test.file';
        $properties = ['name', 'identifier', 'size', 'storage'];
        $result = new Result([
            'LastModified' => new DateTimeResult(),
            'ContentType' => 'image/png',
            'ContentLength' => 123,
        ]);
        $this->s3Client->headObject([
            'Bucket' => $this->testConfiguration['bucket'],
            'Key' => $fileIdentifier
        ])->willReturn($result);
        $info = $this->driver->getFileInfoByIdentifier($fileIdentifier, $properties);
        $infoKeys = array_keys($info);
        sort($infoKeys);
        sort($properties);
        $this->assertEquals($properties, $infoKeys);
    }


    /**
     * @test
     */
    public function testGetFileInfoByIdentifierWithPseudoMimeType()
    {
        $fileIdentifier = 'foo/bar/test.youtube';
        $lastModifiedDateTime = new DateTimeResult();
        $result = new Result([
            'LastModified' => $lastModifiedDateTime,
            'ContentType' => 'text/plain',
            'ContentLength' => 12345,
        ]);
        $expectedInfoKeys = ['name', 'identifier', 'ctime', 'mtime', 'extension', 'mimetype', 'size', 'identifier_hash', 'folder_hash', 'storage'];
        $this->s3Client->headObject([
            'Bucket' => $this->testConfiguration['bucket'],
            'Key' => $fileIdentifier
        ])->willReturn($result);
        $info = $this->driver->getFileInfoByIdentifier($fileIdentifier);
        $infoKeys = array_keys($info);

        sort($infoKeys);
        sort($expectedInfoKeys);
        $this->assertEquals($expectedInfoKeys, $infoKeys);
        $this->assertEquals(basename($fileIdentifier), $info['name']);
        $this->assertEquals($fileIdentifier, $info['identifier']);
        $this->assertEquals($lastModifiedDateTime->getTimestamp(), $info['ctime']);
        $this->assertEquals($lastModifiedDateTime->getTimestamp(), $info['mtime']);
        $this->assertEquals(sha1('/' . $fileIdentifier), $info['identifier_hash']);
        $this->assertEquals(sha1('/' . dirname($fileIdentifier)), $info['folder_hash']);
        $this->assertEquals('youtube', $info['extension']);
        $this->assertEquals('video/youtube', $info['mimetype']);
        $this->assertEquals(12345, $info['size']);
        $this->assertEquals($this->driver->getStorageUid(), $info['storage']);
    }
}
