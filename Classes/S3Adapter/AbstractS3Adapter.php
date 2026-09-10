<?php

/***
 *
 * This file is part of an extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2020 Markus Hölzle <typo3@markus-hoelzle.de>
 *
 ***/

declare(strict_types=1);

namespace AUS\AusDriverAmazonS3\S3Adapter;

use RuntimeException;
use Aws\S3\S3Client;

/**
 * Class AbstractS3Adapter
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\S3Adapter
 */
class AbstractS3Adapter
{
    /**
     * AbstractS3Adapter constructor.
     * @param S3Client $s3Client
     */
    public function __construct(protected ?S3Client $s3Client = null)
    {
    }

    protected function getS3Client(): S3Client
    {
        if ($this->s3Client === null) {
            throw new RuntimeException('The S3 client has not been initialized.', 7342829236);
        }

        return $this->s3Client;
    }
}
