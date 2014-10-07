.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt




Extend Extension
----------------

If you use your own Amazon AWS SDK, you may want to work with your own S3 client object.

So you have to use the following hook in your own ext_loaclconf.php: ::

	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['aus_driver_amazon_s3']['initializeClient-preProcessing'][] = 'Vendor\ExtensionName\Hooks\AmazonS3DriverHook->initializeClient';

A hook class might look like this: ::

	namespace Vendor\ExtensionName\Hooks;

	class AmazonS3DriverHook {

		public function initializeClient(&$params, $obj){
			$params['s3Client'] = MyAwsFactory::getAwsS3Client($params['configuration']);
		}

	}


If you wish other hooks - don't be shy: `TYPO3 Forge: Amazon S3 FAL Driver <http://forge.typo3.org/projects/extension-aus_driver_amazon_s3>`_