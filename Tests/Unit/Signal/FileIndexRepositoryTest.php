<?php
namespace AUS\AusDriverAmazonS3\Tests\Unit\Signal;

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
use AUS\AusDriverAmazonS3\Signal\FileIndexRepository;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Class FileIndexRepositoryTest
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>
 * @package AUS\AusDriverAmazonS3\Tests\Unit\Signal
 */
class FileIndexRepositoryTest extends UnitTestCase
{
    /**
     * @var array
     */
    protected $testFilesToDelete = [];

    /**
     * @test
     */
    public function testRecordUpdatedOrCreatedHandleImageFileType()
    {
        $file = $this->prophesize(File::class)->reveal();
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $storage->getFile('foo/bar.file')->willReturn($file);
        $extractor = $this->prophesize(Extractor::class);
        $extractor->getImageDimensionsOfRemoteFile($file)->willReturn(null);

        $mock = $this->getMock(FileIndexRepository::class, ['getStorage', 'getExtractor', 'getMetaDataRepository']);
        $mock->expects($this->exactly(1))->method('getStorage')->willReturn($storage->reveal());
        $mock->expects($this->exactly(1))->method('getExtractor')->willReturn($extractor->reveal());
        $mock->recordUpdatedOrCreated([
            'type' => File::FILETYPE_IMAGE,
            'storage' => 42,
            'identifier' => 'foo/bar.file',
        ]);
    }

    /**
     * @test
     */
    public function testRecordUpdatedOrCreatedDoNotHandleUnknownFileType()
    {
        $file = $this->prophesize(File::class)->reveal();
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $storage->getFile('foo/bar.file')->willReturn($file);
        $extractor = $this->prophesize(Extractor::class);
        $extractor->getImageDimensionsOfRemoteFile($file)->willReturn(null);

        $mock = $this->getMock(FileIndexRepository::class, ['getStorage', 'getExtractor', 'getMetaDataRepository']);
        $mock->expects($this->exactly(0))->method('getStorage')->willReturn($storage->reveal());
        $mock->expects($this->exactly(0))->method('getExtractor')->willReturn($extractor->reveal());
        $mock->recordUpdatedOrCreated([
            'type' => File::FILETYPE_UNKNOWN,
            'storage' => 42,
            'identifier' => 'foo/bar.file',
        ]);
    }

    /**
     * @test
     */
    public function testRecordUpdatedOrCreatedDoNotHandleApplicationFileType()
    {
        $file = $this->prophesize(File::class)->reveal();
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $storage->getFile('foo/bar.file')->willReturn($file);
        $extractor = $this->prophesize(Extractor::class);
        $extractor->getImageDimensionsOfRemoteFile($file)->willReturn(null);

        $mock = $this->getMock(FileIndexRepository::class, ['getStorage', 'getExtractor', 'getMetaDataRepository']);
        $mock->expects($this->exactly(0))->method('getStorage')->willReturn($storage->reveal());
        $mock->expects($this->exactly(0))->method('getExtractor')->willReturn($extractor->reveal());
        $mock->recordUpdatedOrCreated([
            'type' => File::FILETYPE_APPLICATION,
            'storage' => 42,
            'identifier' => 'foo/bar.file',
        ]);
    }
}
