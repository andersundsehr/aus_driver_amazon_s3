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

use TYPO3\CMS\Core\Resource\FileType;
use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use AUS\AusDriverAmazonS3\Index\Extractor;
use AUS\AusDriverAmazonS3\Service\MetaDataUpdateService;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function testRecordUpdatedOrCreatedDoNotHandleUnknownFileType(): void
    {
        $file = $this->prophesize(File::class)->reveal();

        $mock = $this->getMockBuilder(MetaDataUpdateService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStorage', 'getExtractor'])
            ->getMock();
        $mock->expects($this->exactly(0))->method('getStorage')->willReturn($this->getStorageProphecy($file)->reveal());
        $mock->expects($this->exactly(0))->method('getExtractor')->willReturn($this->getExtractorProphecy($file)->reveal());
        $mock->updateMetadata([
            'type' => FileType::UNKNOWN->value,
            'storage' => 42,
            'identifier' => 'foo/bar.file',
        ]);
    }

    #[Test]
    public function testRecordUpdatedOrCreatedDoNotHandleApplicationFileType(): void
    {
        $file = $this->prophesize(File::class)->reveal();

        $mock = $this->getMockBuilder(MetaDataUpdateService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStorage', 'getExtractor'])
            ->getMock();
        $mock->expects($this->exactly(0))->method('getStorage')->willReturn($this->getStorageProphecy($file)->reveal());
        $mock->expects($this->exactly(0))->method('getExtractor')->willReturn($this->getExtractorProphecy($file)->reveal());
        $mock->updateMetadata([
            'type' => FileType::APPLICATION->value,
            'storage' => 42,
            'identifier' => 'foo/bar.file',
        ]);
    }

    /**
     * @param $file
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
     */
    protected function getExtractorProphecy($file): ObjectProphecy
    {
        $extractor = $this->prophesize(Extractor::class);
        $extractor->getImageDimensionsOfRemoteFile($file)->willReturn(null);
        return $extractor;
    }
}
