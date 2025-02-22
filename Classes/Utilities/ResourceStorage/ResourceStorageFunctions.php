<?php

namespace AUS\AusDriverAmazonS3\Utilities\ResourceStorage;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ResourceStorageFunctions
{

    /**
     * Select the storage dataset by given storageType.
     *
     * @param string $storageType
     *
     * @return array
     */
    public static function findByStorageType(string $storageType): array
    {
        if (empty($storageType)) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_storage');

        $result = $queryBuilder
            ->select('*')
            ->from('sys_file_storage')
            ->where($queryBuilder->expr()->eq('driver', $queryBuilder->createNamedParameter($storageType, Connection::PARAM_STR)))
            ->executeQuery()
            ->fetchAllAssociative();

        return empty($result) ? [] : $result;
    }
}
