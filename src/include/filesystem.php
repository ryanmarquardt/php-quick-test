<?php

namespace QuickTest {

  function get_files_recursive(string $path) {
    if (is_dir($path)) {
      if (!str_ends_with($path, '/')) {
        $path = $path . '/';
      }
      $handle = opendir($path);
      while (($entry = readdir($handle)) !== false) {
        if ($entry !== '.' && $entry !== '..') {
          foreach (get_files_recursive($path . $entry) as $subpath) {
            yield $subpath;
          }
        }
      }
    } else {
      yield $path;
    }
  }

}
