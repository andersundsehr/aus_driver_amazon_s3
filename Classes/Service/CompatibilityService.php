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
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

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
        if (version_compare(VersionNumberUtility::getNumericTypo3Version(), '11.0.0') === -1) {
            // Backwards compatibility: for TYPO3 versions lower than 11.0
            return TYPO3_MODE === 'BE';
        } elseif (Environment::isCli()) {
            return true;
        } else {
            return ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend();
        }
    }

    /**
     * Check if the TYPO3 Frontend mode is currently used
     * @return bool
     */
    public function isFrontend(): bool
    {
        if (version_compare(VersionNumberUtility::getNumericTypo3Version(), '11.0.0') === -1) {
            // Backwards compatibility: for TYPO3 versions lower than 11.0
            return TYPO3_MODE === 'FE';
        } elseif (Environment::isCli()) {
            return false;
        } else {
            return ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend();
        }
    }
}
