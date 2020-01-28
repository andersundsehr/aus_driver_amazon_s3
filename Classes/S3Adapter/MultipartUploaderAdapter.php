<?php

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

namespace AUS\AusDriverAmazonS3\S3Adapter;

use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;

/**
 * Class MultipartUploaderAdapter
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\S3Adapter
 */
class MultipartUploaderAdapter extends AbstractS3Adapter
{
    /**
     * @param string $localFilePath File path and name on local storage
     * @param string $targetFilePath File path and name on target S3 bucket
     * @param string $bucket S3 bucket name
     * @param string $cacheControl Cache control header
     */
    public function upload(string $localFilePath, string $targetFilePath, string $bucket, string $cacheControl)
    {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $contentType = finfo_file($fileInfo, $localFilePath);
        finfo_close($fileInfo);

        $uploader = new MultipartUploader($this->s3Client, $localFilePath, [
            'bucket' => $bucket,
            'key' => $targetFilePath,
            'params' => [
                'ContentType' => $contentType,
                'CacheControl' => $cacheControl,
            ],
        ]);

        // Upload and recover from errors
        do {
            try {
                $result = $uploader->upload();
            } catch (MultipartUploadException $e) {
                $uploader = new MultipartUploader($this->s3Client, $localFilePath, [
                    'state' => $e->getState(),
                ]);
            }
        } while (!isset($result));

        // Abort a multipart upload if failed
        try {
            $uploader->upload();
        } catch (MultipartUploadException $e) {
            // State contains the "Bucket", "Key", and "UploadId"
            $params = $e->getState()->getId();
            $this->s3Client->abortMultipartUpload($params);
            throw $e;
        }
    }
}
