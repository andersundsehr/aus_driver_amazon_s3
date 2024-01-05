<?php

namespace AUS\AusDriverAmazonS3\Tests\Functional\Driver;

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class AmazonS3DriverTest extends FunctionalTestCase
{
    use ProphecyTrait;

    protected bool $initializeDatabase = false;

    /**
     * @var AmazonS3Driver
     */
    protected $driver = null;

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

    public function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][AmazonS3Driver::EXTENSION_KEY] = [
            'dnsPrefetch' => '1',
            'doNotLoadAmazonLib' => '0',
            'enablePermissionsCheck' => '0',
        ];

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cacheManager->setCacheConfigurations([
            'ausdriveramazons3_metainfocache' => [
                'backend' => \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class,
                'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
            ],
            'ausdriveramazons3_requestcache' => [
                'backend' => \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class,
                'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
            ]
        ]);

        $rc = new \ReflectionClass(AmazonS3Driver::class);
        $rc->setStaticPropertyValue('settings', null);

        $this->driver = new AmazonS3Driver(
            $this->testConfiguration,
            null,
            GeneralUtility::makeInstance(NoopEventDispatcher::class)
        );
        $this->driver->setStorageUid(42);
        $this->driver->initialize();
    }

    public function testDeleteFile()
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

    public function testDeleteFolder()
    {
        $this->assertFalse($this->driver->folderExists('tmp-dir'));

        $this->assertEquals('/tmp-dir/', $this->driver->createFolder('tmp-dir'));
        $this->assertTrue($this->driver->folderExists('tmp-dir'));

        $this->assertTrue($this->driver->deleteFolder('tmp-dir/'));
        $this->assertFalse($this->driver->folderExists('tmp-dir'));
    }

    public function testFileExists()
    {
        $this->assertFalse($this->driver->fileExists('doesnotexist.txt'));
        $this->assertTrue($this->driver->fileExists('23.txt'));
        $this->assertTrue($this->driver->fileExists('images/bytes-1009.png'));
    }

    public function testFolderExists()
    {
        $this->assertTrue(
            $this->driver->folderExists($this->driver->getDefaultFolder())
        );

        $this->assertFalse($this->driver->folderExists('doesnotexist'));
        $this->assertFalse($this->driver->folderExists('doesnotexist/'));

        $this->assertTrue($this->driver->folderExists('images'));
        $this->assertTrue($this->driver->folderExists('images/'));
    }

    public function testFolderExistsInFolder()
    {
        $this->assertTrue($this->driver->folderExistsInFolder('subfolder11', 'folder1'));

        $this->assertTrue(
            $this->driver->folderExistsInFolder(
                'images',
                $this->driver->getDefaultFolder()
            )
        );
    }

    public function testGetFileContents()
    {
        $this->assertEquals("42\n", $this->driver->getFileContents('23.txt'));
    }

    public function testGetPublicUrl()
    {
        $this->assertEquals(
            'http://minio/file.txt',
            $this->driver->getPublicUrl('file.txt')
        );
    }

    public function testGetFilesInFolderRoot()
    {
        $this->assertEquals(
            [
                '23.txt' => '23.txt',
            ],
            $this->driver->getFilesInFolder($this->driver->getDefaultFolder())
        );
    }

    public function testGetFilesInFolder()
    {
        $this->assertEquals(
            [
                'images/bytes-1009.png' => 'images/bytes-1009.png',
            ],
            $this->driver->getFilesInFolder('images/')
        );
    }

    public function testGetFileInfoByIdentifierAllProperties()
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

    public function testGetFileInfoByIdentifierOnlyMimetype()
    {
        $info = $this->driver->getFileInfoByIdentifier('images/bytes-1009.png', ['mimetype']);
        $this->assertEquals('image/png', $info['mimetype']);
    }

    public function testGetFoldersInFolderRoot()
    {
        $this->markTestSkipped('need sys_file_storage for this');
        $this->assertEquals(
            [],
            $this->driver->getFoldersInFolder($this->driver->getRootLevelFolder())
        );
    }

    public function testIsFolderEmptySlash()
    {
        $this->assertCount(1, $this->driver->getFilesInFolder('images/'));
        $this->assertFalse($this->driver->isFolderEmpty('images/'));
    }

    public function testIsFolderEmptyNoSlash()
    {
        $this->assertCount(1, $this->driver->getFilesInFolder('images'));
        $this->assertFalse($this->driver->isFolderEmpty('images'));
    }

    public function testCopyFileWithinStorage()
    {
        $this->assertFalse($this->driver->fileExists('copytarget.txt'));
        $this->assertEquals(
            'copytarget.txt',
            $this->driver->copyFileWithinStorage('23.txt', '', 'copytarget.txt')
        );
        $this->assertTrue($this->driver->fileExists('copytarget.txt'));

        $this->assertTrue($this->driver->deleteFile('copytarget.txt'));
    }

    public function testMoveFileWithinStorageRoot()
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

    public function testMoveFileWithinStorageSubfolder()
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

    public function testMoveFileWithinStorageSubfolderNoSlash()
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

    public function testSetFileContents()
    {
        $this->assertEquals(5, $this->driver->setFileContents('write.txt', 'write'));
        $this->assertEquals('write', $this->driver->getFileContents('write.txt'));
        $this->assertTrue($this->driver->deleteFile('write.txt'));
    }

    public function testEnvStorageConfigurationGeneric()
    {
        $rc = new \ReflectionClass(AmazonS3Driver::class);
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

        $this->expectException(\Aws\S3\Exception\S3Exception::class);
        $this->expectExceptionMessageMatches('/.*Failed to connect to minio port 9001.*/');
        $this->driver->getFileContents('23.txt');
    }

    public function testEnvStorageConfigurationUid()
    {
        $rc = new \ReflectionClass(AmazonS3Driver::class);
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
