<?php

namespace XP\Compiler;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class JITPlugin implements PluginInterface, EventSubscriberInterface 
{
  const JIT = 'jit.php';

  /** @return array<string, string> */
  public static function getSubscribedEvents(): array
  {
    return [ScriptEvents::PRE_AUTOLOAD_DUMP => 'process'];
  }

  public function activate(Composer $composer, IOInterface $io): void
  {
    // NOOP
  }

  public function deactivate(Composer $composer, IOInterface $io): void
  {
    // NOOP
  }

  public function uninstall(Composer $composer, IOInterface $io): void
  {
    // NOOP
  }

  /** @return array<string, string> */
  private function sourcesIn(string $baseDir, RepositoryInterface $repository): array
  {
    $sources = [];
    foreach ($repository->getPackages() as $package) {
      $autoload = $package->getAutoload();
      if (!isset($autoload['jit'])) continue;

      foreach ($autoload['jit'] as $prefix => $source) {
        $sources[$prefix] = $baseDir.'/'.$package->getName().'/'.$source;
      }
    }
    return $sources;
  }
  
  public function process(Event $event): void
  {
    $io = $event->getIO();
    $composer = $event->getComposer();
    $vendor = $composer->getConfig()->get('vendor-dir');
    $package = $composer->getPackage();

    // Check for JIT in root package, then in all others
    $autoload = $package->getAutoload();
    $sources = $autoload['jit'] ?? [];
    $sources += $this->sourcesIn(basename($vendor), $composer->getRepositoryManager()->getLocalRepository());
    if (empty($sources)) return;

    // Write JIT bootstrap
    $version = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
    $code = sprintf(
      "%s\nspl_autoload_register([new JIT(%s, sys_get_temp_dir().DIRECTORY_SEPARATOR.'%s-%s', '%s'), 'load']);",
      file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'JIT.php'),
      var_export($sources, true),
      self::JIT,
      crc32($package->getName()),
      $version
    );
    file_put_contents($vendor.DIRECTORY_SEPARATOR.self::JIT, $code);

    // Add bootstrap to this package's autoloader
    $autoload = $package->getAutoload();
    $jit = basename($vendor).'/'.self::JIT;
    if (isset($autoload['files'])) {
      $autoload['files'][] = $jit;
    } else {
      $autoload['files'] = [$jit];
    }
    $package->setAutoload($autoload);

    $list = '';
    foreach ($sources as $prefix => $source) {
      $list .= ', '.rtrim($prefix, '\\').' => '.$source;
    }
    $io->write('<info>XP Compiler:</info> JIT ('.substr($list, 2).')');
  }
}