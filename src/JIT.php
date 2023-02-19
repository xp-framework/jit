<?php

namespace XP\Compiler;

use io\File;
use lang\ast\{Language, Emitter, Tokens};

class JIT
{
  private $sources, $target, $version, $lang, $emit;

  public function __construct(array $sources, string $target, string $version)
  {
    $this->sources = $sources;
    $this->target = $target;
    $this->version = $version;

    // Ensure target directory exists
    is_dir($target) || mkdir($target, 0755);
  }

  public function compile(File $source, File $target): void
  {

    // Lazy-init compiler
    if (null === $this->emit) {
      $this->lang = Language::named('PHP');
      $this->emit = Emitter::forRuntime("php:$this->version")->newInstance();
      foreach ($this->lang->extensions() as $extension) {
        $extension->setup($this->lang, $this->emit);
      }
    }

    $this->emit->write(
      $this->lang->parse(new Tokens($source))->stream(),
      $target->out()
    );
  }

  public function load(string $class): bool
  {
    foreach ($this->sources as $prefix => $source) {
      $l = strlen($prefix);
      if (0 !== strncmp($prefix, $class, $l)) continue;

      // Use flat filesystem structure inside target directory
      $fname = strtr(substr($class, $l), '\\', DIRECTORY_SEPARATOR);
      $source = $source.DIRECTORY_SEPARATOR.$fname.'.php';
      $target = $this->target.DIRECTORY_SEPARATOR.crc32(dirname($fname)).'-'.basename($fname).'.php';

      if (!is_file($target) || filemtime($target) < filemtime($source)) {
        $this->compile(new File($source), new File($target));
      }

      return include($target);
    }

    // Name does not match any prefix, delegate to next loader
    return false;
  }
}