<?php

namespace ComposerIncludeFiles\Composer;

use Composer\Autoload\AutoloadGenerator as ComposerAutoloadGenerator;
use Composer\Composer;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;

class AutoloadGenerator extends ComposerAutoloadGenerator
{
	/**
	 * @param array
	 * @param \Composer\Package\PackageInterface
	 */
	public function parseAutoloadsTypeFiles($paths, PackageInterface $mainPackage)
	{
		$autoloads = array();

		$installPath = '';

		foreach ((array)$paths as $path) {
			if ($mainPackage->getTargetDir() && !is_readable($installPath.'/'.$path)) {
				// remove target-dir from file paths of the root package
				$targetDir = str_replace('\\<dirsep\\>', '[\\\\/]', preg_quote(str_replace(array('/', '\\'), '<dirsep>', $mainPackage->getTargetDir())));
				$path = ltrim(preg_replace('{^'.$targetDir.'}', '', ltrim($path, '\\/')), '\\/');
			}

			$relativePath = empty($installPath) ? (empty($path) ? '.' : $path) : $installPath.'/'.$path;

			$autoloads[$this->getFileIdentifier($mainPackage, $path)] = $relativePath;
		}

		return $autoloads;
	}

	/**
	 * @param \Composer\Composer
	 * @param string
	 * @param string
	 */
	public function dumpFiles(Composer $composer, $paths, $targetDir = 'composer')
	{
		$installationManager = $composer->getInstallationManager();
		$localRepo = $composer->getRepositoryManager()->getLocalRepository();
		$mainPackage = $composer->getPackage();
		$config = $composer->getConfig();

		$filesystem = new Filesystem();
		$basePath = $filesystem->normalizePath(realpath(getcwd()));
		$vendorPath = $filesystem->normalizePath(realpath($config->get('vendor-dir')));
		$targetDir = $vendorPath.'/'.$targetDir;
		$vendorPathCode = $filesystem->findShortestPathCode(realpath($targetDir), $vendorPath, true);
		$vendorPathCode52 = str_replace('__DIR__', 'dirname(__FILE__)', $vendorPathCode);
		$appBaseDirCode = $filesystem->findShortestPathCode($vendorPath, $basePath, true);
		$appBaseDirCode = str_replace('__DIR__', '$vendorDir', $appBaseDirCode);

		$packageMap = $this->buildPackageMap($installationManager, $mainPackage, $localRepo->getCanonicalPackages());
		$autoloads = $this->parseAutoloads($packageMap, $mainPackage);

		$paths = $this->parseAutoloadsTypeFiles($paths, $mainPackage);

		$autoloads['files'] = array_merge($paths, $autoloads['files']);

		$includeFilesFilePath = $targetDir.'/autoload_files.php';
		if ($includeFilesFileContents = $this->getIncludeFilesFile($autoloads['files'], $filesystem, $basePath, $vendorPath, $vendorPathCode52, $appBaseDirCode)) {
			file_put_contents($includeFilesFilePath, $includeFilesFileContents);
		} elseif (file_exists($includeFilesFilePath)) {
			unlink($includeFilesFilePath);
		}
	}
}
