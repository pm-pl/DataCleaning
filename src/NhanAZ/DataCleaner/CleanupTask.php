<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaner;

use pocketmine\scheduler\AsyncTask;

final class CleanupTask extends AsyncTask {

	private string $pluginDataPath;

	/** @var string[] */
	private array $plugins;

	/** @var string[] */
	private array $exceptionData;

	/**
	 * @param string[] $plugins
	 * @param string[] $exceptionData
	 */
	public function __construct(string $pluginDataPath, array $plugins, array $exceptionData, Main $plugin) {
		$this->pluginDataPath = $pluginDataPath;
		$this->plugins = $plugins;
		$this->exceptionData = $exceptionData;

		// Plugin instances cannot be serialized into a worker thread.
		$this->storeLocal("plugin", $plugin);
	}

	public function onRun(): void {
		$this->setResult(Cleaner::clean($this->pluginDataPath, $this->plugins, $this->exceptionData));
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
