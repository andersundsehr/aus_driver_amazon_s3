<?php

declare(strict_types=1);

use AUS\AusDriverAmazonS3\Utilities\ResourceStorage\ResourceStorageFunctions;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\SourceScheme;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Type\Map;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Start collecting all imagesererurls
$uriValues = [];

// append all S3 Storages
$flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
$storages = ResourceStorageFunctions::findByStorageType('AusDriverAmazonS3');

if (!empty($storages)) {
    foreach ($storages as $s) {
        $flexForm = $flexFormService->convertFlexFormContentToArray($s['configuration']);

        if (!empty($flexForm)) {

            if (isset($flexForm['publicBaseUrl']) && $flexForm['publicBaseUrl'] !== '') {
                $storageUrl = rtrim($flexForm['publicBaseUrl'], '/');
            } else {
                $storageUrl = ($flexForm['bucket'] ?? '') . '.s3.amazonaws.com';
            }

            $protocol = $flexForm['protocol'] ?? '';
            $storageUrl = ($protocol == 'auto' ? '' : $protocol) . $storageUrl;
            if (!empty($storageUrl)) {
                $uriValues[] = new UriValue($storageUrl);
            }
        }
    }
}

return Map::fromEntries([
    // Provide declarations for the backend
    Scope::backend(),
    new MutationCollection(
        new Mutation(
            MutationMode::Extend,
            Directive::ImgSrc,
            SourceScheme::data,
            ...$uriValues
        )
    )
]);
