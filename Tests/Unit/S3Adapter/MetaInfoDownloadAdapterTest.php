<?php

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

namespace AUS\AusDriverAmazonS3\Tests\Unit\S3Adapter;

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use AUS\AusDriverAmazonS3\S3Adapter\MetaInfoDownloadAdapter;
use Aws\Api\DateTimeResult;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Class MetaInfoDownloadAdapterTest
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\Tests\Unit\S3Adapter
 */
class MetaInfoDownloadAdapterTest extends UnitTestCase
{
    /**
     * @var MetaInfoDownloadAdapter
     */
    protected $metaInfoDownloadAdapter = null;

    /**
     * @var AmazonS3Driver|ObjectProphecy
     */
    protected $driver = null;


    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->metaInfoDownloadAdapter = new MetaInfoDownloadAdapter();
        $this->driver = $this->prophesize(AmazonS3Driver::class);
    }

    /**
     * @test
     */
    public function getMetaInfoFromResponseTest()
    {
        // prepare test data
        $identifier = 'foo/bar/test.png';
        $lastModifiedDateTime = new DateTimeResult();
        $awsResponse = [
            'LastModified' => $lastModifiedDateTime,
            'ContentType' => 'image/png',
            'ContentLength' => 123,
        ];

        // prepare used dependencies
        $this->driver->hashIdentifier($identifier)->willReturn(sha1('/' . $identifier));
        $this->driver->hashIdentifier(PathUtility::dirname($identifier))->willReturn(sha1('/' . dirname($identifier)));
        $this->driver->getStorageUid()->willReturn(42);

        // execute function
        $metaInfo = $this->metaInfoDownloadAdapter->getMetaInfoFromResponse($this->driver->reveal(), $identifier, $awsResponse);

        // test results
        $this->assertEquals(
            ['name', 'identifier', 'ctime', 'mtime', 'extension', 'mimetype', 'size', 'identifier_hash', 'folder_hash', 'storage'],
            array_keys($metaInfo),
            '',
            0.0,
            10,
            true // set this to true
        );
        $this->assertEquals(basename($identifier), $metaInfo['name']);
        $this->assertEquals($identifier, $metaInfo['identifier']);
        $this->assertEquals($lastModifiedDateTime->getTimestamp(), $metaInfo['ctime']);
        $this->assertEquals($lastModifiedDateTime->getTimestamp(), $metaInfo['mtime']);
        $this->assertEquals(sha1('/' . $identifier), $metaInfo['identifier_hash']);
        $this->assertEquals(sha1('/' . dirname($identifier)), $metaInfo['folder_hash']);
        $this->assertEquals('png', $metaInfo['extension']);
        $this->assertEquals('image/png', $metaInfo['mimetype']);
        $this->assertEquals(123, $metaInfo['size']);
        $this->assertEquals(42, $metaInfo['storage']);
    }

    /**
     * @test
     */
    public function getMetaInfoFromResponseWithPseudoMimeTypeTest()
    {
        // prepare test data
        $identifier = 'foo/bar/test.youtube';
        $lastModifiedDateTime = new DateTimeResult();
        $awsResponse = [
            'LastModified' => $lastModifiedDateTime,
            'ContentType' => 'image/png',
            'ContentLength' => 123,
        ];

        // prepare used dependencies
        $this->driver->hashIdentifier($identifier)->willReturn(sha1('/' . $identifier));
        $this->driver->hashIdentifier(PathUtility::dirname($identifier))->willReturn(sha1('/' . dirname($identifier)));
        $this->driver->getStorageUid()->willReturn(42);

        // execute function
        $metaInfo = $this->metaInfoDownloadAdapter->getMetaInfoFromResponse($this->driver->reveal(), $identifier, $awsResponse);

        // test results
        $this->assertEquals(
            ['name', 'identifier', 'ctime', 'mtime', 'extension', 'mimetype', 'size', 'identifier_hash', 'folder_hash', 'storage'],
            array_keys($metaInfo),
            '',
            0.0,
            10,
            true // set this to true
        );
        $this->assertEquals(basename($identifier), $metaInfo['name']);
        $this->assertEquals($identifier, $metaInfo['identifier']);
        $this->assertEquals($lastModifiedDateTime->getTimestamp(), $metaInfo['ctime']);
        $this->assertEquals($lastModifiedDateTime->getTimestamp(), $metaInfo['mtime']);
        $this->assertEquals(sha1('/' . $identifier), $metaInfo['identifier_hash']);
        $this->assertEquals(sha1('/' . dirname($identifier)), $metaInfo['folder_hash']);
        $this->assertEquals('youtube', $metaInfo['extension']);
        $this->assertEquals('video/youtube', $metaInfo['mimetype']);
        $this->assertEquals(123, $metaInfo['size']);
        $this->assertEquals(42, $metaInfo['storage']);
    }
}
