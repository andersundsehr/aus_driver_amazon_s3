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

use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileNameException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is used to place functions which are more complex because keeping compatibility to older TYPO3 versions.
 * The functions can be removed here, if the specific TYPO3 version is not supported anymore.
 */
class FileNameService implements SingletonInterface
{
    protected $unsafeFilenameCharacterExpression = '\\x00-\\x2C\\/\\x3A-\\x3F\\x5B-\\x60\\x7B-\\xBF';

    /**
     * @var CharsetConverter
     */
    protected $charsetConversion = null;

    /**
     * Returns a string where any character not matching [.a-zA-Z0-9_-] is
     * substituted by '_'
     * Trailing dots are removed
     * @param string $fileName Input string, typically the body of a fileName
     * @param string $charset Charset of the a fileName (defaults to current charset; depending on context)
     * @return string Output string with any characters not matching [.a-zA-Z0-9_-] is substituted by '_' and trailing dots removed
     * @throws InvalidFileNameException
     */
    public function sanitizeFileName(string $fileName, string $charset = ''): string
    {
        $fileName = $this->getCharsetConversion()->specCharsToASCII('utf-8', $fileName);
        // Replace unwanted characters by underscores
        $cleanFileName = preg_replace(
            '/[' . $this->unsafeFilenameCharacterExpression . '\\xC0-\\xFF]/',
            '_',
            trim($fileName)
        );

        // Strip trailing dots and return
        $cleanFileName = rtrim($cleanFileName, '.');
        if ($cleanFileName === '') {
            throw new InvalidFileNameException(
                'File name ' . $fileName . ' is invalid.',
                1320288991
            );
        }
        return $cleanFileName;
    }

    protected function getCharsetConversion(): CharsetConverter
    {
        if (!isset($this->charsetConversion)) {
            $this->charsetConversion = GeneralUtility::makeInstance(CharsetConverter::class);
        }
        return $this->charsetConversion;
    }
}
