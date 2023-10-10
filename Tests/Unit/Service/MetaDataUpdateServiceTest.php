<?php

/***
 *
 * This file is part of an extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2023 Markus Hölzle <typo3@markus-hoelzle.de>
 *
 ***/

namespace AUS\AusDriverAmazonS3\Tests\Unit\Service;

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use AUS\AusDriverAmazonS3\Index\Extractor;
use AUS\AusDriverAmazonS3\Service\MetaDataUpdateService;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Class FileIndexRepositoryTest
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\Tests\Unit\Service
 */
class MetaDataUpdateServiceTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function testRecordUpdatedOrCreatedDoNotHandleUnknownFileType()
    {
        $file = $this->prophesize(File::class)->reveal();

        $mock = $this->getMockBuilder(MetaDataUpdateService::class)->onlyMethods(['getStorage', 'getExtractor'])->getMock();
        $mock->expects($this->exactly(0))->method('getStorage')->willReturn($this->getStorageProphecy($file)->reveal());
        $mock->expects($this->exactly(0))->method('getExtractor')->willReturn($this->getExtractorProphecy($file)->reveal());
        $mock->updateMetadata([
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

        $mock = $this->getMockBuilder(MetaDataUpdateService::class)->onlyMethods(['getStorage', 'getExtractor'])->getMock();
        $mock->expects($this->exactly(0))->method('getStorage')->willReturn($this->getStorageProphecy($file)->reveal());
        $mock->expects($this->exactly(0))->method('getExtractor')->willReturn($this->getExtractorProphecy($file)->reveal());
        $mock->updateMetadata([
            'type' => File::FILETYPE_APPLICATION,
            'storage' => 42,
            'identifier' => 'foo/bar.file',
        ]);
    }

    /**
     * @param $file
     * @return ObjectProphecy
     */
    protected function getStorageProphecy($file): ObjectProphecy
    {
        $storage = $this->prophesize(ResourceStorage::class);
        $storage->getDriverType()->willReturn(AmazonS3Driver::DRIVER_TYPE);
        $storage->getFile('foo/bar.file')->willReturn($file);
        return $storage;
    }

    /**
     * @param $file
     * @return ObjectProphecy
     */
    protected function getExtractorProphecy($file): ObjectProphecy
    {
        $extractor = $this->prophesize(Extractor::class);
        $extractor->getImageDimensionsOfRemoteFile($file)->willReturn(null);
        return $extractor;
    }
}
