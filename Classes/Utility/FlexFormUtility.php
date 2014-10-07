<?php
namespace AUS\AusDriverAmazonS3\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Markus Hölzle <m.hoelzle@andersundsehr.com>, anders und sehr GmbH
 *  All rights reserved
 *
 ***************************************************************/

/**
 * Class FlexFormUtility
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>
 * @package AUS\AusDriverAmazonS3\Utility
 */
class FlexFormUtility implements \TYPO3\CMS\Core\SingletonInterface {


	/**
	 * @return FlexFormUtility
	 */
	public function __construct() {
		\AUS\AusDriverAmazonS3\Driver\AmazonS3Driver::loadExternalClasses();
	}


	/**
	 * @param \array& $config
	 * @return void
	 */
	public function addDriverConfigurationRegions(array& $config) {
		$regionOptions = array();
		$reflection = new \ReflectionClass('\Aws\Common\Enum\Region');

		foreach ($reflection->getConstants() as $constant => $value) {
			$regionOptions[] = array($constant, $value);
		}

		$config['items'] = array_merge($config['items'], $regionOptions);
	}

}
