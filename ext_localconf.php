<?php

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Resource\Driver\DriverRegistry;

defined('TYPO3') or die();

$driverRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(DriverRegistry::class);
$driverRegistry->registerDriverClass(
    AmazonS3Driver::class,
    AmazonS3Driver::DRIVER_TYPE,
    'AWS S3',
    'FILE:EXT:' . AmazonS3Driver::EXTENSION_KEY . '/Configuration/FlexForm/AmazonS3DriverFlexForm.xml'
);

// register extractor
\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::class)->registerExtractionService(\AUS\AusDriverAmazonS3\Index\Extractor::class);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache'] = [
    'backend' => TransientMemoryBackend::class,
    'frontend' => VariableFrontend::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache'] = [
    'backend' => TransientMemoryBackend::class,
    'frontend' => VariableFrontend::class,
];
