<?php

/***
 *
 * This file is part of an extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2022 Markus Hölzle <typo3@markus-hoelzle.de>
 *
 ***/

declare(strict_types=1);

namespace AUS\AusDriverAmazonS3\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * This class is used to place functions which are more complex because keeping compatibility to older TYPO3 versions.
 * The functions can be removed here, if the specific TYPO3 version is not supported anymore.
 */
class CompatibilityService implements SingletonInterface
{
    /**
     * Check if the TYPO3 Backend mode is currently used
     * @return bool
     */
    public function isBackend(): bool
    {
        if (Environment::isCli()) {
            return true;
        }
        return !isset($GLOBALS['TYPO3_REQUEST'])
            || ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend();
    }

    /**
     * Check if the TYPO3 Frontend mode is currently used
     * @return bool
     */
    public function isFrontend(): bool
    {
        if (Environment::isCli()) {
            return false;
        }
        return isset($GLOBALS['TYPO3_REQUEST'])
            && ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend();
    }
}
