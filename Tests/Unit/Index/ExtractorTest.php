<?php
namespace AUS\AusDriverAmazonS3\Tests\Unit\Index;

/***
 *
 * This file is part of an "anders und sehr" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2017 Markus Hölzle <m.hoelzle@andersundsehr.com>, anders und sehr GmbH
 *
 ***/

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use AUS\AusDriverAmazonS3\Index\Extractor;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Class ExtractorTest
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>
 * @package AUS\AusDriverAmazonS3\Tests\Unit\Index
 */
class ExtractorTest extends UnitTestCase
{
    /**
     * @var array
     */
    protected $testFilesToDelete = [];

    /**
     * @var Extractor
     */
    protected $extractor = null;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->extractor = new Extractor();
    }

    /**
     * @test
     */
    public function testCanProcessImageFileType()
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE)->shouldBeCalled();
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(File::FILETYPE_IMAGE)->shouldBeCalled();

        $this->assertEquals(true, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessOtherDriverType()
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn('UnknownDriver')->shouldBeCalled();
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(File::FILETYPE_IMAGE);

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessUnknownFileType()
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(File::FILETYPE_UNKNOWN)->shouldBeCalled();

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessApplicationFileType()
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(File::FILETYPE_APPLICATION)->shouldBeCalled();

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessVideoFileType()
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(File::FILETYPE_VIDEO)->shouldBeCalled();

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessAudioFileType()
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(File::FILETYPE_AUDIO)->shouldBeCalled();

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testCanNotProcessTextFileType()
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $file = $this->prophesize(File::class);
        $file->getStorage()->willReturn($storage->reveal());
        $file->getType()->willReturn(File::FILETYPE_TEXT)->shouldBeCalled();

        $this->assertEquals(false, $this->extractor->canProcess($file->reveal()));
    }

    /**
     * @test
     */
    public function testExtractMetaDataIfRequired()
    {
        $file = $this->prophesize(File::class);
        $mock = $this->getMock(Extractor::class, ['getImageDimensionsOfRemoteFile']);
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
    public function testExtractNoMetaDataIfNotRequired()
    {
        $file = $this->prophesize(File::class);
        $mock = $this->getMock(Extractor::class, ['getImageDimensionsOfRemoteFile']);
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
