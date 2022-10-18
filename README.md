[![Packagist Release](https://img.shields.io/packagist/v/andersundsehr/aus-driver-amazon-s3.svg?style=flat-square)](https://packagist.org/packages/andersundsehr/aus-driver-amazon-s3)
[![Packagist Downloads](https://img.shields.io/packagist/dt/andersundsehr/aus-driver-amazon-s3.svg?style=flat-square)](https://packagist.org/packages/andersundsehr/aus-driver-amazon-s3)
[![GitHub License](https://img.shields.io/github/license/andersundsehr/aus_driver_amazon_s3.svg?style=flat-square)](https://github.com/andersundsehr/aus_driver_amazon_s3/blob/master/LICENSE.txt)
[![Travis](https://img.shields.io/travis/andersundsehr/aus_driver_amazon_s3.svg?style=flat-square)](https://travis-ci.org/andersundsehr/aus_driver_amazon_s3)
[![Code Climate](https://img.shields.io/codeclimate/tech-debt/andersundsehr/aus_driver_amazon_s3.svg?style=flat-square)](https://codeclimate.com/github/andersundsehr/aus_driver_amazon_s3)

# TYPO3 Extension: Amazon AWS S3 FAL driver (CDN)

This is a driver for the file abstraction layer (FAL) to support Amazon AWS S3.

You can create a file storage which allows you to upload/download and link the files to an AWS S3 bucket. It also supports the TYPO3 CMS image rendering.

Requires TYPO3 10.4 - 11.5

German blog post: [TYPO3 CDN with Amazon S3](https://www.andersundsehr.com/blog/typo3-performance-optimierung-durch-content-delivery-network-cdn/)

Issue tracking: [GitHub: AWS S3 FAL Driver](https://github.com/andersundsehr/aus_driver_amazon_s3/issues)

Packagist: [andersundsehr/aus-driver-amazon-s3](https://packagist.org/packages/andersundsehr/aus-driver-amazon-s3)


## Installation

1.  Install the TYPO3 extension via composer (recommended) or install the extension via TER (not recommended anymore).

> Composer installation:
>
> ```bash
> composer require andersundsehr/aus-driver-amazon-s3
> ```

1.  Add a new file storage with the “AWS S3” driver to root page (pid = 0).
2.  Configure your file storage

## Configuration

### Driver Configuration

Add the following configurations:

-   Bucket: The name of your AWS S3 bucket
-   Region: The region of your bucket (avoid dots in the bucket name)
-   Key and secret key of your AWS account (see security credentials -&gt; access keys)
-   Public base url (optional): this is the public url of your bucket, if empty its default to “bucketname.s3.amazonaws.com”
-   Protocol: network protocol (https://, http:// or auto detection)

#### Hint: Amazon AWS S3 bucket configuration

Make sure that your AWS S3 bucket is accessible to public web users.

For example add the following default permissions to “Edit bucket policy”:

Example permissions:

```json
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
```

### Extension Configuration

Edit in “Extension Manager” the following extension settings:

-   **dnsPrefetch** Use DNS prefetching tag: If enabled, an HTML tag will be included which prefetchs the DNS of the current CDN
-   **doNotLoadAmazonLib** Don’t load Amazon AWS PHP SDK: If enabled, you have to include the SDK by yourself! (<http://aws.amazon.com/de/sdk-for-php/>)
-   **enablePermissionsCheck** Check S3 permissions for each file and folder. This is disabled by default because it is very slow (TYPO3 has to make an AWS request for each file)

## Extend Extension

### Initialize S3 Client

If you use your own Amazon AWS SDK, you may want to work with your own S3 client object.

So you have to use the following hook in your own ext\_loaclconf.php:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['aus_driver_amazon_s3']['initializeClient-preProcessing'][] = \Vendor\ExtensionName\Hooks\AmazonS3DriverHook::class . '->initializeClient';
```

A hook class might look like this:

```php
namespace Vendor\ExtensionName\Hooks;

class AmazonS3DriverHook {

  public function initializeClient(array &$params, $obj){
    $params['s3Client'] = MyAwsFactory::getAwsS3Client($params['configuration']);
  }
}
```

### Initialize public base URL

You can set the public base URL in the configuration of your driver (TYPO3 backend).
But maybe you want to set this on an other place.

So you have to use the following hook in your own ext\_loaclconf.php:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['aus_driver_amazon_s3']['initializeBaseUrl-postProcessing'][] = \Vendor\ExtensionName\Hooks\AmazonS3DriverHook::class . '->initializeBaseUrl';
```

A hook class might look like this:

```php
namespace Vendor\ExtensionName\Hooks;

class AmazonS3DriverHook {

  public function initializeBaseUrl(array &$params, $obj){
    $params['baseUrl'] = 'https://example.com';
  }
}
```

### Cache Control Header

There is a default setting to set the cache control header's max age for all file types. If you want to use special cache headers, you can use this hook:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['aus_driver_amazon_s3']['getCacheControl'][] = 'Vendor\ExtensionName\Hooks\AmazonS3DriverHook->getCacheControl';
```

You can modify the parameter "cacheControl" as you wish. Please Notice: AWS S3 set the cache header only once - while uploading / creating or copy the file.

### More

If you wish other hooks - don’t be shy: [GitHub issue tracking: Amazon S3 FAL Driver](https://github.com/andersundsehr/aus_driver_amazon_s3/issues)
