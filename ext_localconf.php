<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\Driver\DriverRegistry;
use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Resource\Index\ExtractorRegistry;
use AUS\AusDriverAmazonS3\Index\Extractor;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') || die();

$driverRegistry = GeneralUtility::makeInstance(DriverRegistry::class);
$driverRegistry->registerDriverClass(
    AmazonS3Driver::class,
    AmazonS3Driver::DRIVER_TYPE,
    'AWS S3',
    'FILE:EXT:' . AmazonS3Driver::EXTENSION_KEY . '/Configuration/FlexForm/AmazonS3DriverFlexForm.xml'
);

// register extractor
GeneralUtility::makeInstance(ExtractorRegistry::class)->registerExtractionService(Extractor::class);
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache'] ??= [];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache'] ??= [];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache']['backend'] ??= TransientMemoryBackend::class;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache']['backend'] ??= TransientMemoryBackend::class;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache']['frontend'] ??= VariableFrontend::class;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache']['frontend'] ??= VariableFrontend::class;
