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

namespace AUS\AusDriverAmazonS3\Event;

/**
 * Class GetFileForLocalProcessingEvent
 * Event which is called in function getFileForLocalProcessing()
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\Event
 */
final class GetFileForLocalProcessingEvent
{
    public function __construct(private readonly string $fileIdentifier, private string $temporaryPath, private readonly bool $writable)
    {
    }

    public function getFileIdentifier(): string
    {
        return $this->fileIdentifier;
    }

    public function getTemporaryPath(): string
    {
        return $this->temporaryPath;
    }

    public function setTemporaryPath(string $temporaryPath): void
    {
        $this->temporaryPath = $temporaryPath;
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }
}
