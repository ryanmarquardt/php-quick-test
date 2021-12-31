<?php


define('QUICKTEST_PATH', __DIR__ . '/');

require_once QUICKTEST_PATH . 'include/include.php';

if (count($_SERVER['argv']) > 1 && $_SERVER['argv'][1] === 'run') {
  require_class('runner');
  exit((new \QuickTest\Runner($_SERVER['argv']))->run());

} else {
  require_class('controller');
  exit((new \QuickTest\Controller($_SERVER['argv']))->run());

}
