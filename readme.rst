What does it do?
================

This is a driver for the file abstraction layer (FAL) to support Amazon AWS S3.

You can create a file storage which allows you to upload/download and link the files to an AWS S3 bucket. It also supports the TYPO3 CMS image rendering.

Requires TYPO3 6.2 - 8.x

German blog post: `TYPO3 CDN with Amazon S3 <http://www.andersundsehr.com/blog/technik/typo3-performance-optimierung-durch-cdn>`_

Issue tracking: `GitHub: Amazon S3 FAL Driver <https://github.com/andersundsehr/aus_driver_amazon_s3/issues>`_



Administrator Manual
====================

Installation
------------

1. Install the TYPO3 extension via composer (recommended):

  .. code-block::

    composer require andersundsehr/aus-driver-amazon-s3

  or install the extension via TER (not recommended anymore)

2. Add a new file storage with the "Amazon S3" driver to root page (pid = 0).

3. Configure your file storage


Driver Configuration
--------------------

Add the following configurations:

- Bucket: The name of your AWS S3 bucket

- Region: The region of your bucket (avoid dots in the bucket name)

- Key and secret key of your AWS account (see security credentials -> access keys)

- Public base url (optional): this is the public url of your bucket, if empty its default to "bucketname.s3.amazonaws.com"

- Protocol: network protocol (https://, http:// or auto detection)



Hint: Amazon AWS S3 bucket configuration
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Make sure that your AWS S3 bucket is accessible to public web users.

For example add the following default permissions to "Edit bucket policy":

Example permissions:

.. code-block:: javascript

  {
  	"Version": "2008-10-17",
  	"Statement": [
  		{
  			"Sid": "AddPerm",
  			"Effect": "Allow",
  			"Principal": "*",
  			"Action": "s3:GetObject",
  			"Resource": "arn:aws:s3:::bucketname/*"
  		}
  	]
  }




Extension Configuration
-----------------------

Edit in "Extension Manager" the following extension settings:

- Use DNS prefetching tag: If enabled, a HTML tag will be included which prefetchs the DNS of the current CDN

- Don't load Amazon AWS PHP SDK: If enabled, you have to include this files by yourself! (http://aws.amazon.com/de/sdk-for-php/)




Extend Extension
----------------

If you use your own Amazon AWS SDK, you may want to work with your own S3 client object.

So you have to use the following hook in your own ext_loaclconf.php:

.. code-block:: php

  $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['aus_driver_amazon_s3']['initializeClient-preProcessing'][] = \Vendor\ExtensionName\Hooks\AmazonS3DriverHook::class . '->initializeClient';

A hook class might look like this:

.. code-block:: php

  namespace Vendor\ExtensionName\Hooks;

  class AmazonS3DriverHook {

    public function initializeClient(array &$params, $obj){
      $params['s3Client'] = MyAwsFactory::getAwsS3Client($params['configuration']);
    }
  }


If you wish other hooks - don't be shy: `GitHub issue tracking: Amazon S3 FAL Driver <https://github.com/andersundsehr/aus_driver_amazon_s3/issues>`_
