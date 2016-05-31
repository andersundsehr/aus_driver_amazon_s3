<?php
namespace AUS\AusDriverAmazonS3\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Markus Hölzle <m.hoelzle@andersundsehr.com>, anders und sehr GmbH
 *  All rights reserved
 *
 ***************************************************************/
use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class FlexFormUtility
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>
 * @package AUS\AusDriverAmazonS3\Utility
 */
class FlexFormUtility implements SingletonInterface
{

    /**
     * @return FlexFormUtility
     */
    public function __construct()
    {
        AmazonS3Driver::loadExternalClasses();
    }

    /**
     * @param array &$config
     * @return void
     */
    public function addDriverConfigurationRegions(array &$config)
    {
        $regionOptions = array();
        $reflection = new \ReflectionClass('\Aws\Common\Enum\Region');

        foreach ($reflection->getConstants() as $constant => $value) {
            $regionOptions[$value] = array($constant, $value);
        }

        $config['items'] = array_merge($config['items'], $regionOptions);
    }

}
