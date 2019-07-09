<?php
namespace AUS\AusDriverAmazonS3\S3Adapter;

/***
 *
 * This file is part of an extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Markus Hölzle <typo3@markus-hoelzle.de>
 *
 ***/

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class MetaInfoDownloadAdapter
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\S3Adapter
 */
class MetaInfoDownloadAdapter extends AbstractS3Adapter
{
    /**
     * @param AmazonS3Driver $driver
     * @param string $identifier
     * @param array $response
     * @return array
     */
    public function getMetaInfoFromResponse(AmazonS3Driver $driver, string $identifier, array $response): array
    {
        /** @var \Aws\Api\DateTimeResult $lastModified */
        $lastModified = $response['LastModified'];
        $lastModifiedUnixTimestamp = $lastModified->getTimestamp();

        $metaInfo = [
            'name' => basename($identifier),
            'identifier' => $identifier,
            'ctime' => $lastModifiedUnixTimestamp,
            'mtime' => $lastModifiedUnixTimestamp,
            'identifier_hash' => $driver->hashIdentifier($identifier),
            'folder_hash' => $driver->hashIdentifier(PathUtility::dirname($identifier)),
            'extension' => PathUtility::pathinfo($identifier, PATHINFO_EXTENSION),
            'storage' => $driver->getStorageUid(),
        ];

        if (!empty($response['ContentType'])) {
            $metaInfo['mimetype'] = $this->getOverwrittenMimeType($response['ContentType'], $metaInfo['extension']);
        }
        if (!empty($response['ContentLength'])) {
            $metaInfo['size'] = (int)$response['ContentLength'];
        } elseif (!empty($response['size'])) {
            $metaInfo['size'] = (int)$response['size'];
        }
        return $metaInfo;
    }

    /**
     * This is a copy of \TYPO3\CMS\Core\Type\File\FileInfo because we can't use the code there.
     * We need the same logic to get the mime types for special cases like "youtube" or "vimeo" videos, which are technically json files.
     *
     * @see \TYPO3\CMS\Core\Type\File\FileInfo::getMimeType
     * @param string $mimeType Content type provided by AWS S3
     * @param string $extension The extension of the file name
     * @return string The mime type as string
     */
    protected function getOverwrittenMimeType(string $mimeType, string $extension): string
    {
        $fileExtensionToMimeTypeMapping = $GLOBALS['TYPO3_CONF_VARS']['SYS']['FileInfo']['fileExtensionToMimeType'];
        $lowercaseFileExtension = strtolower($extension);
        if (!empty($fileExtensionToMimeTypeMapping[$lowercaseFileExtension])) {
            $mimeType = $fileExtensionToMimeTypeMapping[$lowercaseFileExtension];
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][\TYPO3\CMS\Core\Type\File\FileInfo::class]['mimeTypeGuessers'] ?? [] as $mimeTypeGuesser) {
            $hookParameters = [
                'mimeType' => &$mimeType
            ];

            GeneralUtility::callUserFunction(
                $mimeTypeGuesser,
                $hookParameters,
                $this
            );
        }

        return $mimeType;
    }
}
