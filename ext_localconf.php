<?php

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers'][\AUS\AusDriverAmazonS3\Driver\AmazonS3Driver::DRIVER_TYPE] = [
    'class' => \AUS\AusDriverAmazonS3\Driver\AmazonS3Driver::class,
    'flexFormDS' => 'FILE:EXT:' . \AUS\AusDriverAmazonS3\Driver\AmazonS3Driver::EXTENSION_KEY . '/Configuration/FlexForm/AmazonS3DriverFlexForm.xml',
    'label' => 'AWS S3',
    'shortName' => \AUS\AusDriverAmazonS3\Driver\AmazonS3Driver::DRIVER_TYPE,
];

// register extractor
\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::class)->registerExtractionService(\AUS\AusDriverAmazonS3\Index\Extractor::class);

/* @var $signalSlotDispatcher \TYPO3\CMS\Extbase\SignalSlot\Dispatcher */
$signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\SignalSlot\Dispatcher');
$signalSlotDispatcher->connect(\TYPO3\CMS\Core\Resource\Index\FileIndexRepository::class, 'recordUpdated', \AUS\AusDriverAmazonS3\Signal\FileIndexRepository::class, 'recordUpdatedOrCreated');
$signalSlotDispatcher->connect(\TYPO3\CMS\Core\Resource\Index\FileIndexRepository::class, 'recordCreated', \AUS\AusDriverAmazonS3\Signal\FileIndexRepository::class, 'recordUpdatedOrCreated');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache'] = [
    'backend' => \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class,
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache'] = [
    'backend' => \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class,
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
];
