<?php
namespace AUS\AusDriverAmazonS3\Signal;

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use AUS\AusDriverAmazonS3\Index\Extractor;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Markus Hölzle <m.hoelzle@andersundsehr.com>, anders und sehr GmbH
 *  Stefan Lamm <s.lamm@andersundsehr.com>, anders und sehr GmbH
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Signals for metadata update
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>
 * @author Stefan Lamm <s.lamm@andersundsehr.com>
 */
class FileIndexRepository {

	/**
	 * @param array $data
	 * @return void|null
	 */
	public function recordUpdatedOrCreated($data) {
		if ($data['type'] === File::FILETYPE_IMAGE) {
			/* @var $storage \TYPO3\CMS\Core\Resource\ResourceStorage */
			$storage = ResourceFactory::getInstance()->getStorageObject($data['storage']);

			// only process on our driver type where data was missing
			if ($storage->getDriverType() !== AmazonS3Driver::DRIVER_TYPE) {
				return NULL;
			}

			$file = $storage->getFile($data['identifier']);
			$imageDimensions = Extractor::getImageDimensionsOfRemoteFile($file);

			if ($imageDimensions !== NULL) {
				/* @var $metaDataRepository \TYPO3\CMS\Core\Resource\Index\MetaDataRepository */
				$metaDataRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\Index\\MetaDataRepository');
				$metaData = $metaDataRepository->findByFileUid($data['uid']);

				$metaData['width'] = $imageDimensions[0];
				$metaData['height'] = $imageDimensions[1];
				$metaDataRepository->update($data['uid'], $metaData);
			}
		}
	}

}