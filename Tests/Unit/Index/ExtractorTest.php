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

namespace AUS\AusDriverAmazonS3\Tests\Unit\Index;

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use AUS\AusDriverAmazonS3\Index\Extractor;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Class ExtractorTest
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\Tests\Unit\Index
 */
class ExtractorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var Extractor
     */
    protected $extractor = null;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->extractor = new Extractor();
    }

    /**
     * @test
     */
    public function testCanProcessImageFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE)->shouldBeCalled();
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(\TYPO3\CMS\Core\Resource\FileType::IMAGE)->shouldBeCalled();

        $this->assertEquals(true, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessOtherDriverType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn('UnknownDriver')->shouldBeCalled();
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(\TYPO3\CMS\Core\Resource\FileType::IMAGE);

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessUnknownFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(\TYPO3\CMS\Core\Resource\FileType::UNKNOWN)->shouldBeCalled();

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessApplicationFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(\TYPO3\CMS\Core\Resource\FileType::APPLICATION)->shouldBeCalled();

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessVideoFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(\TYPO3\CMS\Core\Resource\FileType::VIDEO)->shouldBeCalled();

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessAudioFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(\TYPO3\CMS\Core\Resource\FileType::AUDIO)->shouldBeCalled();

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessTextFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(\TYPO3\CMS\Core\Resource\FileType::TEXT)->shouldBeCalled();

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testExtractMetaDataIfRequired(): void
    {
        $file = $this->prophesize(File::class);
        $mock = $this->getMockBuilder(Extractor::class)->onlyMethods(['getImageDimensionsOfRemoteFile'])->getMock();
        $mock->expects($this->exactly(1))->method('getImageDimensionsOfRemoteFile')->willReturn([200, 100]);
        $this->assertEquals(
            [
                'width' => 200,
                'height' => 100
            ],
            $mock->extractMetaData($file->reveal(), [])
        );
    }

    /**
     * @test
     */
    public function testExtractNoMetaDataIfNotRequired(): void
    {
        $file = $this->prophesize(File::class);
        $mock = $this->getMockBuilder(Extractor::class)->onlyMethods(['getImageDimensionsOfRemoteFile'])->getMock();
        $mock->expects($this->exactly(0))->method('getImageDimensionsOfRemoteFile')->willReturn([200, 100]);
        $result = $mock->extractMetaData(
            $file->reveal(),
            [
                'width' => 500,
                'height' => 200
            ]
        );
        $this->assertEquals(500, $result['width']);
        $this->assertEquals(200, $result['height']);
    }
}
