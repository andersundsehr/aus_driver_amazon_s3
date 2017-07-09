<?php
namespace AUS\AusDriverAmazonS3\Index;

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

use TYPO3\CMS\Core\Resource;
use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\ExtractorInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Extractor for image files
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>
 * @author Stefan Lamm <s.lamm@andersundsehr.com>
 * @package AUS\AusDriverAmazonS3\Index
 */
class Extractor implements ExtractorInterface
{

    /**
     * Returns an array of supported file types;
     * An empty array indicates all filetypes
     *
     * @return array
     */
    public function getFileTypeRestrictions()
    {
        return array(File::FILETYPE_IMAGE);
    }

    /**
     * Get all supported DriverClasses
     *
     * Since some extractors may only work for local files, and other extractors
     * are especially made for grabbing data from remote.
     *
     * Returns array of string with driver names of Drivers which are supported,
     * If the driver did not register a name, it's the classname.
     * empty array indicates no restrictions
     *
     * @return array
     */
    public function getDriverRestrictions()
    {
        return array(AmazonS3Driver::DRIVER_TYPE);
    }

    /**
     * Returns the data priority of the extraction Service.
     * Defines the precedence of Data if several extractors
     * extracted the same property.
     *
     * Should be between 1 and 100, 100 is more important than 1
     *
     * @return integer
     */
    public function getPriority()
    {
        return 50;
    }

    /**
     * Returns the execution priority of the extraction Service
     * Should be between 1 and 100, 100 means runs as first service, 1 runs at last service
     *
     * @return integer
     */
    public function getExecutionPriority()
    {
        return 50;
    }

    /**
     * Checks if the given file can be processed by this Extractor
     *
     * @param Resource\File $file
     * @return boolean
     */
    public function canProcess(Resource\File $file)
    {
        return $file->getType() == File::FILETYPE_IMAGE && $file->getStorage()->getDriverType() === AmazonS3Driver::DRIVER_TYPE;
    }

    /**
     * The actual processing TASK
     *
     * Should return an array with database properties for sys_file_metadata to write
     *
     * @param Resource\File $file
     * @param array $previousExtractedData optional, contains the array of already extracted data
     * @return array
     */
    public function extractMetaData(Resource\File $file, array $previousExtractedData = array())
    {
        if (!$previousExtractedData['width'] || !$previousExtractedData['height']) {
            $imageDimensions = self::getImageDimensionsOfRemoteFile($file);
            if ($imageDimensions !== null) {
                $previousExtractedData['width'] = $imageDimensions[0];
                $previousExtractedData['height'] = $imageDimensions[1];
            }
        }

        return $previousExtractedData;
    }

    /**
     * @param File $file
     * @return array|NULL
     */
    public static function getImageDimensionsOfRemoteFile(File $file)
    {
        /* @var $graphicalFunctionsObject \TYPO3\CMS\Core\Imaging\GraphicalFunctions */
        $graphicalFunctionsObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Imaging\\GraphicalFunctions');
        $tempFilePath = $file->getForLocalProcessing();
        $imageDimensions = $graphicalFunctionsObject->getImageDimensions($tempFilePath);
        @unlink($tempFilePath);
        return $imageDimensions;
    }

}
