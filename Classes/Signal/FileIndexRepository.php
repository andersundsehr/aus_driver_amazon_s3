<?php
namespace AUS\AusDriverAmazonS3\Signal;

/***
 *
 * This file is part of an "anders und sehr" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2017 Markus Hölzle <m.hoelzle@andersundsehr.com>, anders und sehr GmbH
 * Stefan Lamm <s.lamm@andersundsehr.com>, anders und sehr GmbH
 *
 ***/

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use AUS\AusDriverAmazonS3\Index\Extractor;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Signals for metadata update
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>
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
            /* @var $storage \TYPO3\CMS\Core\Resource\ResourceStorage */
            $storage = ResourceFactory::getInstance()->getStorageObject($data['storage']);

            // only process on our driver type where data was missing
            if ($storage->getDriverType() !== AmazonS3Driver::DRIVER_TYPE) {
                return null;
            }

            $file = $storage->getFile($data['identifier']);
            $imageDimensions = Extractor::getImageDimensionsOfRemoteFile($file);

            if ($imageDimensions !== null) {
                /* @var $metaDataRepository \TYPO3\CMS\Core\Resource\Index\MetaDataRepository */
                $metaDataRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\Index\\MetaDataRepository');
                $metaData = $metaDataRepository->findByFileUid($data['uid']);

                $metaData['width'] = $imageDimensions[0];
                $metaData['height'] = $imageDimensions[1];
                $metaDataRepository->update($data['uid'], $metaData);
            }
        }
    }

}
