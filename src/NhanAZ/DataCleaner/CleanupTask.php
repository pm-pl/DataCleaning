<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaner;

use pocketmine\scheduler\AsyncTask;

final class CleanupTask extends AsyncTask {

	private string $pluginDataPath;

	private string $backupPath;

	/** @var string[] */
	private array $plugins;

	/** @var string[] */
	private array $exceptionData;

	/**
	 * @param string[] $plugins
	 * @param string[] $exceptionData
	 */
	public function __construct(string $pluginDataPath, string $backupPath, array $plugins, array $exceptionData, Main $plugin) {
		$this->pluginDataPath = $pluginDataPath;
		$this->backupPath = $backupPath;
		$this->plugins = $plugins;
		$this->exceptionData = $exceptionData;

		// Plugin instances cannot be serialized into a worker thread.
		$this->storeLocal("plugin", $plugin);
	}

	public function onRun(): void {
		$this->setResult(Cleaner::cleanWithBackup($this->pluginDataPath, $this->plugins, $this->exceptionData, $this->backupPath));
	}

	public function onCompletion(): void {
		/** @var Main $plugin */
		$plugin = $this->fetchLocal("plugin");
		$deleted = $this->getResult();
		if (is_array($deleted)) {
			$plugin->deleteMessage($deleted);
		}
	}

	public function onError(): void {
		/** @var Main $plugin */
		$plugin = $this->fetchLocal("plugin");
		$plugin->getLogger()->error("Unable to clean the plugin_data directory asynchronously");
	}
}
