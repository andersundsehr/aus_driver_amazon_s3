<?php

namespace AUS\AusDriverAmazonS3\Tests\Functional\Driver;

use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use ReflectionClass;
use Aws\S3\Exception\S3Exception;
use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;

class AmazonS3DriverTest extends FunctionalTestCase
{
    use ProphecyTrait;

    protected bool $initializeDatabase = false;

    /**
     * @var AmazonS3Driver
     */
    protected $driver;

    /**
     * @var string[]
     */
    protected $testConfiguration = [
        'bucket'              => 'test-bucket',
        'region'              => 'eu-central-1',
        'customHost'          => 'http://minio:9000',
        'pathStyleEndpoint'   => 1,
        'key'                 => 'test-key',
        'secretKey'           => 'test-secretkey',
        'publicBaseUrl'       => 'minio',
        'baseFolder'          => '',
        'cacheHeaderDuration' => 0,
        'protocol'            => 'http://',
        'signature'           => 0,
        'caseSensitive'       => 1,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][AmazonS3Driver::EXTENSION_KEY] = [
            'dnsPrefetch' => '1',
            'enablePermissionsCheck' => '0',
        ];

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cacheManager->setCacheConfigurations([
            'ausdriveramazons3_metainfocache' => [
                'backend' => TransientMemoryBackend::class,
                'frontend' => VariableFrontend::class,
            ],
            'ausdriveramazons3_requestcache' => [
                'backend' => TransientMemoryBackend::class,
                'frontend' => VariableFrontend::class,
            ]
        ]);

        $rc = new ReflectionClass(AmazonS3Driver::class);
        $rc->setStaticPropertyValue('settings', null);

        $this->driver = new AmazonS3Driver(
            $this->testConfiguration,
            null,
            GeneralUtility::makeInstance(NoopEventDispatcher::class)
        );
        $this->driver->setStorageUid(42);
        $this->driver->initialize();
    }

    public function testDeleteFile(): void
    {
        $localPath = __DIR__ . '/tmp.txt';
        file_put_contents($localPath, '42');
        $this->assertTrue(file_exists($localPath));
        $this->assertFalse($this->driver->fileExists('tmp-uploaded.txt'));

        $this->assertEquals(
            'tmp-uploaded.txt',
            $this->driver->addFile(
                $localPath,
                $this->driver->getRootLevelFolder(),
                'tmp-uploaded.txt'
            )
        );
        $this->assertFalse(file_exists($localPath));

        $this->assertTrue($this->driver->deleteFile('tmp-uploaded.txt'));
        $this->assertFalse($this->driver->fileExists('tmp-uploaded.txt'));
    }

    public function testDeleteFolder(): void
    {
        $this->assertFalse($this->driver->folderExists('tmp-dir'));

        $this->assertEquals('/tmp-dir/', $this->driver->createFolder('tmp-dir'));
        $this->assertTrue($this->driver->folderExists('tmp-dir'));

        $this->assertTrue($this->driver->deleteFolder('tmp-dir/'));
        $this->assertFalse($this->driver->folderExists('tmp-dir'));
    }

    public function testFileExists(): void
    {
        $this->assertFalse($this->driver->fileExists('doesnotexist.txt'));
        $this->assertTrue($this->driver->fileExists('23.txt'));
        $this->assertTrue($this->driver->fileExists('images/bytes-1009.png'));
    }

    public function testFolderExists(): void
    {
        $this->assertTrue(
            $this->driver->folderExists($this->driver->getDefaultFolder())
        );

        $this->assertFalse($this->driver->folderExists('doesnotexist'));
        $this->assertFalse($this->driver->folderExists('doesnotexist/'));

        $this->assertTrue($this->driver->folderExists('images'));
        $this->assertTrue($this->driver->folderExists('images/'));
    }

    public function testFolderExistsInFolder(): void
    {
        $this->assertTrue($this->driver->folderExistsInFolder('subfolder11', 'folder1'));

        $this->assertTrue(
            $this->driver->folderExistsInFolder(
                'images',
                $this->driver->getDefaultFolder()
            )
        );
    }

    public function testGetFileContents(): void
    {
        $this->assertEquals("42\n", $this->driver->getFileContents('23.txt'));
    }

    public function testGetPublicUrl(): void
    {
        $this->assertEquals(
            'http://minio/file.txt',
            $this->driver->getPublicUrl('file.txt')
        );
    }

    public function testPublicCapabilitySwitchesBetweenDirectAndTypo3PassthroughUrl(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute('applicationType')->willReturn(SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $GLOBALS['TYPO3_REQUEST'] = $request->reveal();
        $previousAutoTagging = $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['frontend.cache.autoTagging'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['frontend.cache.autoTagging'] = false;

        $storageRecord = [
            'uid' => 42,
            'name' => 'Amazon S3',
            'configuration' => $this->testConfiguration,
            'is_browsable' => 1,
            'is_public' => 1,
            'is_writable' => 1,
            'is_online' => 1,
        ];
        $eventDispatcher = GeneralUtility::makeInstance(NoopEventDispatcher::class);

        $publicStorage = new ResourceStorage($this->driver, $storageRecord, $eventDispatcher);
        $publicFile = new File(
            [
                'uid' => 1,
                'identifier' => '23.txt',
                'name' => '23.txt',
                'missing' => 0,
            ],
            $publicStorage
        );

        $this->assertSame('http://minio/23.txt', $publicFile->getPublicUrl());

        $storageRecord['is_public'] = 0;
        $privateDriver = new AmazonS3Driver(
            $this->testConfiguration,
            null,
            $eventDispatcher
        );
        $privateDriver->setStorageUid(42);

        $privateStorage = new ResourceStorage($privateDriver, $storageRecord, $eventDispatcher);
        $privateFile = new File(
            [
                'uid' => 1,
                'identifier' => '23.txt',
                'name' => '23.txt',
                'missing' => 0,
            ],
            $privateStorage
        );

        $privateUrl = (string)$privateFile->getPublicUrl();
        $query = parse_url($privateUrl, PHP_URL_QUERY);
        parse_str((string)$query, $queryParameters);

        $this->assertStringContainsString('eID=dumpFile', $privateUrl);
        $this->assertSame('f', $queryParameters['t']);
        $this->assertSame('1', (string)$queryParameters['f']);
        $this->assertArrayHasKey('token', $queryParameters);

        if ($previousAutoTagging === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['frontend.cache.autoTagging']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['frontend.cache.autoTagging'] = $previousAutoTagging;
        }
    }

    public function testGetFilesInFolderRoot(): void
    {
        $this->assertEquals(
            [
                '23.txt' => '23.txt',
            ],
            $this->driver->getFilesInFolder($this->driver->getDefaultFolder())
        );
    }

    public function testGetFilesInFolder(): void
    {
        $this->assertEquals(
            [
                'images/bytes-1009.png' => 'images/bytes-1009.png',
            ],
            $this->driver->getFilesInFolder('images/')
        );
    }

    public function testGetFileInfoByIdentifierAllProperties(): void
    {
        $info = $this->driver->getFileInfoByIdentifier('images/bytes-1009.png');
        $this->assertEquals('bytes-1009.png', $info['name']);
        $this->assertEquals('images/bytes-1009.png', $info['identifier']);
        $this->assertEquals('8b6249ec878b12d3f014e616336fefaac0b4d0dd', $info['identifier_hash']);
        $this->assertEquals('c1a406ab82b5588738d1587da2761746ec584a6c', $info['folder_hash']);
        $this->assertEquals('png', $info['extension']);
        $this->assertEquals(42, $info['storage']);
        $this->assertEquals('image/png', $info['mimetype']);
        $this->assertEquals(1009, $info['size']);
    }

    public function testGetFileInfoByIdentifierOnlyMimetype(): void
    {
        $info = $this->driver->getFileInfoByIdentifier('images/bytes-1009.png', ['mimetype']);
        $this->assertEquals('image/png', $info['mimetype']);
    }

    public function testGetFoldersInFolderRoot(): void
    {
        $this->markTestSkipped('need sys_file_storage for this');
        $this->assertEquals(
            [],
            $this->driver->getFoldersInFolder($this->driver->getRootLevelFolder())
        );
    }

    public function testIsFolderEmptySlash(): void
    {
        $this->assertCount(1, $this->driver->getFilesInFolder('images/'));
        $this->assertFalse($this->driver->isFolderEmpty('images/'));
    }

    public function testIsFolderEmptyNoSlash(): void
    {
        $this->assertCount(1, $this->driver->getFilesInFolder('images'));
        $this->assertFalse($this->driver->isFolderEmpty('images'));
    }

    public function testCopyFileWithinStorage(): void
    {
        $this->assertFalse($this->driver->fileExists('copytarget.txt'));
        $this->assertEquals(
            'copytarget.txt',
            $this->driver->copyFileWithinStorage('23.txt', '', 'copytarget.txt')
        );
        $this->assertTrue($this->driver->fileExists('copytarget.txt'));

        $this->assertTrue($this->driver->deleteFile('copytarget.txt'));
    }

    public function testMoveFileWithinStorageRoot(): void
    {
        $this->assertTrue($this->driver->fileExists('23.txt'));
        $this->assertFalse($this->driver->fileExists('movetarget.txt'));
        $this->assertEquals(
            'movetarget.txt',
            $this->driver->moveFileWithinStorage('23.txt', '', 'movetarget.txt')
        );
        $this->assertTrue($this->driver->fileExists('movetarget.txt'));
        $this->assertFalse($this->driver->fileExists('23.txt'));

        $this->assertEquals(
            '23.txt',
            $this->driver->moveFileWithinStorage('movetarget.txt', '', '23.txt')
        );
    }

    public function testMoveFileWithinStorageSubfolder(): void
    {
        $this->assertTrue($this->driver->fileExists('23.txt'));
        $this->assertFalse($this->driver->fileExists('images/movetarget.txt'));
        $this->assertEquals(
            'images/movetarget.txt',
            $this->driver->moveFileWithinStorage('23.txt', 'images/', 'movetarget.txt')
        );
        $this->assertTrue($this->driver->fileExists('images/movetarget.txt'));
        $this->assertFalse($this->driver->fileExists('23.txt'));

        $this->assertEquals(
            '23.txt',
            $this->driver->moveFileWithinStorage('images/movetarget.txt', '', '23.txt')
        );
    }

    public function testMoveFileWithinStorageSubfolderNoSlash(): void
    {
        $this->assertTrue($this->driver->fileExists('23.txt'));
        $this->assertFalse($this->driver->fileExists('images/movetarget.txt'));
        $this->assertEquals(
            'images/movetarget.txt',
            $this->driver->moveFileWithinStorage('23.txt', 'images', 'movetarget.txt')
        );
        $this->assertTrue($this->driver->fileExists('images/movetarget.txt'));
        $this->assertFalse($this->driver->fileExists('23.txt'));

        $this->assertEquals(
            '23.txt',
            $this->driver->moveFileWithinStorage('images/movetarget.txt', '', '23.txt')
        );
    }

    public function testSetFileContents(): void
    {
        $this->assertEquals(5, $this->driver->setFileContents('write.txt', 'write'));
        $this->assertEquals('write', $this->driver->getFileContents('write.txt'));
        $this->assertTrue($this->driver->deleteFile('write.txt'));
    }

    public function testEnvStorageConfigurationGeneric(): void
    {
        $rc = new ReflectionClass(AmazonS3Driver::class);
        $rc->setStaticPropertyValue('settings', null);

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][AmazonS3Driver::EXTENSION_KEY]['storage'] = [
            //define invalid host
            'customHost' => 'http://minio:9001',
        ];
        $this->driver = new AmazonS3Driver(
            $this->testConfiguration,
            null,
            GeneralUtility::makeInstance(NoopEventDispatcher::class)
        );
        $this->driver->setStorageUid(42);
        $this->driver->initialize();

        $this->expectException(S3Exception::class);
        $this->expectExceptionMessageMatches('/Failed to connect to minio(?::9001| port 9001)/');
        $this->driver->getFileContents('23.txt');
    }

    public function testEnvStorageConfigurationUid(): void
    {
        $rc = new ReflectionClass(AmazonS3Driver::class);
        $rc->setStaticPropertyValue('settings', null);

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][AmazonS3Driver::EXTENSION_KEY]['storage'] = [
            //define invalid host
            'customHost' => 'http://minio:9001',
        ];
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][AmazonS3Driver::EXTENSION_KEY]['storage_42'] = [
            //define valid host specific for storage ID 42 that overrides the broken generic one
            'customHost' => 'http://minio:9000',
        ];

        $this->driver = new AmazonS3Driver(
            $this->testConfiguration,
            null,
            GeneralUtility::makeInstance(NoopEventDispatcher::class)
        );
        $this->driver->setStorageUid(42);
        $this->driver->initialize();

        $this->driver->getFileContents('23.txt');
    }
}
