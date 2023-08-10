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
    private string $fileIdentifier;
    private string $temporaryPath;
    private bool $writable;

    public function __construct(string $fileIdentifier, string $temporaryPath, bool $writable)
    {
        $this->fileIdentifier = $fileIdentifier;
        $this->temporaryPath = $temporaryPath;
        $this->writable = $writable;
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
