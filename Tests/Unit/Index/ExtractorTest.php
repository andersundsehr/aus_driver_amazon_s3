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

use TYPO3\CMS\Core\Resource\FileType;
use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use AUS\AusDriverAmazonS3\Index\Extractor;
use PHPUnit\Framework\Attributes\Test;
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
    protected $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new Extractor();
    }

    #[Test]
    public function testCanProcessImageFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE)->shouldBeCalled();
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(FileType::IMAGE->value);
        $file->isImage()->willReturn(true);

        $this->assertEquals(true, $this->extractor->canProcess($file->reveal()));
    }

    #[Test]
    public function testCanNotProcessOtherDriverType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn('UnknownDriver')->shouldBeCalled();
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(FileType::IMAGE->value);
        $file->isImage()->willReturn(true);

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    #[Test]
    public function testCanNotProcessUnknownFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(FileType::UNKNOWN->value);
        $file->isImage()->willReturn(false);

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    #[Test]
    public function testCanNotProcessApplicationFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(FileType::APPLICATION->value);
        $file->isImage()->willReturn(false);

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    #[Test]
    public function testCanNotProcessVideoFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(FileType::VIDEO->value);
        $file->isImage()->willReturn(false);

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    #[Test]
    public function testCanNotProcessAudioFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(FileType::AUDIO->value);
        $file->isImage()->willReturn(false);

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    #[Test]
    public function testCanNotProcessTextFileType(): void
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(FileType::TEXT->value);
        $file->isImage()->willReturn(false);

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    #[Test]
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

    #[Test]
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
