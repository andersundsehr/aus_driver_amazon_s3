.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt




Driver Configuration
--------------------

Add the following configurations:

- Bucket: The name of your AWS S3 bucket

- Region: The region of your bucket (avoid dots in the bucket name)

- Key and secret key of your AWS account (optional, you can also use IAM roles or environment variables)

- Public base url (optional): this is the public url of your bucket, if empty its default to "bucketname.s3.amazonaws.com"

- Default cache header: max-age in seconds (optional) - Please Notice: AWS S3 set the cache header only once - while uploading / creating or copy the file.

- Protocol: network protocol (https://, http:// or auto detection)

- Signature: Here you can set the signature manually to "Version 4" - "auto" should usually work



Example configuration:
^^^^^^^^^^^^^^^^^^^^^^

.. figure:: ../../Images/aus_driver_amazon_s3-config.png
	:alt: AWS S3 config

	AWS S3 config






Hint: Amazon AWS S3 bucket configuration
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Make sure that your AWS S3 bucket is accessible to public web users.

For example add the following default permissions to "Edit bucket policy":

.. figure:: ../../Images/aus_driver_amazon_s3-aws_config.png
	:alt: S3 bucket config

	S3 bucket config

Example permissions:

.. code-block:: none

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



Overriding storage configuration
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

It is possible to override the driver configuration in the
`config/system/additional.php` file.
This allows to use environment variable based storage configuration,
and no secret keys need to be stored in the database anymore.

Record-based storage configuration can be overridden by defining

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['aus_driver_amazon_s3']['storage']

or

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['aus_driver_amazon_s3']['storage_X']

(where X is the UID of the storage record) in `config/system/additional.php`.

Storage configuration in the database record is merged with the generic 'storage'
configuration, which then with the uid-specific storage config.

Example for defining the credentials in `config/system/additional.php`:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['aus_driver_amazon_s3']['storage_23'] = [
       'key'       => $_ENV['S3_KEY'],
       'secretKey' => $_ENV['S3_SECRET'],
   ];
