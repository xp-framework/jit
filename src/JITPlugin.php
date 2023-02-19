<?php

namespace XP\Compiler;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

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

  
  public function process(Event $event): void
  {
    $io = $event->getIO();
    $composer = $event->getComposer();
    $version = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

    $package = $composer->getPackage();
    $vendor = $composer->getConfig()->get('vendor-dir');
    $autoload = $package->getAutoload();

    // Write JIT bootstrap
    $code = sprintf(
      "%s\nspl_autoload_register([new JIT(%s, sys_get_temp_dir().DIRECTORY_SEPARATOR.'%s-%s', '%s'), 'load']);",
      file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'JIT.php'),
      var_export($autoload['jit'], true),
      self::JIT,
      crc32($package->getName()),
      $version
    );
    file_put_contents($vendor.DIRECTORY_SEPARATOR.self::JIT, $code);

    // Add bootstrap to autoloader
    $jit = basename($vendor).'/'.self::JIT;
    if (isset($autoload['files'])) {
      $autoload['files'][] = $jit;
    } else {
      $autoload['files'] = [$jit];
    }
    $package->setAutoload($autoload);

    $io->write('<info>XP Compiler:</info> JIT(<comment>'.$jit.'@'.$version.'</comment>) enabled');
  }
}