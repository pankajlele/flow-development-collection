<?php
namespace TYPO3\Flow\Tests\Unit\Core;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use TYPO3\Flow\Core\LockManager;
use TYPO3\Flow\Tests\UnitTestCase;

/**
 * Testcase for the LockManager
 */
class LockManagerTest extends UnitTestCase {

	/**
	 * @var LockManager|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $lockManager;

	/**
	 * @var vfsStreamDirectory
	 */
	protected $mockLockDirectory;

	/**
	 * @var vfsStreamFile
	 */
	protected $mockLockFile;

	/**
	 * @var vfsStreamFile
	 */
	protected $mockLockFlagFile;


	public function setUp() {
		$this->mockLockDirectory = vfsStream::setup('LockPath');
		$this->mockLockFile = vfsStream::newFile(md5(FLOW_PATH_ROOT) . '_Flow.lock')->at($this->mockLockDirectory);
		$this->mockLockFlagFile = vfsStream::newFile(md5(FLOW_PATH_ROOT) . '_FlowIsLocked')->at($this->mockLockDirectory);

		$this->lockManager = $this->getMockBuilder(LockManager::class)->setMethods(['getLockPath', 'doExit'])->disableOriginalConstructor()->getMock();
		$this->lockManager->expects($this->atLeastOnce())->method('getLockPath')->will($this->returnValue($this->mockLockDirectory->url() . '/'));
		$this->lockManager->__construct();
	}

	/**
	 * @test
	 */
	public function constructorDoesNotRemoveLockFilesIfTheyAreNotExpired() {
		$this->assertFileExists($this->mockLockFile->url());
		$this->assertFileExists($this->mockLockFlagFile->url());
	}

	/**
	 * @test
	 */
	public function constructorRemovesExpiredLockFiles() {
		$this->mockLockFlagFile->lastModified(time() - (LockManager::LOCKFILE_MAXIMUM_AGE + 1));
		$this->assertFileExists($this->mockLockFile->url());
		$this->assertFileExists($this->mockLockFlagFile->url());

		$this->lockManager->__construct();

		$this->assertFileNotExists($this->mockLockFile->url());
		$this->assertFileNotExists($this->mockLockFlagFile->url());
	}

	/**
	 * @test
	 */
	public function isSiteLockedReturnsTrueIfTheFlagFileExists() {
		$this->assertTrue($this->lockManager->isSiteLocked());
	}

	/**
	 * @test
	 */
	public function isSiteLockedReturnsFalseIfTheFlagFileDoesNotExist() {
		unlink($this->mockLockFlagFile->url());
		$this->assertFalse($this->lockManager->isSiteLocked());
	}

	/**
	 * @test
	 */
	public function exitIfSiteLockedExitsIfSiteIsLocked() {
		$this->lockManager->expects($this->once())->method('doExit');
		$this->lockManager->exitIfSiteLocked();
	}

	/**
	 * @test
	 */
	public function exitIfSiteLockedDoesNotExitIfSiteIsNotLocked() {
		$this->lockManager->unlockSite();
		$this->lockManager->expects($this->never())->method('doExit');
		$this->lockManager->exitIfSiteLocked();
	}

	/**
	 * test
	 */
	public function lockSiteOrExitCreatesLockFlagFileIfItDoesNotExist() {
		$mockLockFlagFilePathAndName = $this->mockLockFlagFile->url();
		unlink($mockLockFlagFilePathAndName);
		$this->lockManager->lockSiteOrExit();
		$this->assertFileExists($mockLockFlagFilePathAndName);
	}

	/**
	 * @test
	 */
	public function lockSiteOrExitUpdatesLockFlagFileLastModifiedTimestampIfItExists() {
		$oldLastModifiedTimestamp = time() - 100;
		$this->mockLockFlagFile->lastModified($oldLastModifiedTimestamp);

		$this->lockManager->lockSiteOrExit();

		$this->assertNotEquals($oldLastModifiedTimestamp, $this->mockLockFlagFile->filemtime());
	}

	/**
	 * @test
	 */
	public function lockSiteOrExitExitsIfSiteIsLocked() {
		$mockLockResource = fopen($this->mockLockFile->url(), 'w+');
		$this->mockLockFile->lock($mockLockResource, LOCK_EX | LOCK_NB);
		$this->lockManager->expects($this->once())->method('doExit');
		$this->lockManager->lockSiteOrExit();
	}

	/**
	 * @test
	 */
	public function lockSiteOrExitDoesNotExitIfSiteIsNotLocked() {
		$this->lockManager->expects($this->never())->method('doExit');
		$this->lockManager->lockSiteOrExit();
	}

	/**
	 * @test
	 */
	public function unlockSiteClosesLockResource() {
		$mockLockResource = fopen($this->mockLockFile->url(), 'w+');
		$this->mockLockFile->lock($mockLockResource, LOCK_EX | LOCK_NB);
		$this->inject($this->lockManager, 'lockResource', $mockLockResource);

		$this->lockManager->unlockSite();
		$this->assertFalse(is_resource($mockLockResource));
	}

	/**
	 * @test
	 */
	public function unlockSiteRemovesLockFlagFile() {
		$this->lockManager->unlockSite();
		$this->assertFileNotExists($this->mockLockFlagFile->url());
	}
}
