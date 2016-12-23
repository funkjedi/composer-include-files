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
	 *
	 * @see https://github.com/composer/composer/blob/master/src/Composer/Autoload/AutoloadGenerator.php#L115
	 */
	public function dumpFiles(Composer $composer, $paths, $targetDir = 'composer', $suffix = '', $staticPhpVersion = 70000)
	{
		$installationManager = $composer->getInstallationManager();
		$localRepo = $composer->getRepositoryManager()->getLocalRepository();
		$mainPackage = $composer->getPackage();
		$config = $composer->getConfig();

		$filesystem = new Filesystem();
		$filesystem->ensureDirectoryExists($config->get('vendor-dir'));
		// Do not remove double realpath() calls.
		// Fixes failing Windows realpath() implementation.
		// See https://bugs.php.net/bug.php?id=72738
		$basePath = $filesystem->normalizePath(realpath(realpath(getcwd())));
		$vendorPath = $filesystem->normalizePath(realpath(realpath($config->get('vendor-dir'))));
		$targetDir = $vendorPath.'/'.$targetDir;
		$filesystem->ensureDirectoryExists($targetDir);

		$vendorPathCode = $filesystem->findShortestPathCode(realpath($targetDir), $vendorPath, true);
		$vendorPathCode52 = str_replace('__DIR__', 'dirname(__FILE__)', $vendorPathCode);
		$vendorPathToTargetDirCode = $filesystem->findShortestPathCode($vendorPath, realpath($targetDir), true);

		$appBaseDirCode = $filesystem->findShortestPathCode($vendorPath, $basePath, true);
		$appBaseDirCode = str_replace('__DIR__', '$vendorDir', $appBaseDirCode);

		// Collect information from all packages.
		$packageMap = $this->buildPackageMap($installationManager, $mainPackage, $localRepo->getCanonicalPackages());
		$autoloads = $this->parseAutoloads($packageMap, $mainPackage);

		if (!$suffix) {
			if (!$config->get('autoloader-suffix') && is_readable($vendorPath.'/autoload.php')) {
				$content = file_get_contents($vendorPath.'/autoload.php');
				if (preg_match('{ComposerAutoloaderInit([^:\s]+)::}', $content, $match)) {
					$suffix = $match[1];
				}
			}
			if (!$suffix) {
				$suffix = $config->get('autoloader-suffix') ?: md5(uniqid('', true));
			}
		}

		$paths = $this->parseAutoloadsTypeFiles($paths, $mainPackage);

		$autoloads['files'] = array_merge($paths, $autoloads['files']);

		$includeFilesFilePath = $targetDir.'/autoload_files.php';
		if ($includeFilesFileContents = $this->getIncludeFilesFile($autoloads['files'], $filesystem, $basePath, $vendorPath, $vendorPathCode52, $appBaseDirCode)) {
			file_put_contents($includeFilesFilePath, $includeFilesFileContents);
		} elseif (file_exists($includeFilesFilePath)) {
			unlink($includeFilesFilePath);
		}
		file_put_contents($targetDir.'/autoload_static.php', $this->getStaticFile($suffix, $targetDir, $vendorPath, $basePath, $staticPhpVersion));
	}
}
