<?php

/***
 *
 * This file is part of an extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2020 Markus Hölzle <typo3@markus-hoelzle.de>
 * Stefan Lamm <s.lamm@andersundsehr.com>, anders und sehr GmbH
 *
 ***/

namespace AUS\AusDriverAmazonS3\Signal;

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use AUS\AusDriverAmazonS3\Index\Extractor;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Signals for metadata update
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @author Stefan Lamm <s.lamm@andersundsehr.com>
 * @package AUS\AusDriverAmazonS3\Signal
 */
class FileIndexRepository
{

    /**
     * @param array $data
     * @return void|null
     */
    public function recordUpdatedOrCreated($data)
    {
        if ($data['type'] === File::FILETYPE_IMAGE) {
            $storage = $this->getStorage((int) $data['storage']);

            // only process on our driver type where data was missing
            if ($storage->getDriverType() !== AmazonS3Driver::DRIVER_TYPE) {
                return null;
            }

            $file = $storage->getFile($data['identifier']);
            $imageDimensions = $this->getExtractor()->getImageDimensionsOfRemoteFile($file);

            if ($imageDimensions !== null) {
                $metaDataRepository = $this->getMetaDataRepository();
                $metaData = $metaDataRepository->findByFileUid($data['uid']);

                $metaData['width'] = $imageDimensions[0];
                $metaData['height'] = $imageDimensions[1];
                $metaDataRepository->update($data['uid'], $metaData);
            }
        }
    }

    /**
     * @param int $uid
     * @return ResourceStorage
     */
    protected function getStorage(int $uid): ResourceStorage
    {
        return GeneralUtility::makeInstance(ResourceFactory::class)->getStorageObject($uid);
    }

    /**
     * @return \AUS\AusDriverAmazonS3\Index\Extractor
     */
    protected function getExtractor()
    {
        return GeneralUtility::makeInstance(Extractor::class);
    }

    /**
     * @return \TYPO3\CMS\Core\Resource\Index\MetaDataRepository
     */
    protected function getMetaDataRepository()
    {
        return GeneralUtility::makeInstance(MetaDataRepository::class);
    }
}
