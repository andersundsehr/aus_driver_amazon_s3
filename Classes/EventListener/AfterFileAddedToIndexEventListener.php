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

namespace AUS\AusDriverAmazonS3\EventListener;

use AUS\AusDriverAmazonS3\Service\MetaDataUpdateService;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedToIndexEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Signals for metadata update
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\EventListener
 */
class AfterFileAddedToIndexEventListener
{
    public function __invoke(AfterFileAddedToIndexEvent $event): void
    {
        $metaDataUpdateService = GeneralUtility::makeInstance(MetaDataUpdateService::class);
        $metaDataUpdateService->updateMetadata($event->getRecord());
    }
}
