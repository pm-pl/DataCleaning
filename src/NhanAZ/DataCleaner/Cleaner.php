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
}
