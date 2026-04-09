.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt




Extension Configuration
-----------------------

Edit in "Extension Manager" the following extension settings:

- "dnsPrefetch": Use DNS prefetching tag: If enabled, an HTML tag will be included which prefetchs the DNS of the current CDN

- "enablePermissionsCheck": Check S3 permissions for each file and folder. This is disabled by default because it is very slow (TYPO3 has to make an AWS request for each file)
