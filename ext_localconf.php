<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

// register additional driver
$TYPO3_CONF_VARS['SYS']['fal']['registeredDrivers'][\AUS\AusDriverAmazonS3\Driver\AmazonS3Driver::DRIVER_TYPE] = array(
	'class' => 'AUS\AusDriverAmazonS3\Driver\AmazonS3Driver',
	'label' => 'Amazon S3',
	'flexFormDS' => 'FILE:EXT:' . \AUS\AusDriverAmazonS3\Driver\AmazonS3Driver::EXTENSION_KEY . '/Configuration/FlexForm/AmazonS3DriverFlexForm.xml'
);

// register extractor
\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::getInstance()->registerExtractionService('AUS\AusDriverAmazonS3\Index\Extractor');

/* @var $signalSlotDispatcher \TYPO3\CMS\Extbase\SignalSlot\Dispatcher */
$signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\SignalSlot\Dispatcher');
$signalSlotDispatcher->connect('TYPO3\\CMS\\Core\\Resource\\Index\\FileIndexRepository', 'recordUpdated', 'AUS\AusDriverAmazonS3\Signal\FileIndexRepository', 'recordUpdatedOrCreated');
$signalSlotDispatcher->connect('TYPO3\\CMS\\Core\\Resource\\Index\\FileIndexRepository', 'recordCreated', 'AUS\AusDriverAmazonS3\Signal\FileIndexRepository', 'recordUpdatedOrCreated');
