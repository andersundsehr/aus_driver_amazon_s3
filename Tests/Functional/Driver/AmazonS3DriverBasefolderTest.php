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

class AmazonS3DriverBasefolderTest extends FunctionalTestCase
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
        'baseFolder'          => 'folder1',
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

        $this->assertEquals(
            'http://minio/folder1/tmp-uploaded.txt',
            $this->driver->getPublicUrl('tmp-uploaded.txt')
        );
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
        $this->assertTrue($this->driver->fileExists('1.txt'));
        $this->assertTrue($this->driver->fileExists('subfolder11/11.txt'));
    }

    public function testFolderExists()
    {
        $this->assertTrue(
            $this->driver->folderExists($this->driver->getDefaultFolder())
        );

        $this->assertFalse($this->driver->folderExists('doesnotexist'));

        $this->assertTrue($this->driver->folderExists('subfolder11'));
    }

    public function testFolderExistsInFolder()
    {
        $this->assertTrue($this->driver->folderExistsInFolder('subsubfolder111', 'subfolder11'));

        $this->assertTrue(
            $this->driver->folderExistsInFolder(
                'subfolder12',
                $this->driver->getDefaultFolder()
            )
        );
    }

    public function testGetFileContents()
    {
        $this->assertEquals("1\n", $this->driver->getFileContents('1.txt'));
    }

    public function testGetPublicUrl()
    {
        $this->assertEquals(
            'http://minio/folder1/file.txt',
            $this->driver->getPublicUrl('file.txt')
        );
    }

    public function testGetFilesInFolderRoot()
    {
        $this->assertEquals(
            [
                '1.txt' => '1.txt',
                '2.txt' => '2.txt',
            ],
            $this->driver->getFilesInFolder($this->driver->getDefaultFolder())
        );
    }

    public function testGetFilesInFolder()
    {
        $this->assertEquals(
            [
                'subfolder11/11.txt' => 'subfolder11/11.txt',
                'subfolder11/11-bytes-1009.png' => 'subfolder11/11-bytes-1009.png',
            ],
            $this->driver->getFilesInFolder('subfolder11/')
        );
    }

    public function testGetFileInfoByIdentifierAllProperties()
    {
        $info = $this->driver->getFileInfoByIdentifier('subfolder11/11-bytes-1009.png');
        $this->assertEquals('11-bytes-1009.png', $info['name']);
        $this->assertEquals('subfolder11/11-bytes-1009.png', $info['identifier']);
        $this->assertEquals('9c6a399efd8382ab1cea63ec089ff6d35765c5bb', $info['identifier_hash']);
        $this->assertEquals('ed0dfe4cb67446995f92bc3564495fc9a34e97e0', $info['folder_hash']);
        $this->assertEquals('png', $info['extension']);
        $this->assertEquals(42, $info['storage']);
        $this->assertEquals('image/png', $info['mimetype']);
        $this->assertEquals(1009, $info['size']);
    }

    public function testGetFileInfoByIdentifierOnlyMimetype()
    {
        $info = $this->driver->getFileInfoByIdentifier('subfolder11/11-bytes-1009.png', ['mimetype']);
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

    public function testCopyFileWithinStorage()
    {
        $this->assertFalse($this->driver->fileExists('copytarget.txt'));
        $this->assertEquals(
            'copytarget.txt',
            $this->driver->copyFileWithinStorage('1.txt', '', 'copytarget.txt')
        );
        $this->assertTrue($this->driver->fileExists('copytarget.txt'));

        $this->assertTrue($this->driver->deleteFile('copytarget.txt'));
    }

    public function testMoveFileWithinStorageRoot()
    {
        $this->assertTrue($this->driver->fileExists('1.txt'));
        $this->assertFalse($this->driver->fileExists('movetarget.txt'));
        $this->assertEquals(
            'movetarget.txt',
            $this->driver->moveFileWithinStorage('1.txt', '', 'movetarget.txt')
        );
        $this->assertTrue($this->driver->fileExists('movetarget.txt'));
        $this->assertFalse($this->driver->fileExists('1.txt'));

        $this->assertEquals(
            '1.txt',
            $this->driver->moveFileWithinStorage('movetarget.txt', '', '1.txt')
        );
    }

    public function testMoveFileWithinStorageSubfolder()
    {
        $this->assertTrue($this->driver->fileExists('1.txt'));
        $this->assertFalse($this->driver->fileExists('subfolder11/movetarget.txt'));
        $this->assertEquals(
            'subfolder11/movetarget.txt',
            $this->driver->moveFileWithinStorage('1.txt', 'subfolder11/', 'movetarget.txt')
        );
        $this->assertTrue($this->driver->fileExists('subfolder11/movetarget.txt'));
        $this->assertFalse($this->driver->fileExists('1.txt'));

        $this->assertEquals(
            '1.txt',
            $this->driver->moveFileWithinStorage('subfolder11/movetarget.txt', '', '1.txt')
        );
    }

    public function testSetFileContents()
    {
        $this->assertEquals(5, $this->driver->setFileContents('write.txt', 'write'));
        $this->assertEquals('write', $this->driver->getFileContents('write.txt'));
        $this->assertTrue($this->driver->deleteFile('write.txt'));
    }
}
