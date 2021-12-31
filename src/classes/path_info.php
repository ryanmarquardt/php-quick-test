<?php

namespace QuickTest {

  class PathInfo {
    public string $abs_path;
    public string $rel_path;
    public array $code;

    function __construct(string $path) {
      $this->abs_path = $path;
      $this->rel_path = substr($path, strlen(getcwd()));
      $this->code = file($path);
      array_unshift($this->code, '');  // Renumber lines for 1-based indexing
    }
  }
}
