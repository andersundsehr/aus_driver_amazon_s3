<?php
namespace AUS\AusDriverAmazonS3\Tests\Unit\Driver;

/***
 *
 * This file is part of an extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Markus Hölzle <typo3@markus-hoelzle.de>
 *
 ***/

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use Aws\Api\DateTimeResult;
use Aws\Result;
use Aws\S3\S3Client;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Class AmazonS3DriverTest
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\Tests\Unit\Driver
 */
class AmazonS3DriverTest extends UnitTestCase
{
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
    public function setUp()
    {
        parent::setUp();
        \PHPUnit\Framework\Error\Deprecated::$enabled = false;

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][AmazonS3Driver::EXTENSION_KEY] = [];

        $this->s3Client = $this->prophesize(S3Client::class);
        $this->driver = new AmazonS3Driver($this->testConfiguration, $this->s3Client->reveal());
        $this->driver->initialize();
    }

    /**
     * @test
     */
    public function testConstructorConfigurationParameter()
    {
        $driver = new AmazonS3Driver($this->testConfiguration);
        $this->assertAttributeEquals($this->testConfiguration, 'configuration', $driver);
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
        $result = new Result([
            'LastModified' => new DateTimeResult(),
            'ContentType' => 'image/png',
            'ContentLength' => 123,
        ]);
        $this->s3Client->headObject([
            'Bucket' => $this->testConfiguration['bucket'],
            'Key' => $fileIdentifier
        ])->willReturn($result);
        $info = $this->driver->getFileInfoByIdentifier($fileIdentifier);
        $this->assertEquals(
            ['name', 'identifier', 'ctime', 'mtime', 'mimetype', 'size', 'identifier_hash', 'folder_hash', 'storage'],
            array_keys($info),
            '',
            0.0,
            10,
            true // set this to true
        );
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
        $this->assertEquals($properties, array_keys($info), '', 0.0, 10, true);
    }
}
