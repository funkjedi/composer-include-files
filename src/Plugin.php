<?php

namespace ComposerIncludeFiles;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use ComposerIncludeFiles\Composer\AutoloadGenerator;

class Plugin implements PluginInterface, EventSubscriberInterface
{
	/**
	 * @var \Composer\Composer
	 */
	protected $composer;

	/**
	 * @var \ComposerIncludeFiles\Composer\AutoloadGenerator
	 */
	protected $generator;

	/**
	 * Apply plugin modifications to Composer
	 *
	 * @param Composer $composer
	 * @param IOInterface $io
	 */
	public function activate(Composer $composer, IOInterface $io)
	{
		$this->composer = $composer;
		$this->generator = new AutoloadGenerator($composer->getEventDispatcher(), $io);
	}

	/**
	 * @param Composer $composer
	 * @param IOInterface $io
	 */
	public function deactivate(Composer $composer, IOInterface $io)
	{
		// do nothing
	}

	/**
	 * @param Composer $composer
	 * @param IOInterface $io
	 */
	public function uninstall(Composer $composer, IOInterface $io)
	{
		// do nothing
	}

	/**
	 * @return array
	 */
	public static function getSubscribedEvents()
	{
		return array(
			ScriptEvents::POST_INSTALL_CMD => 'dumpFiles',
			'post-autoload-dump' => 'dumpFiles',
		);
	}

	public function dumpFiles()
	{
		$extraConfig = $this->composer->getPackage()->getExtra();

		if (!array_key_exists('include_files', $extraConfig) || !is_array($extraConfig['include_files'])) {
			return;
		}

		$this->generator->dumpFiles($this->composer, $extraConfig['include_files']);
	}
}
