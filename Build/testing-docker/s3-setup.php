<?php

declare(strict_types=1);

/**
 * Prepares the S3 server for the functional tests: creates the bucket "test-bucket",
 * allows anonymous downloads and uploads the files from Tests/Functional/Bucketfiles.
 * The service "s3_setup" in docker-compose.yml runs this script before the functional tests.
 */

use Aws\S3\S3Client;

require __DIR__ . '/../../.Build/vendor/autoload.php';

$bucket = 'test-bucket';
$region = 'eu-central-1';

$s3Client = new S3Client([
    'version' => 'latest',
    'region' => $region,
    'endpoint' => 'http://s3:9000',
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key' => 'test-key',
        'secret' => 'test-secretkey',
    ],
]);

$s3Client->createBucket([
    'Bucket' => $bucket,
    'CreateBucketConfiguration' => ['LocationConstraint' => $region],
]);

$s3Client->putBucketPolicy([
    'Bucket' => $bucket,
    'Policy' => json_encode([
        'Version' => '2012-10-17',
        'Statement' => [
            [
                'Effect' => 'Allow',
                'Principal' => ['AWS' => ['*']],
                'Action' => ['s3:GetObject'],
                'Resource' => ['arn:aws:s3:::' . $bucket . '/*'],
            ],
        ],
    ], JSON_THROW_ON_ERROR),
]);

$sourceFolder = realpath(__DIR__ . '/../../Tests/Functional/Bucketfiles');
if ($sourceFolder === false) {
    throw new RuntimeException('Tests/Functional/Bucketfiles not found', 1763640000);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceFolder, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file instanceof SplFileInfo) {
        continue;
    }

    $s3Client->putObject([
        'Bucket' => $bucket,
        'Key' => substr($file->getPathname(), strlen($sourceFolder) + 1),
        'SourceFile' => $file->getPathname(),
    ]);
}
