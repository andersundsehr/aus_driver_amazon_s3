<?php

defined('TYPO3') or die();

$driverRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class);
$driverRegistry->registerDriverClass(
    \AUS\AusDriverAmazonS3\Driver\AmazonS3Driver::class,
    \AUS\AusDriverAmazonS3\Driver\AmazonS3Driver::DRIVER_TYPE,
    'AWS S3',
    'FILE:EXT:' . \AUS\AusDriverAmazonS3\Driver\AmazonS3Driver::EXTENSION_KEY . '/Configuration/FlexForm/AmazonS3DriverFlexForm.xml'
);

// register extractor
\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::class)->registerExtractionService(\AUS\AusDriverAmazonS3\Index\Extractor::class);

if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache'] = [];
}
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache'] = [];
}

if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache']['backend'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache']['backend'] = \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class;
}
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache']['backend'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache']['backend'] = \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class;
}

if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache']['frontend'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_metainfocache']['frontend'] = \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
}
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache']['frontend'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ausdriveramazons3_requestcache']['frontend'] = \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
}