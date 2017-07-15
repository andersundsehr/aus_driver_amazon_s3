<?php
namespace AUS\AusDriverAmazonS3\Tests;

/***
 *
 * This file is part of an "anders und sehr" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2017 Markus Hölzle <m.hoelzle@andersundsehr.com>, anders und sehr GmbH
 *
 ***/

use Nimut\TestingFramework\TestCase\UnitTestCase;

/**
 * Class AmazonS3DriverTest
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>
 * @package AUS\AusDriverAmazonS3\Tests
 */
class AmazonS3DriverTest extends UnitTestCase
{
    /**
     * @var array
     */
    protected $testFilesToDelete = [];

    /**
     * @test
     */
    public function constructorTest()
    {
        $this->assertEquals(1, 1);
    }
}
