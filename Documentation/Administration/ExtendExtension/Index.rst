.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt




Extend Extension
----------------


Initialize S3 Client:
^^^^^^^^^^^^^^^^^^^^^

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


Cache Control Header:
^^^^^^^^^^^^^^^^^^^^^

There is a default setting to set the cache control header's max age for all file types. If you want to use special cache headers, you can use this hook: ::

	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['aus_driver_amazon_s3']['getCacheControl'][] = 'Vendor\ExtensionName\Hooks\AmazonS3DriverHook->getCacheControl';

You can modify the parameter "cacheControl" as you wish. Please Notice: Amazon S3 set the cache header only once - while uploading / creating or copy the file.

More features:
^^^^^^^^^^^^^^

If you wish other hooks - don't be shy: `TYPO3 Forge: Amazon S3 FAL Driver <http://forge.typo3.org/projects/extension-aus_driver_amazon_s3>`_