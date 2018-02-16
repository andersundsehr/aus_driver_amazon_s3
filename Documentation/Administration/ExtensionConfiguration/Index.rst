.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt




Extension Configuration
-----------------------

Edit in "Extension Manager" the following extension settings:

- "dnsPrefetch": Use DNS prefetching tag: If enabled, a HTML tag will be included which prefetchs the DNS of the current CDN

- "doNotLoadAmazonLib": Don't load Amazon AWS PHP SDK: If enabled, you have to include this files by yourself! (http://aws.amazon.com/de/sdk-for-php/)

- "enablePermissionsCheck": Check S3 permissions for each file and folder. This is disabled by default because it is very slow (TYPO3 has to make an AWS request for each file)
