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
