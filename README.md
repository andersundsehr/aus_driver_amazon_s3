[![Packagist Release](https://img.shields.io/packagist/v/andersundsehr/aus-driver-amazon-s3.svg?style=flat-square)](https://packagist.org/packages/andersundsehr/aus-driver-amazon-s3)
[![Packagist Downloads](https://img.shields.io/packagist/dt/andersundsehr/aus-driver-amazon-s3.svg?style=flat-square)](https://packagist.org/packages/andersundsehr/aus-driver-amazon-s3)
[![GitHub License](https://img.shields.io/github/license/andersundsehr/aus_driver_amazon_s3.svg?style=flat-square)](https://github.com/andersundsehr/aus_driver_amazon_s3/blob/master/LICENSE.txt)
[![Code Climate](https://img.shields.io/codeclimate/tech-debt/andersundsehr/aus_driver_amazon_s3.svg?style=flat-square)](https://codeclimate.com/github/andersundsehr/aus_driver_amazon_s3)
[![Contributors](https://img.shields.io/github/contributors/andersundsehr/aus_driver_amazon_s3?style=flat-square)](https://github.com/andersundsehr/aus_driver_amazon_s3/graphs/contributors)

# TYPO3 Extension: Amazon AWS S3 FAL driver (CDN)

This is a driver for the file abstraction layer (FAL) to support Amazon AWS S3.

You can create a file storage which allows you to upload/download and link the files to an AWS S3 bucket. It also supports the TYPO3 CMS image rendering.

Requires TYPO3 11.5 - 12.4

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
-   Key and secret key of your AWS account (optional, you can also use IAM roles or environment variables)
-   Public base url (optional): this is the public url of your bucket, if empty its default to “bucketname.s3.amazonaws.com”
-   Protocol: network protocol (https://, http:// or auto detection)

#### File hash setting

Choose **Method for file hash** in the file storage's driver configuration. TYPO3 uses the result as a file content hash, so an identifier-based pseudo hash does not change when the contents of a file at the same path change.

| Setting | How the hash is obtained | When to use it |
| --- | --- | --- |
| **Ignore** (`ignore`, default) | Returns a pseudo hash based on the file identifier. No S3 request is needed to calculate the hash. | Fastest choice for large existing buckets or bulk indexing when content hashes are not required. |
| **Receive from S3** (`receive`) | Reads the requested hash from S3 object metadata. If it is missing, returns the identifier-based pseudo hash. Looking up uncached metadata requires a HEAD request per file. | Choose when files have stored hash metadata and you want content hashes without downloading the files. |
| **Download and calculate** (`force`) | Uses the hash in S3 metadata if present. Otherwise downloads the entire object and calculates the requested hash. | Choose only when a real content hash is required even for objects without stored hash metadata. This can be slow and generate substantial network traffic during indexing. |
| **Require from S3** (`strict`) | Reads the requested hash from S3 object metadata. If it is missing, throws a `RuntimeException`. Looking up uncached metadata requires a HEAD request per file; the object body is never downloaded for hashing. | Choose when every hash must describe the file contents, downloads are unacceptable, and missing metadata should fail the operation. |

Choose `ignore` for speed when a pseudo hash is acceptable. Choose `receive` to use stored content hashes where available while accepting pseudo hashes for other files. Choose `force` when a content hash is required and downloading objects with missing hashes is acceptable. Choose `strict` when a content hash is required without downloading: a missing hash causes the calling operation (such as indexing) to fail, so ensure the metadata exists first.

The requested algorithm must have a matching metadata key: for example, `hash-sha1` for SHA-1. Having only `hash-md5` does not satisfy a SHA-1 request. `strict` is a separate fourth choice; selecting `receive` still allows the existing pseudo hash fallback.

For nonempty files uploaded through this extension while `receive`, `force`, or `strict` is selected, the driver stores MD5, SHA-1, and SHA-256 hashes in S3 object metadata. Existing objects and files uploaded with `ignore` may lack that metadata; changing the setting does not add hashes to those objects. For a large bucket without hash metadata, start with `ignore` for bulk indexing and select a content-hash mode only after assessing the cost and the need for content-based change detection.

Changing the setting also does not update hashes already saved in TYPO3's file index. Those entries keep their previous hash until they are reindexed, and a saved pseudo hash does not represent the file contents. Reindexing with `receive` still produces a pseudo hash for objects without matching hash metadata; reindexing with `force` downloads those objects to calculate a content hash; reindexing with `strict` throws an exception for those objects.

To refresh **all** existing hashes after changing the setting, use a one-off TYPO3 CLI command that processes the storage's existing `sys_file` records in batches and calls [`Indexer::updateIndexEntry()`](https://api.typo3.org/13.4/classes/TYPO3-CMS-Core-Resource-Index-Indexer.html) for each file. The standard ["File abstraction layer: Update storage index" Scheduler task](https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ApiOverview/Fal/Administration/Maintenance.html) only updates existing files whose modification time has changed; it has no option to force an update of every unchanged file. Keep the existing `sys_file` records and their UIDs so file references remain intact. With `force`, plan for a full download of every object lacking hash metadata.

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
-   **enablePermissionsCheck** Check S3 permissions for each file and folder. This is disabled by default because it is very slow (TYPO3 has to make an AWS request for each file)

### Cache Configuration

# Customizing TYPO3 Cache Backends

- `ausdriveramazons3_metainfocache` retains metadata from AWS S3 on a per-object basis.
- `ausdriveramazons3_requestcache` stores the complete response of a specific request, facilitating efficient data access and performance enhancement.

By default, these caches are transient. However, if you choose to configure a persistent cache backend, it's crucial to remember that such a cache will not automatically recognize changes from the data source. In this case, it becomes your responsibility to implement the necessary updates manually.

Detailed instructions on how to customize these cache backends can be found in the [TYPO3 CachingFramework Configuration Guide](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/CachingFramework/Configuration/Index.html). Remember, thorough testing is essential when modifying cache backends.

Example with simple file backend; all changes through TYPO3
```php
[
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend::class,
    'groups' => [
        'pages'
    ],
];
```
Example with redis
```php
[
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\RedisBackend::class,
    'options' => [
        'defaultLifetime' => 0, // infinite
        'database' => 0,
        'hostname' => 'redis',
        'port' => 6379,
    ],
];
```

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


# Development

## Running tests

Run `make tests` to run both unit and functional tests.

To switch TYPO3 test version to 11:
```bash
composer update --with typo3/cms-core:^11.5.6
```
To switch TYPO3 test version to 12:
```bash
composer update --with typo3/cms-core:^12.4.0 --ignore-platform-req=ext-intl
```
