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
