<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaner;

final class Cleaner {

	private function __construct() {
	}

	/**
	 * @param string[] $plugins
	 * @param string[] $exceptionData
	 *
	 * @return string[]
	 */
	public static function clean(string $pluginDataPath, array $plugins, array $exceptionData): array {
		if (!is_dir($pluginDataPath)) {
			return [];
		}

		$deleted = [];
		$directoryIterator = new \DirectoryIterator($pluginDataPath);
		foreach ($directoryIterator as $fileInfo) {
			$fileName = $fileInfo->getFilename();
			$success = self::delete(
				$fileInfo,
				in_array($fileName, $plugins, true),
				$exceptionData
			);
			if ($success) {
				$deleted[] = $fileName;
			}
		}

		return $deleted;
	}

	/**
	 * Cleans plugin data while moving deleted entries into a backup directory.
	 * The backup directory itself is cleared before new entries are moved into it.
	 *
	 * @param string[] $plugins
	 * @param string[] $exceptionData
	 *
	 * @return string[]
	 */
	public static function cleanWithBackup(string $pluginDataPath, array $plugins, array $exceptionData, string $backupPath): array {
		if (!self::clearDirectory($backupPath)) {
			throw new \RuntimeException("Unable to prepare the backup directory: " . $backupPath);
		}
		if (!is_dir($pluginDataPath)) {
			return [];
		}

		$deleted = [];
		$backupFolderName = basename(dirname(rtrim($backupPath, DIRECTORY_SEPARATOR)));
		$directoryIterator = new \DirectoryIterator($pluginDataPath);
		foreach ($directoryIterator as $fileInfo) {
			$fileName = $fileInfo->getFilename();
			// The backup lives inside DataCleaner's own plugin data and must survive this pass.
			if ($fileName === $backupFolderName) {
				continue;
			}

			$success = self::moveToBackup(
				$fileInfo,
				in_array($fileName, $plugins, true),
				$exceptionData,
				$backupPath
			);
			if ($success) {
				$deleted[] = $fileName;
			}
		}

		return $deleted;
	}

	/**
	 * Removes all entries from a directory while keeping the directory itself.
	 */
	public static function clearDirectory(string $directory): bool {
		if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
			return false;
		}

		foreach (new \DirectoryIterator($directory) as $fileInfo) {
			if (!$fileInfo->isDot() && !self::delete($fileInfo, false, [])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string[] $exceptionData
	 *
	 * @return bool true on success or false on failure.
	 */
	public static function delete(\DirectoryIterator $fileInfo, bool $justEmpty, array $exceptionData): bool {
		if ($fileInfo->isDir()) {
			return self::deleteFolder($fileInfo, $justEmpty, $exceptionData);
		} elseif ($fileInfo->isFile()) {
			return self::deleteFile($fileInfo, $exceptionData);
		}

		throw new \InvalidArgumentException($fileInfo->getFilename() . " is a " . $fileInfo->getType() . " but he must be a file or folder");
	}

	/**
	 * @param string[] $exceptionData
	 *
	 * @return bool true on success or false on failure.
	 */
	public static function deleteFile(\DirectoryIterator $file, array $exceptionData): bool {
		if (!$file->isFile()) {
			throw new \InvalidArgumentException($file->getFilename() . " is a " . $file->getType() . " but he must be a file");
		} elseif (in_array($file->getFilename(), $exceptionData, true)) {
			return false;
		}

		return @unlink($file->getPathname());
	}

	/**
	 * @param string[] $exceptionData
	 *
	 * @return bool true on success or false on failure.
	 */
	public static function deleteFolder(\DirectoryIterator $folder, bool $justEmpty, array $exceptionData): bool {
		if (!$folder->isDir()) {
			throw new \InvalidArgumentException($folder->getFilename() . " is a " . $folder->getType() . " but he must be a folder");
		} elseif (in_array($folder->getFilename(), $exceptionData, true)) {
			return false;
		}

		if ($justEmpty) {
			$directoryIterator = new \DirectoryIterator($folder->getPathname());
			foreach ($directoryIterator as $fileInfo) {
				if ($fileInfo->isFile()) {
					return false;
				}
				if (!$fileInfo->isDot() && !self::deleteFolder($fileInfo, true, $exceptionData)) {
					return false;
				}
			}

			$filePathName = $folder->getPathname();
			if (count(scandir($filePathName)) <= 2) {
				return @rmdir($filePathName);
			}
		} elseif (self::deleteFilesInFolder($folder, $exceptionData)) {
			return @rmdir($folder->getPathname());
		}

		return false;
	}

	/**
	 * @param string[] $exceptionData
	 *
	 * @return bool true on success or false on failure.
	 */
	public static function deleteFilesInFolder(\DirectoryIterator $folder, array $exceptionData): bool {
		$directoryIterator = new \DirectoryIterator($folder->getPathname());
		foreach ($directoryIterator as $fileInfo) {
			if (!$fileInfo->isDot() && !self::delete($fileInfo, false, $exceptionData)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks whether the existing cleanup rules allow an entry to be deleted.
	 * This is done before moving an entry so exception data is never backed up as deleted data.
	 *
	 * @param string[] $exceptionData
	 */
	private static function canDelete(\DirectoryIterator $fileInfo, bool $justEmpty, array $exceptionData): bool {
		if ($fileInfo->isDir()) {
			if (in_array($fileInfo->getFilename(), $exceptionData, true)) {
				return false;
			}

			foreach (new \DirectoryIterator($fileInfo->getPathname()) as $child) {
				if ($child->isDot()) {
					continue;
				}
				if ($child->isFile()) {
					if ($justEmpty || in_array($child->getFilename(), $exceptionData, true)) {
						return false;
					}
				} elseif (!$child->isDir() || !self::canDelete($child, $justEmpty, $exceptionData)) {
					return false;
				}
			}

			return true;
		}

		return $fileInfo->isFile() && !in_array($fileInfo->getFilename(), $exceptionData, true);
	}

	/**
	 * @param string[] $exceptionData
	 */
	private static function moveToBackup(
		\DirectoryIterator $fileInfo,
		bool $justEmpty,
		array $exceptionData,
		string $backupPath
	): bool {
		if (!self::canDelete($fileInfo, $justEmpty, $exceptionData)) {
			return false;
		}

		$backupFilePath = rtrim($backupPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileInfo->getFilename();
		return @rename($fileInfo->getPathname(), $backupFilePath);
	}
}
