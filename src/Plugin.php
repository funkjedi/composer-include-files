<?php

namespace ComposerIncludeFiles;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use ComposerIncludeFiles\Composer\AutoloadGenerator;
use Composer\Package\CompletePackage;
use Composer\Script\Event;
use Composer\Util\Filesystem;

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
	 * @param Composer    $composer
	 * @param IOInterface $io
	 */
	public function activate(Composer $composer, IOInterface $io)
	{
		$this->composer = $composer;
		$this->generator = new AutoloadGenerator($composer->getEventDispatcher(), $io);
	}

	/**
	 * @return array
	 */
	public static function getSubscribedEvents()
	{
	    return array(
			'post-autoload-dump' => 'dumpFiles',
		);
	}

	public function dumpFiles(Event $event)
	{
	    // var to hold the include files
        $extraIncludeFiles = [];

        // setup filesystem object
        $filesystem = new Filesystem();

        // set up installation manage
        $installationManager = $event->getComposer()->getInstallationManager();

        // get all packages except the root package
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();

        // process packages
        foreach ($packages as $package) {

            // only include CompletePackages
            if ($package instanceof CompletePackage) {

                // get this package extra config values
                $packageConfig = $package->getExtra();

                // if has include files
                if (isset($packageConfig['include_files'])) {

                    // get this package base dir
                    $packageBaseDir = $filesystem->normalizePath($installationManager->getInstallPath($package));

                    // process each include file for the package
                    foreach ($packageConfig['include_files'] as $file) {

                        // update the path of the package file to be realitive of the vendor dir
                        $extraIncludeFiles[] = $filesystem->normalizePath($packageBaseDir . '/' . $file);
                    }
                }
            }
        }

        // check if the root package has include files
        $package = $this->composer->getPackage()->getExtra();
        if (isset($package['include_files'])) {
            $extraIncludeFiles = array_merge($extraIncludeFiles, $package['include_files']);
        }

        // if we have files, then process them
        if (!empty($extraIncludeFiles)) {
            $this->generator->dumpFiles($this->composer, $extraIncludeFiles);
        }
	}
}
