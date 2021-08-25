<?php

/***
 *
 * This file is part of an extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2020 Markus Hölzle <typo3@markus-hoelzle.de>
 *
 ***/

namespace AUS\AusDriverAmazonS3\Driver;

use AUS\AusDriverAmazonS3\S3Adapter\MetaInfoDownloadAdapter;
use AUS\AusDriverAmazonS3\S3Adapter\MultipartUploaderAdapter;
use Aws\S3\S3Client;
use Aws\S3\StreamWrapper;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorageInterface;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver;
use TYPO3\CMS\Core\Resource\Exception;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class AmazonS3Driver
 * Driver for Amazon Simple Storage Service (S3)
 *
 * @author Markus Hölzle <typo3@markus-hoelzle.de>
 * @package AUS\AusDriverAmazonS3\Driver
 */
class AmazonS3Driver extends AbstractHierarchicalFilesystemDriver
{
    const DRIVER_TYPE = 'AusDriverAmazonS3';

    const EXTENSION_KEY = 'aus_driver_amazon_s3';

    const EXTENSION_NAME = 'AusDriverAmazonS3';

    const FILTER_ALL = 'all';

    const FILTER_FOLDERS = 'folders';

    const FILTER_FILES = 'files';

    const ROOT_FOLDER_IDENTIFIER = '/';

    const UNSAFE_FILENAME_CHARACTER_EXPRESSION = '\\x00-\\x2C\\/\\x3A-\\x3F\\x5B-\\x60\\x7B-\\xBF';

    /**
     * @var S3Client
     */
    protected $s3Client = null;

    /**
     * The base URL that points to this driver's storage. As long is this is not set, it is assumed that this folder
     * is not publicly available
     *
     * @var string
     */
    protected $baseUrl = '';

    /**
     * Stream wrapper protocol: Will be set in the constructor
     *
     * @var string
     */
    protected $streamWrapperProtocol = '';

    /**
     * The identifier map used for renaming
     *
     * @var array
     */
    protected $identifierMap = [];

    /**
     * Object meta data is cached here as array or null
     * $identifier => [meta info as array]
     *
     * @var array[]
     */
    protected $metaInfoCache = [];

    /**
     * Generic request -> response cache
     * Used for 'listObjectsV2' until now
     *
     * @var array[][]
     */
    protected $requestCache = [
        'listObjectsV2' => [],
    ];

    /**
     * Object permissions are cached here in subarrays like:
     * $identifier => ['r' => bool, 'w' => bool]
     *
     * @var array
     */
    protected $objectPermissionsCache = [];

    /**
     * Processing folder
     *
     * @var string
     */
    protected $processingFolder = '';

    /**
     * Default processing folder
     *
     * @var string
     */
    protected $processingFolderDefault = '_processed_';

    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceStorage
     */
    protected $storage = null;

    /**
     * @var array
     */
    protected static $settings = null;

    /**
     * @var \TYPO3\CMS\Core\Charset\CharsetConverter
     */
    protected $charsetConversion = null;

    /**
     * @var string
     */
    protected $languageFile = 'EXT:aus_driver_amazon_s3/Resources/Private/Language/locallang_flexform.xlf';

    /**
     * @var array
     */
    protected $temporaryPaths = [];

    /**
     * @var Dispatcher
     */
    protected $signalSlotDispatcher;

    /**
     * AmazonS3Driver constructor.
     *
     * @param array $configuration
     * @param S3Client $s3Client
     */
    public function __construct(array $configuration = [], $s3Client = null)
    {
        parent::__construct($configuration);
        // The capabilities default of this driver. See CAPABILITY_* constants for possible values
        $this->capabilities =
            ResourceStorage::CAPABILITY_BROWSABLE
            | ResourceStorage::CAPABILITY_PUBLIC
            | ResourceStorage::CAPABILITY_WRITABLE;
        $this->streamWrapperProtocol = 's3-' . substr(md5(uniqid()), 0, 7);
        $this->s3Client = $s3Client;
    }

    /**
     * Remove temporary used files.
     * This is a poor software architecture style: temp files should be deleted by the FAL users and not by the FAL drivers
     * @see https://forge.typo3.org/issues/56982
     * @see https://review.typo3.org/#/c/36446/
     */
    public function __destruct()
    {
        foreach ($this->temporaryPaths as $temporaryPath) {
            @unlink($temporaryPath);
        }
    }

    /**
     * loadExternalClasses
     * @throws \Exception
     */
    public static function loadExternalClasses()
    {
        // Backwards compatibility: for TYPO3 versions lower than 10.0
        $loadSdk = !Environment::isComposerMode() && !function_exists('Aws\\manifest');
        if ($loadSdk) {
            require ExtensionManagementUtility::extPath(self::EXTENSION_KEY) . '/Resources/Private/PHP/vendor/autoload.php';
        }
    }

    /**
     * @return void
     */
    public function processConfiguration()
    {
    }

    /**
     * @return void
     */
    public function initialize()
    {
        $this->initializeBaseUrl()
            ->initializeSettings()
            ->initializeClient();
        // Test connection if we are in the edit view of this storage
        if (TYPO3_MODE === 'BE' && !empty($_GET['edit']['sys_file_storage'])) {
            $this->testConnection();
        }
    }

    /**
     * @param string $identifier
     * @return string
     */
    public function getPublicUrl($identifier)
    {
        $uriParts = GeneralUtility::trimExplode('/', ltrim($identifier, '/'), true);
        $uriParts = array_map('rawurlencode', $uriParts);
        return $this->baseUrl . '/' . implode('/', $uriParts);
    }

    /**
     * Creates a (cryptographic) hash for a file.
     *
     * @param string $fileIdentifier
     * @param string $hashAlgorithm
     * @return string
     */
    public function hash($fileIdentifier, $hashAlgorithm)
    {
        return $this->hashIdentifier($fileIdentifier);
    }

    /**
     * Returns the identifier of the default folder new files should be put into.
     *
     * @return string
     */
    public function getDefaultFolder()
    {
        return $this->getRootLevelFolder();
    }

    /**
     * Returns the identifier of the root level folder of the storage.
     *
     * @return string
     */
    public function getRootLevelFolder()
    {
        return '/';
    }

    /**
     * Returns information about a file.
     *
     * @param string $fileIdentifier
     * @param array $propertiesToExtract Array of properties which are be extracted
     *                                    If empty all will be extracted
     * @return array
     * @throws \Exception
     */
    public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = [])
    {
        if (in_array('mimetype', $propertiesToExtract)) {
            // force to reload the infos from S3 if the mime type was requested
            $this->flushMetaInfoCache($fileIdentifier);
        }
        $return = $this->getMetaInfo($fileIdentifier);
        if ($return === null) {
            throw new \Exception('File does not exist', 1503500470);
        }
        if (count($propertiesToExtract) > 0) {
            $return = array_intersect_key($return, array_flip($propertiesToExtract));
        }
        return $return;
    }

    /**
     * Checks if a file exists
     *
     * @param string $identifier
     * @return bool
     */
    public function fileExists($identifier)
    {
        if (substr($identifier, -1) === '/' || $identifier === '') {
            return false;
        }
        return $this->objectExists($identifier);
    }

    /**
     * Checks if a folder exists
     *
     * @param string $identifier
     * @return bool
     */
    public function folderExists($identifier)
    {
        if ($identifier === self::ROOT_FOLDER_IDENTIFIER) {
            return true;
        }
        if (substr($identifier, -1) !== '/') {
            $identifier .= '/';
        }
        return $this->prefixExists($identifier);
    }

    /**
     * @param string $fileName
     * @param string $folderIdentifier
     * @return bool
     */
    public function fileExistsInFolder($fileName, $folderIdentifier)
    {
        return $this->objectExists($folderIdentifier . $fileName);
    }

    /**
     * Checks if a folder exists inside a storage folder
     *
     * @param string $folderName
     * @param string $folderIdentifier
     * @return bool
     */
    public function folderExistsInFolder($folderName, $folderIdentifier)
    {
        return $this->prefixExists(rtrim($folderIdentifier, '/') . '/' . $folderName . '/');
    }

    /**
     * Returns the Identifier for a folder within a given folder.
     *
     * @param string $folderName The name of the target folder
     * @param string $folderIdentifier
     * @return string
     */
    public function getFolderInFolder($folderName, $folderIdentifier)
    {
        $identifier = $folderIdentifier . '/' . $folderName . '/';
        $this->normalizeIdentifier($identifier);
        return $identifier;
    }

    /**
     * @param string $localFilePath (within PATH_site)
     * @param string $targetFolderIdentifier
     * @param string $newFileName optional, if not given original name is used
     * @param bool $removeOriginal if set the original file will be removed
     *                                after successful operation
     * @return string the identifier of the new file
     * @throws \Exception
     */
    public function addFile($localFilePath, $targetFolderIdentifier, $newFileName = '', $removeOriginal = true)
    {
        $newFileName = $this->sanitizeFileName($newFileName !== '' ? $newFileName : PathUtility::basename($localFilePath));
        $targetIdentifier = $targetFolderIdentifier . $newFileName;
        $localIdentifier = $localFilePath;
        $this->normalizeIdentifier($localIdentifier);

        // if the source file is also in this driver
        if (!is_uploaded_file($localFilePath) && $this->objectExists($localIdentifier)) {
            if ($removeOriginal) {
                rename($this->getStreamWrapperPath($localIdentifier), $this->getStreamWrapperPath($targetIdentifier));
            } else {
                copy($this->getStreamWrapperPath($localIdentifier), $this->getStreamWrapperPath($targetIdentifier));
            }
        } else { // upload local file
            $this->normalizeIdentifier($targetIdentifier);

            if (filesize($localFilePath) === 0) { // Multipart uploader would fail to upload empty files
                $this->s3Client->upload(
                    $this->configuration['bucket'],
                    $targetIdentifier,
                    ''
                );
            } else {
                $multipartUploadAdapter = GeneralUtility::makeInstance(MultipartUploaderAdapter::class, $this->s3Client);
                $multipartUploadAdapter->upload(
                    $localFilePath,
                    $targetIdentifier,
                    $this->configuration['bucket'],
                    $this->getCacheControl($targetIdentifier)
                );
            }

            if ($removeOriginal) {
                unlink($localFilePath);
            }
        }
        $this->flushMetaInfoCache($targetIdentifier);

        return $targetIdentifier;
    }

    /**
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFileName
     *
     * @return string
     */
    public function moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName)
    {
        $targetIdentifier = $targetFolderIdentifier . $newFileName;
        $this->renameObject($fileIdentifier, $targetIdentifier);
        return $targetIdentifier;
    }

    /**
     * Copies a file *within* the current storage.
     * Note that this is only about an inner storage copy action,
     * where a file is just copied to another folder in the same storage.
     *
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $fileName
     * @return string the Identifier of the new file
     */
    public function copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName)
    {
        $targetIdentifier = $targetFolderIdentifier . $fileName;
        $this->copyObject($fileIdentifier, $targetIdentifier);
        return $targetIdentifier;
    }

    /**
     * Replaces a file with file in local file system.
     *
     * @param string $fileIdentifier
     * @param string $localFilePath
     * @return bool TRUE if the operation succeeded
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException
     */
    public function replaceFile($fileIdentifier, $localFilePath)
    {
        $contents = file_get_contents($localFilePath);
        $written = $this->setFileContents($fileIdentifier, $contents);
        $this->flushMetaInfoCache($fileIdentifier);
        return $written > 0;
    }

    /**
     * Removes a file from the filesystem. This does not check if the file is
     * still used or if it is a bad idea to delete it for some other reason
     * this has to be taken care of in the upper layers (e.g. the Storage)!
     *
     * @param string $fileIdentifier
     * @return bool TRUE if deleting the file succeeded
     */
    public function deleteFile($fileIdentifier)
    {
        return $this->deleteObject($fileIdentifier);
    }

    /**
     * Removes a folder in filesystem.
     *
     * @param string $folderIdentifier
     * @param bool $deleteRecursively
     * @return bool
     */
    public function deleteFolder($folderIdentifier, $deleteRecursively = false)
    {
        if ($deleteRecursively) {
            $items = $this->getListObjects($folderIdentifier);
            foreach ($items['Contents'] ?? [] as $object) {
                // Filter the folder itself
                if ($object['Key'] !== $folderIdentifier) {
                    if ($this->isDir($object['Key'])) {
                        $subFolder = $this->getFolder($object['Key']);
                        if ($subFolder) {
                            $this->deleteFolder($subFolder, $deleteRecursively);
                        }
                    } else {
                        unlink($this->getStreamWrapperPath($object['Key']));
                    }
                }
            }
        }

        return $this->deleteObject($folderIdentifier);
    }

    /**
     * Returns a path to a local copy of a file for processing it. When changing the
     * file, you have to take care of replacing the current version yourself!
     * The file will be removed by the driver automatically on destruction.
     *
     * @param string $fileIdentifier
     * @param bool $writable Set this to FALSE if you only need the file for read
     *                         operations. This might speed up things, e.g. by using
     *                         a cached local version. Never modify the file if you
     *                         have set this flag!
     * @return string The path to the file on the local disk
     * @throws \RuntimeException
     * @todo take care of replacing the file on change
     */
    public function getFileForLocalProcessing($fileIdentifier, $writable = true)
    {
        $temporaryPath = $this->getTemporaryPathForFile($fileIdentifier);
        $this->s3Client->getObject([
            'Bucket' => $this->configuration['bucket'],
            'Key' => $fileIdentifier,
            'SaveAs' => $temporaryPath,
        ]);
        if (!is_file($temporaryPath)) {
            throw new \RuntimeException('Copying file ' . $fileIdentifier . ' to temporary path failed.', 1320577649);
        }
        $this->emitGetFileForLocalProcessingSignal($fileIdentifier, $temporaryPath, $writable);
        if (!isset($this->temporaryPaths[$temporaryPath])) {
            $this->temporaryPaths[$temporaryPath] = $temporaryPath;
        }
        return $temporaryPath;
    }

    /**
     * @param string $fileIdentifier
     * @param string $temporaryPath
     * @param bool $writable
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function emitGetFileForLocalProcessingSignal(&$fileIdentifier, &$temporaryPath, &$writable)
    {
        list($fileIdentifier, $temporaryPath, $writable) = $this->getSignalSlotDispatcher()->dispatch(
            self::class,
            'getFileForLocalProcessing',
            [$fileIdentifier, $temporaryPath, $writable]
        );
    }

    /**
     * Creates a new (empty) file and returns the identifier.
     *
     * @param string $fileName
     * @param string $parentFolderIdentifier
     * @return string
     */
    public function createFile($fileName, $parentFolderIdentifier)
    {
        $parentFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($parentFolderIdentifier);
        $identifier = $this->canonicalizeAndCheckFileIdentifier(
            $parentFolderIdentifier . $this->sanitizeFileName(ltrim($fileName, '/'))
        );
        $this->createObject($identifier);
        return $identifier;
    }

    /**
     * Creates a folder, within a parent folder.
     * If no parent folder is given, a root level folder will be created
     *
     * @param string $newFolderName
     * @param string $parentFolderIdentifier
     * @param bool $recursive
     * @return string the Identifier of the new folder
     */
    public function createFolder($newFolderName, $parentFolderIdentifier = '', $recursive = false)
    {
        $parentFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($parentFolderIdentifier);
        $newFolderName = trim($newFolderName, '/');

        if ($recursive === false) {
            $newFolderName = $this->sanitizeFileName($newFolderName);
            $identifier = $parentFolderIdentifier . $newFolderName . '/';
        } else {
            $parts = GeneralUtility::trimExplode('/', $newFolderName);
            $parts = array_map([$this, 'sanitizeFileName'], $parts);
            $newFolderName = implode('/', $parts);
            $identifier = $parentFolderIdentifier . $newFolderName . '/';
        }

        $this->createObject($identifier);
        return $identifier;
    }

    /**
     * Returns the contents of a file. Beware that this requires to load the
     * complete file into memory and also may require fetching the file from an
     * external location. So this might be an expensive operation (both in terms
     * of processing resources and money) for large files.
     *
     * @param string $fileIdentifier
     * @return string The file contents
     */
    public function getFileContents($fileIdentifier)
    {
        $result = $this->s3Client->getObject([
            'Bucket' => $this->configuration['bucket'],
            'Key' => $fileIdentifier
        ]);
        return (string)$result['Body'];
    }

    /**
     * Sets the contents of a file to the specified value.
     *
     * @param string $fileIdentifier
     * @param string $contents
     * @return int The number of bytes written to the file
     */
    public function setFileContents($fileIdentifier, $contents)
    {
        return file_put_contents($this->getStreamWrapperPath($fileIdentifier), $contents);
    }

    /**
     * Renames a file in this storage.
     *
     * @param string $fileIdentifier
     * @param string $newName The target path (including the file name!)
     * @return string The identifier of the file after renaming
     */
    public function renameFile($fileIdentifier, $newName)
    {
        $newName = $this->sanitizeFileName($newName);
        $newIdentifier = rtrim(PathUtility::dirname($fileIdentifier), '/') . '/' . $newName;

        $this->renameObject($fileIdentifier, $newIdentifier);
        return $newIdentifier;
    }

    /**
     * Renames a folder in this storage.
     *
     * @param string $folderIdentifier
     * @param string $newName
     * @return array A map of old to new file identifiers of all affected resources
     */
    public function renameFolder($folderIdentifier, $newName)
    {
        $this->resetIdentifierMap();
        $newName = $this->sanitizeFileName($newName);

        $parentFolderName = PathUtility::dirname($folderIdentifier);
        if ($parentFolderName === '.') {
            $parentFolderName = '';
        } else {
            $parentFolderName .= '/';
        }
        $newIdentifier = $parentFolderName . $newName . '/';

        foreach ($this->getSubObjects($folderIdentifier, false) as $object) {
            $subObjectIdentifier = $object['Key'];
            if ($this->isDir($subObjectIdentifier)) {
                $this->renameSubFolder($this->getFolder($subObjectIdentifier), $newIdentifier);
            } else {
                $newSubObjectIdentifier = $newIdentifier . basename($subObjectIdentifier);
                $this->renameObject($subObjectIdentifier, $newSubObjectIdentifier);
            }
        }

        $this->renameObject($folderIdentifier, $newIdentifier);
        return $this->identifierMap;
    }

    /**
     * Folder equivalent to moveFileWithinStorage().
     *
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     *
     * @return array All files which are affected, map of old => new file identifiers
     */
    public function moveFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        $this->resetIdentifierMap();

        $newIdentifier = $targetFolderIdentifier . $newFolderName . '/';
        $this->renameObject($sourceFolderIdentifier, $newIdentifier);

        $subObjects = $this->getSubObjects($sourceFolderIdentifier);
        $this->sortObjectsForNestedFolderOperations($subObjects);

        foreach ($subObjects as $subObject) {
            $newIdentifier = $targetFolderIdentifier . $newFolderName . '/' . substr(
                $subObject['Key'],
                strlen($sourceFolderIdentifier)
            );
            $this->renameObject($subObject['Key'], $newIdentifier);
        }
        return $this->identifierMap;
    }

    /**
     * Folder equivalent to copyFileWithinStorage().
     *
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     *
     * @return bool
     */
    public function copyFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        $newIdentifier = $targetFolderIdentifier . $newFolderName . '/';
        $this->copyObject($sourceFolderIdentifier, $newIdentifier);

        $subObjects = $this->getSubObjects($sourceFolderIdentifier);
        $this->sortObjectsForNestedFolderOperations($subObjects);

        foreach ($subObjects as $subObject) {
            $newIdentifier = $targetFolderIdentifier . $newFolderName . '/' . substr(
                $subObject['Key'],
                strlen($sourceFolderIdentifier)
            );
            $this->copyObject($subObject['Key'], $newIdentifier);
        }

        return true;
    }

    /**
     * Checks if a folder contains files and (if supported) other folders.
     *
     * @param string $folderIdentifier
     * @return bool TRUE if there are no files and folders within $folder
     */
    public function isFolderEmpty($folderIdentifier)
    {
        $result = $this->getListObjects(
            $folderIdentifier,
            [
                'MaxKeys' => 1
            ]
        );

        // Contents will always include the folder itself
        if (isset($result['Contents']) && sizeof($result['Contents']) > 1) {
            return false;
        }
        return true;
    }

    /**
     * Checks if a given identifier is within a container, e.g. if
     * a file or folder is within another folder.
     * This can e.g. be used to check for web-mounts.
     *
     * Hint: this also needs to return TRUE if the given identifier
     * matches the container identifier to allow access to the root
     * folder of a filemount.
     *
     * @param string $folderIdentifier
     * @param string $identifier identifier to be checked against $folderIdentifier
     * @return bool TRUE if $content is within or matches $folderIdentifier
     */
    public function isWithin($folderIdentifier, $identifier)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFileIdentifier($folderIdentifier);
        $entryIdentifier = $this->canonicalizeAndCheckFileIdentifier($identifier);
        if ($folderIdentifier === $entryIdentifier) {
            return true;
        }
        // File identifier canonicalization will not modify a single slash so
        // we must not append another slash in that case.
        if ($folderIdentifier !== '/') {
            $folderIdentifier .= '/';
        }

        return GeneralUtility::isFirstPartOfStr($entryIdentifier, $folderIdentifier);
    }

    /**
     * Returns information about a file.
     *
     * @param string $folderIdentifier
     * @return array
     */
    public function getFolderInfoByIdentifier($folderIdentifier)
    {
        $this->normalizeIdentifier($folderIdentifier);

        return [
            'identifier' => $folderIdentifier,
            'name' => basename(rtrim($folderIdentifier, '/')),
            'storage' => $this->storageUid
        ];
    }

    /**
     * Returns a file inside the specified path
     *
     * @param string $fileName
     * @param string $folderIdentifier
     * @return string File Identifier
     */
    public function getFileInFolder($fileName, $folderIdentifier)
    {
        $folderIdentifier = $folderIdentifier . '/' . $fileName;
        $this->normalizeIdentifier($folderIdentifier);
        return $folderIdentifier;
    }

    /**
     * Returns a list of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                      Among them may be: '' (empty, no sorting), name,
     *                      fileext, size, tstamp and rw.
     *                      If a driver does not support the given property, it
     *                      should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     *
     * @return array of FileIdentifiers
     * @toDo: Implement $start, $numberOfItems, $sort and $sortRev
     */
    public function getFilesInFolder($folderIdentifier, $start = 0, $numberOfItems = 0, $recursive = false, array $filenameFilterCallbacks = [], $sort = '', $sortRev = false)
    {
        $this->normalizeIdentifier($folderIdentifier);
        $files = [];
        if ($folderIdentifier === self::ROOT_FOLDER_IDENTIFIER) {
            $folderIdentifier = '';
        }

        $overrideArgs = [];
        if (!$recursive) {
            $overrideArgs['Delimiter'] = '/';
        }
        $response = $this->getListObjects($folderIdentifier, $overrideArgs);
        if ($response['Contents']) {
            foreach ($response['Contents'] as $fileCandidate) {
                // skip directory entries
                if (substr($fileCandidate['Key'], -1) === '/') {
                    continue;
                }

                // skip subdirectory entries
                if (!$recursive && substr_count($fileCandidate['Key'], '/') > substr_count($folderIdentifier, '/')) {
                    continue;
                }

                $fileName = basename($fileCandidate['Key']);
                // check filter
                if (
                    !$this->applyFilterMethodsToDirectoryItem(
                        $filenameFilterCallbacks,
                        $fileName,
                        $fileCandidate['Key'],
                        $folderIdentifier
                    )
                ) {
                    continue;
                }

                $files[$fileCandidate['Key']] = $fileCandidate['Key'];
            }
        }
        if ($numberOfItems > 0) {
            return array_splice($files, $start, $numberOfItems);
        } else {
            return $files;
        }
    }

    /**
     * Returns the number of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @return int Number of files in folder
     */
    public function countFilesInFolder($folderIdentifier, $recursive = false, array $filenameFilterCallbacks = [])
    {
        return count($this->getFilesInFolder($folderIdentifier, 0, 0, $recursive, $filenameFilterCallbacks));
    }

    /**
     * Returns a list of folders inside the specified path
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                      Among them may be: '' (empty, no sorting), name,
     *                      fileext, size, tstamp and rw.
     *                      If a driver does not support the given property, it
     *                      should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     *
     * @return array of Folder Identifier
     * @toDo: Implement params $start, $numberOfItems, $sort, $sortRev
     */
    public function getFoldersInFolder($folderIdentifier, $start = 0, $numberOfItems = 0, $recursive = false, array $folderNameFilterCallbacks = [], $sort = '', $sortRev = false)
    {
        $this->normalizeIdentifier($folderIdentifier);
        $folders = [];

        $folderIdentifier = $folderIdentifier === self::ROOT_FOLDER_IDENTIFIER ? '' : $folderIdentifier;
        if ($recursive) {
            // search folders recursive
            $response = $this->getListObjects($folderIdentifier);
            if ($response['Contents']) {
                foreach ($response['Contents'] as $folderCandidate) {
                    $key = '/' . $folderCandidate['Key'];
                    $folderName = basename(rtrim($key, '/'));

                    // filter only folders
                    if (substr($key, -1) !== '/') {
                        continue;
                    }
                    if (!$this->applyFilterMethodsToDirectoryItem($folderNameFilterCallbacks, $folderName, $key, $folderIdentifier)) {
                        continue;
                    }
                    if ($folderName === $this->getProcessingFolder()) {
                        continue;
                    }

                    $folders[$key] = $key;
                }
            }
        } else {
            // search folders on the current level (non-recursive)
            $response = $this->getListObjects($folderIdentifier, ['Delimiter' => '/']);
            if ($response['CommonPrefixes']) {
                foreach ($response['CommonPrefixes'] as $folderCandidate) {
                    $key = '/' . $folderCandidate['Prefix'];
                    $folderName = basename(rtrim($key, '/'));
                    if (!$this->applyFilterMethodsToDirectoryItem($folderNameFilterCallbacks, $folderName, $key, $folderIdentifier)) {
                        continue;
                    }
                    if ($folderName === $this->getProcessingFolder()) {
                        continue;
                    }

                    $folders[$key] = $key;
                }
            }
        }
        return $folders;
    }

    /**
     * Returns the number of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @return int Number of folders in folder
     */
    public function countFoldersInFolder($folderIdentifier, $recursive = false, array $folderNameFilterCallbacks = [])
    {
        return count($this->getFoldersInFolder($folderIdentifier, 0, 0, $recursive, $folderNameFilterCallbacks));
    }

    /**
     * Directly output the contents of the file to the output
     * buffer. Should not take care of header files or flushing
     * buffer before. Will be taken care of by the Storage.
     *
     * @param string $identifier
     * @return void
     */
    public function dumpFileContents($identifier)
    {
        $fileContents = $this->getFileContents($identifier);
        echo $fileContents;
        exit;
    }

    /**
     * Returns the permissions of a file/folder as an array
     * (keys r, w) of bool flags
     *
     * @param string $identifier
     * @return array
     */
    public function getPermissions($identifier)
    {
        return $this->getObjectPermissions($identifier);
    }

    /**
     * Merges the capabilites merged by the user at the storage
     * configuration into the actual capabilities of the driver
     * and returns the result.
     *
     * @param int $capabilities
     *
     * @return int
     */
    public function mergeConfigurationCapabilities($capabilities)
    {
        $this->capabilities &= $capabilities;
        return $this->capabilities;
    }

    /**
     * @return int
     */
    public function getStorageUid(): int
    {
        return $this->storageUid;
    }





    /*************************************************************
     ******************** Protected Helpers **********************
     *************************************************************/

    /**
     * initializeBaseUrl
     *
     * @return $this
     */
    protected function initializeBaseUrl()
    {
        $protocol = $this->configuration['protocol'];
        if ($protocol == 'auto') {
            $protocol = GeneralUtility::getIndpEnv('TYPO3_SSL') ? 'https://' : 'http://';
        }
        $baseUrl = $protocol;

        if (isset($this->configuration['publicBaseUrl']) && $this->configuration['publicBaseUrl'] !== '') {
            $baseUrl .= rtrim($this->configuration['publicBaseUrl'], '/');
        } else {
            $baseUrl .= $this->configuration['bucket'] . '.s3.amazonaws.com';
        }

        if (
            isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['initializeBaseUrl-postProcessing']) &&
            is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['initializeBaseUrl-postProcessing'])
        ) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['initializeBaseUrl-postProcessing'] as $funcName) {
                $params = ['baseUrl' => &$baseUrl];
                GeneralUtility::callUserFunction($funcName, $params, $this);
            }
        }

        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * initializeSettings
     *
     * @return $this
     */
    protected function initializeSettings()
    {
        if (self::$settings === null) {
            if (version_compare(VersionNumberUtility::getNumericTypo3Version(), '9.0.0') === -1) {
                // Backwards compatibility: for TYPO3 versions lower than 9.0
                $extConf = 'extConf'; // Trick the install tool extension checker to dismiss "deprecated" warning
                if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT'][$extConf][self::EXTENSION_KEY])) {
                    self::$settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT'][$extConf][self::EXTENSION_KEY]);
                }
            } else {
                self::$settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get(self::EXTENSION_KEY);
            }

            if (!isset(self::$settings['doNotLoadAmazonLib']) || !self::$settings['doNotLoadAmazonLib']) {
                self::loadExternalClasses();
            }
            if (TYPO3_MODE === 'FE' && (!isset(self::$settings['dnsPrefetch']) || self::$settings['dnsPrefetch'])) {
                $GLOBALS['TSFE']->additionalHeaderData['ausDriverAmazonS3_dnsPrefetch'] = '<link rel="dns-prefetch" href="' . $this->baseUrl . '">';
            }
        }
        return $this;
    }

    /**
     * initializeClient
     *
     * @return $this
     */
    protected function initializeClient()
    {
        $configuration = [
            'version' => '2006-03-01',
            'region' => (string)$this->configuration['region'],
            'credentials' => [
                'key' => (string)$this->configuration['key'],
                'secret' => (string)$this->configuration['secretKey'],
            ],
            'validation' => false,
        ];
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy'])) {
            $configuration['http']['proxy'] = $GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy'];
        }
        if (!empty($this->configuration['signature'])) {
            $configuration['signature_version'] = $this->configuration['signature_version'];
        }
        if (!empty($this->configuration['customHost'])) {
            $configuration['endpoint'] = $this->configuration['customHost'];
        }
        if (!empty($this->configuration['pathStyleEndpoint'])) {
            $configuration['use_path_style_endpoint'] = true;
        }

        if (
            isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['initializeClient-preProcessing']) &&
            is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['initializeClient-preProcessing'])
        ) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['initializeClient-preProcessing'] as $funcName) {
                $params = ['s3Client' => &$this->s3Client, 'configuration' => &$configuration];
                GeneralUtility::callUserFunction($funcName, $params, $this);
            }
        }

        if (!$this->s3Client) {
            if (empty($configuration['region'])) {
                $configuration['region'] = 'eu-central-1'; // region is required, set default region
            }
            $this->s3Client = new S3Client($configuration);
            StreamWrapper::register($this->s3Client, $this->streamWrapperProtocol);
        }
        return $this;
    }

    /**
     * Test the connection
     */
    protected function testConnection()
    {
        $messageQueue = $this->getMessageQueue();
        $localizationPrefix = 'LLL:' . $this->languageFile . ':driverConfiguration.message.';
        try {
            $this->getFilesInFolder(static::ROOT_FOLDER_IDENTIFIER);
            /** @var \TYPO3\CMS\Core\Messaging\FlashMessage $message */
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                LocalizationUtility::translate($localizationPrefix . 'connectionTestSuccessful.message', static::EXTENSION_NAME),
                LocalizationUtility::translate($localizationPrefix . 'connectionTestSuccessful.title', static::EXTENSION_NAME),
                FlashMessage::OK
            );
            $messageQueue->addMessage($message);
        } catch (\Exception $exception) {
            /** @var \TYPO3\CMS\Core\Messaging\FlashMessage $message */
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $exception->getMessage(),
                LocalizationUtility::translate($localizationPrefix . 'connectionTestFailed.title', static::EXTENSION_NAME),
                FlashMessage::WARNING
            );
            $messageQueue->addMessage($message);
        }
    }

    /**
     * @return \TYPO3\CMS\Core\Messaging\FlashMessageQueue
     */
    protected function getMessageQueue()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = $objectManager->get(FlashMessageService::class);
        return $flashMessageService->getMessageQueueByIdentifier();
    }

    /**
     * Checks if an object exists
     *
     * @param string $identifier
     * @return bool
     */
    protected function objectExists(string $identifier): bool
    {
        return $this->getMetaInfo($identifier) !== null;
    }

    /**
     * Checks if an prefix exists.
     * This is necessary because a folder is not an object for S3.
     *
     * @param string $identifier
     * @return bool
     */
    protected function prefixExists(string $identifier): bool
    {
        $objects = $this->getListObjects($identifier, ['MaxKeys' => 1]);
        if (is_array($objects['Contents']) && count($objects['Contents']) > 0) {
            return true;
        }

        //Do the HEAD call to work around MinIO speciality/bug:
        //- empty directories do not appear in ListObjectsV2 call
        //- empty directories appear in HEAD call
        //Since empty directories are the exception, we try the HEAD call last
        return $this->objectExists($identifier);
    }

    /**
     * Get the meta information of an file or folder
     *
     * @param string $identifier
     * @return array|null Returns an array with the meta info or "null"
     */
    protected function getMetaInfo($identifier)
    {
        $this->normalizeIdentifier($identifier);
        if (!isset($this->metaInfoCache[$identifier])) {
            try {
                $metadata = $this->s3Client->headObject([
                    'Bucket' => $this->configuration['bucket'],
                    'Key' => $identifier
                ])->toArray();
                $this->emitAfterMetadataRequestSignal($identifier, $metadata);
                $metaInfoDownloadAdapter = GeneralUtility::makeInstance(MetaInfoDownloadAdapter::class);
                $this->metaInfoCache[$identifier] = $metaInfoDownloadAdapter->getMetaInfoFromResponse($this, $identifier, $metadata);
            } catch (\Exception $exc) {
                // Ignore file not found errors
                if (!$exc->getPrevious() || $exc->getPrevious()->getCode() !== 404) {
                    /** @var \TYPO3\CMS\Core\Log\Logger $logger */
                    $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
                    $logger->log(LogLevel::WARNING, $exc->getMessage(), $exc->getTrace());
                }
                $this->metaInfoCache[$identifier] = null;
            }
        }
        return $this->metaInfoCache[$identifier];
    }

    /**
     * @param string $fileIdentifier
     * @param array $temporaryPath
     */
    protected function emitAfterMetadataRequestSignal(&$fileIdentifier, &$metadata)
    {
        [$fileIdentifier, $metadata] = $this->getSignalSlotDispatcher()->dispatch(
            self::class,
            'AfterMetadataRequest',
            [$fileIdentifier, $metadata]
        );
    }

    /**
     * @param string $function
     * @param array $parameter
     * @return array
     */
    protected function getCachedResponse($function, $parameter)
    {
        $cacheIdentifier = md5(serialize($parameter));
        if (!isset($this->requestCache[$function][$cacheIdentifier])) {
            $this->requestCache[$function][$cacheIdentifier] = $this->s3Client->$function($parameter)->toArray();
        }
        return $this->requestCache[$function][$cacheIdentifier];
    }

    /**
     * Remove the identifier from the first level cache
     *
     * @param $identifier
     * @return void
     */
    protected function flushMetaInfoCache($identifier)
    {
        $this->normalizeIdentifier($identifier);
        unset($this->metaInfoCache[$identifier]);
    }

    /**
     * @param string $identifier
     * @return mixed
     */
    protected function getObjectPermissions($identifier)
    {
        $this->normalizeIdentifier($identifier);
        if ($identifier === '') {
            $identifier = self::ROOT_FOLDER_IDENTIFIER;
        }
        if ($identifier === ResourceStorageInterface::DEFAULT_ProcessingFolder) {
            $identifier = rtrim($identifier, '/') . '/';
        }
        if (!isset($this->objectPermissionsCache[$identifier])) {
            if (!isset(self::$settings['enablePermissionsCheck']) || empty(self::$settings['enablePermissionsCheck'])) {
                $permissions = ['r' => true, 'w' => true];
            } elseif ($identifier === self::ROOT_FOLDER_IDENTIFIER) {
                $permissions = ['r' => true, 'w' => true];
            } else {
                $permissions = ['r' => false, 'w' => false];

                try {
                    $response = $this->s3Client->getObjectAcl([
                        'Bucket' => $this->configuration['bucket'],
                        'Key' => $identifier
                    ])->toArray();

                    // Until the SDK provides any useful information about folder permissions, we take full access for granted as long as one user with full access exists.
                    foreach ($response['Grants'] as $grant) {
                        if ($grant['Permission'] === 'FULL_CONTROL') {
                            $permissions['r'] = true;
                            $permissions['w'] = true;
                            break;
                        }
                    }
                } catch (\Exception $exception) {
                    // Show warning in backend list module
                    if (TYPO3_MODE === 'BE' && $_GET['M'] === 'file_FilelistList') {
                        $messageQueue = $this->getMessageQueue();
                        /** @var \TYPO3\CMS\Core\Messaging\FlashMessage $message */
                        $message = GeneralUtility::makeInstance(
                            FlashMessage::class,
                            $exception->getMessage(),
                            '',
                            FlashMessage::WARNING
                        );
                        $messageQueue->addMessage($message);
                    }
                }
            }
            $this->objectPermissionsCache[$identifier] = $permissions;
        }

        return $this->objectPermissionsCache[$identifier];
    }

    /**
     * @param string $identifier
     * @return bool
     */
    protected function deleteObject($identifier)
    {
        $this->s3Client->deleteObject(['Bucket' => $this->configuration['bucket'], 'Key' => $identifier]);
        $this->flushMetaInfoCache($identifier);
        return !$this->s3Client->doesObjectExist($this->configuration['bucket'], $identifier);
    }

    /**
     * Returns a folder by its identifier.
     *
     * @param $identifier
     * @return Folder|string
     */
    protected function getFolder($identifier)
    {
        if ($identifier === self::ROOT_FOLDER_IDENTIFIER) {
            return $this->getRootLevelFolder();
        }
        $this->normalizeIdentifier($identifier);
        return new Folder($this->storage, $identifier, basename(rtrim($identifier, '/')));
    }

    /**
     * @param string $identifier
     * @param string $body
     * @param array $overrideArgs
     */
    protected function createObject($identifier, $body = '', $overrideArgs = [])
    {
        $this->normalizeIdentifier($identifier);
        $args = [
            'Bucket' => $this->configuration['bucket'],
            'Key' => $identifier,
            'Body' => $body
        ];
        $this->s3Client->putObject(array_merge_recursive($args, $overrideArgs));
        $this->flushMetaInfoCache($identifier);
    }

    /**
     * Renames an object using the StreamWrapper
     *
     * @param string $identifier
     * @param string $newIdentifier
     * @return void
     */
    protected function renameObject($identifier, $newIdentifier)
    {
        rename($this->getStreamWrapperPath($identifier), $this->getStreamWrapperPath($newIdentifier));
        $this->identifierMap[$identifier] = $newIdentifier;
        $this->flushMetaInfoCache($identifier);
        $this->flushMetaInfoCache($newIdentifier);
    }

    /**
     * Returns a string where any character not matching [.a-zA-Z0-9_-] is
     * substituted by '_'
     * Trailing dots are removed
     * @param string $fileName Input string, typically the body of a fileName
     * @param string $charset Charset of the a fileName (defaults to current charset; depending on context)
     * @return string Output string with any characters not matching [.a-zA-Z0-9_-] is substituted by '_' and trailing dots removed
     * @throws Exception\InvalidFileNameException
     */
    public function sanitizeFileName($fileName, $charset = '')
    {
        $fileName = $this->getCharsetConversion()->specCharsToASCII('utf-8', $fileName);
        // Replace unwanted characters by underscores
        $cleanFileName = preg_replace(
            '/[' . self::UNSAFE_FILENAME_CHARACTER_EXPRESSION . '\\xC0-\\xFF]/',
            '_',
            trim($fileName)
        );

        // Strip trailing dots and return
        $cleanFileName = rtrim($cleanFileName, '.');
        if ($cleanFileName === '') {
            throw new Exception\InvalidFileNameException(
                'File name ' . $fileName . ' is invalid.',
                1320288991
            );
        }
        return $cleanFileName;
    }

    /**
     * Gets the charset conversion object.
     *
     * @return \TYPO3\CMS\Core\Charset\CharsetConverter
     */
    protected function getCharsetConversion()
    {
        if (!isset($this->charsetConversion)) {
            $this->charsetConversion = GeneralUtility::makeInstance(CharsetConverter::class);
        }
        return $this->charsetConversion;
    }

    /**
     * Returns the StreamWrapper path of a file or folder.
     *
     * @param FileInterface|Folder|string $file
     * @return string
     * @throws \RuntimeException
     */
    protected function getStreamWrapperPath($file)
    {
        $basePath = $this->streamWrapperProtocol . '://' . $this->configuration['bucket'] . '/';
        if ($file instanceof FileInterface) {
            $identifier = $file->getIdentifier();
        } elseif ($file instanceof Folder) {
            $identifier = $file->getIdentifier();
        } elseif (is_string($file)) {
            $identifier = $file;
        } else {
            throw new \RuntimeException('Type "' . gettype($file) . '" is not supported.', 1325191178);
        }
        $this->normalizeIdentifier($identifier);
        return $basePath . $identifier;
    }

    /**
     * @param string &$identifier
     */
    protected function normalizeIdentifier(&$identifier)
    {
        $identifier = str_replace('//', '/', $identifier);
        if ($identifier !== '/') {
            $identifier = ltrim($identifier, '/');
        }
    }

    /**
     * @return void
     */
    protected function resetIdentifierMap()
    {
        $this->identifierMap = [];
    }

    /**
     * Returns all sub objects for the parent object given by identifier, excluding the parent object itself.
     * If the $recursive flag is disabled, only objects on the exact next level are returned.
     *
     * @param string $identifier
     * @param bool $recursive
     * @param string $filter
     * @return array
     */
    protected function getSubObjects($identifier, $recursive = true, $filter = self::FILTER_ALL)
    {
        $result = $this->getListObjects($identifier);
        if (!is_array($result['Contents'])) {
            return [];
        }
        return array_filter($result['Contents'], function (&$object) use ($identifier, $recursive, $filter) {
            return (
                $object['Key'] !== $identifier &&
                (
                    $recursive ||
                    substr_count(trim(str_replace($identifier, '', $object['Key']), '/'), '/') === 0
                ) && (
                    $filter === self::FILTER_ALL ||
                    $filter === self::FILTER_FOLDERS && $this->isDir($object['Key']) ||
                    $filter === self::FILTER_FILES && !$this->isDir($object['Key'])
                )
            );
        });
    }

    /**
     * Recursive function to get all objects of a folder
     * It is recursive because AWS S3 lists max 1000 objects by one request
     *
     * @param string $identifier
     * @param array $overrideArgs
     * @return array
     */
    protected function getListObjects($identifier, $overrideArgs = [])
    {
        $args = [
            'Bucket' => $this->configuration['bucket'],
            'Prefix' => $identifier,
        ];
        $result = $this->getCachedResponse('listObjectsV2', array_merge_recursive($args, $overrideArgs));

        // Cache the given meta info
        $metaInfoDownloadAdapter = GeneralUtility::makeInstance(MetaInfoDownloadAdapter::class);
        if (is_array($result['Contents'])) {
            foreach ($result['Contents'] as $content) {
                $fileIdentifier = $identifier . $content['Key'];
                $this->normalizeIdentifier($fileIdentifier);
                if (!isset($this->metaInfoCache[$fileIdentifier])) {
                    $this->metaInfoCache[$fileIdentifier] = $metaInfoDownloadAdapter->getMetaInfoFromResponse($this, $fileIdentifier, $content);
                }
            }
        }

        if (isset($overrideArgs['MaxKeys']) && $overrideArgs['MaxKeys'] <= 1000) {
            return $result;
        }

        // Amazon S3 lists max 1000 files, so we have to get all recursive
        if ($result['IsTruncated']) {
            $overrideArgs['ContinuationToken'] = $result['NextContinuationToken'];
            $moreResults = $this->getListObjects($identifier, $overrideArgs);
            if (is_array($moreResults['Contents'])) {
                if (!is_array($result['Contents'])) {
                    $result['Contents'] = $moreResults['Contents'];
                } else {
                    $result['Contents'] = array_merge($result['Contents'], $moreResults['Contents']);
                }
            }
            if (is_array($moreResults['CommonPrefixes'])) {
                if (!is_array($result['CommonPrefixes'])) {
                    $result['CommonPrefixes'] = $moreResults['CommonPrefixes'];
                } else {
                    $result['CommonPrefixes'] = array_merge($result['CommonPrefixes'], $moreResults['CommonPrefixes']);
                }
            }
        }
        return $result;
    }

    /**
     * Renames a given subfolder by renaming all its sub objects and the folder itself.
     * Used for renaming child objects of a renamed a parent object.
     *
     * @param Folder $folder
     * @param string $newDirName The new directory name the folder will reside in
     * @return void
     */
    protected function renameSubFolder(Folder $folder, $newDirName)
    {
        foreach ($this->getSubObjects($folder->getIdentifier(), false) as $subObject) {
            $subObjectIdentifier = $subObject['Key'];
            if ($this->isDir($subObjectIdentifier)) {
                $subFolder = $this->getFolder($subObjectIdentifier);
                $this->renameSubFolder($subFolder, $newDirName . $folder->getName() . '/');
            } else {
                $newSubObjectIdentifier = $newDirName . $folder->getName() . '/' . basename($subObjectIdentifier);
                $this->renameObject($subObjectIdentifier, $newSubObjectIdentifier);
            }
        }

        $newIdentifier = $newDirName . $folder->getName() . '/';
        $this->renameObject($folder->getIdentifier(), $newIdentifier);
    }

    /**
     * @param string $identifier
     * @param string $targetIdentifier
     */
    protected function copyObject($identifier, $targetIdentifier)
    {
        $this->s3Client->copyObject([
            'Bucket' => $this->configuration['bucket'],
            'CopySource' => $this->configuration['bucket'] . '/' . $identifier,
            'Key' => $targetIdentifier,
            'CacheControl' => $this->getCacheControl($targetIdentifier)
        ]);
        $this->flushMetaInfoCache($targetIdentifier);
    }

    /**
     * @param array $objects S3 Objects as arrays with at least the Key field set
     * @return void
     */
    protected function sortObjectsForNestedFolderOperations(array &$objects)
    {
        usort($objects, function ($object1, $object2) {
            if (substr($object1['Key'], -1) === '/') {
                if (substr($object2['Key'], -1) === '/') {
                    $numSlashes1 = substr_count($object1['Key'], '/');
                    $numSlashes2 = substr_count($object2['Key'], '/');
                    return $numSlashes1 < $numSlashes2 ? -1 : ($numSlashes1 === $numSlashes2 ? 0 : 1);
                } else {
                    return -1;
                }
            } else {
                if (substr($object2['Key'], -1) === '/') {
                    return 1;
                } else {
                    $numSlashes1 = substr_count($object1['Key'], '/');
                    $numSlashes2 = substr_count($object2['Key'], '/');
                    return $numSlashes1 < $numSlashes2 ? -1 : ($numSlashes1 === $numSlashes2 ? 0 : 1);
                }
            }
        });
    }

    /**
     * @param string $pathAndFilename
     * @return string
     */
    protected function getCacheControl($pathAndFilename)
    {
        $cacheControl = $this->configuration['cacheHeaderDuration'] ? 'max-age=' . $this->configuration['cacheHeaderDuration'] : '';
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['getCacheControl'])) {
            $fileExtension = pathinfo($pathAndFilename, PATHINFO_EXTENSION);
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['getCacheControl'] as $funcName) {
                $params = [
                    'cacheControl' => &$cacheControl,
                    'pathAndFilename' => $pathAndFilename,
                    'fileExtension' => $fileExtension,
                    'configuration' => $this->configuration
                ];
                GeneralUtility::callUserFunction($funcName, $params, $this);
            }
        }
        return $cacheControl;
    }

    /**
     * @return ResourceStorage
     */
    protected function getStorage()
    {
        if (!$this->storage) {
            /** @var $storageRepository \TYPO3\CMS\Core\Resource\StorageRepository */
            $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
            $this->storage = $storageRepository->findByUid($this->storageUid);
        }
        return $this->storage;
    }

    /**
     * @return string
     */
    protected function getProcessingFolder()
    {
        if (!$this->processingFolder) {
            $confProcessingFolder = $this->getStorage()->getProcessingFolder()->getName();
            $this->processingFolder = $confProcessingFolder ? $confProcessingFolder : $this->processingFolderDefault;
        }
        return $this->processingFolder;
    }

    /**
     * Returns whether the object defined by its identifier is a folder
     *
     * @param string $identifier
     * @return bool
     */
    protected function isDir($identifier)
    {
        return substr($identifier, -1) === '/';
    }

    /**
     * Applies a set of filter methods to a file name to find out if it should be used or not. This is e.g. used by
     * directory listings.
     *
     * @param array $filterMethods The filter methods to use
     * @param string $itemName
     * @param string $itemIdentifier
     * @param string $parentIdentifier
     * @throws \RuntimeException
     * @return bool
     */
    protected function applyFilterMethodsToDirectoryItem(array $filterMethods, $itemName, $itemIdentifier, $parentIdentifier)
    {
        foreach ($filterMethods as $filter) {
            if (is_array($filter)) {
                $result = call_user_func($filter, $itemName, $itemIdentifier, $parentIdentifier, [], $this);
                // We have to use -1 as the „don't include“ return value, as call_user_func() will return FALSE
                // If calling the method succeeded and thus we can't use that as a return value.
                if ($result === -1) {
                    return false;
                } elseif ($result === false) {
                    throw new \RuntimeException('Could not apply file/folder name filter ' . $filter[0] . '::' . $filter[1]);
                }
            }
        }
        return true;
    }

    /**
     * Get the SignalSlot dispatcher
     *
     * @return Dispatcher
     */
    protected function getSignalSlotDispatcher()
    {
        if (!isset($this->signalSlotDispatcher)) {
            $this->signalSlotDispatcher = GeneralUtility::makeInstance(ObjectManager::class)->get(Dispatcher::class);
        }
        return $this->signalSlotDispatcher;
    }
}
