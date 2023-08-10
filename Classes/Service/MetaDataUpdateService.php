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

declare(strict_types=1);

namespace AUS\AusDriverAmazonS3\Service;

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use AUS\AusDriverAmazonS3\Index\Extractor;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is used from event listeners to update the metadata
 */
class MetaDataUpdateService implements SingletonInterface
{
    public function updateMetadata(array $fileProperties): void
    {
        if ($fileProperties['type'] === AbstractFile::FILETYPE_IMAGE) {
            $storage = $this->getStorage((int) $fileProperties['storage']);

            // only process on our driver type where data was missing
            if ($storage->getDriverType() !== AmazonS3Driver::DRIVER_TYPE) {
                return;
            }

            $file = $storage->getFile($fileProperties['identifier']);
            $imageDimensions = $this->getExtractor()->getImageDimensionsOfRemoteFile($file);

            if ($imageDimensions !== null) {
                $metaDataRepository = $this->getMetaDataRepository();
                $metaData = $metaDataRepository->findByFileUid($fileProperties['uid']);

                $metaData['width'] = $imageDimensions[0];
                $metaData['height'] = $imageDimensions[1];
                $metaDataRepository->update($fileProperties['uid'], $metaData);
            }
        }
    }

    protected function getStorage(int $uid): ResourceStorage
    {
        return GeneralUtility::makeInstance(ResourceFactory::class)->getStorageObject($uid);
    }

    protected function getExtractor(): Extractor
    {
        return GeneralUtility::makeInstance(Extractor::class);
    }

    protected function getMetaDataRepository(): MetaDataRepository
    {
        return GeneralUtility::makeInstance(MetaDataRepository::class);
    }
}
