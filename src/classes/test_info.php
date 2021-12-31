<?php

namespace QuickTest {

  require_class('callable_info');
  require_class('path_info');

  define('TEST_STATUS_PENDING', 'pending');
  define('TEST_STATUS_SKIPPED', 'skipped');
  define('TEST_STATUS_OK', 'ok');
  define('TEST_STATUS_FAILED', 'fail');
  define('TEST_STATUS_XFAILED', 'xfail');
  define('TEST_STATUS_XPASSED', 'xpass');
  define('TEST_STATUS_ERROR', 'error');

  class TestInfo {
    public array $code;
    public PathInfo $path_info;
    public CallableInfo $callable_info;

    public string $status;
    public array $result;
    public int $exit_code;

    function __construct(PathInfo $path_info, CallableInfo $callable_info) {
      $this->path_info = $path_info;
      $this->callable_info = $callable_info;
      $this->status = TEST_STATUS_PENDING;

      if (
        ($start_line = $callable_info->get_start_line()) !== false
        && ($end_line = $callable_info->get_end_line()) !== false
      ) {
        $this->code = array_slice(
          $path_info->code, $start_line, $end_line - $start_line + 1, true
        );
      } else {
        $this->code = null;
      }
    }

    function set_result(array $result = []) {
      $this->result = $result;

      $this->set_status_and_exit_code();
      $this->set_failure_point();
    }

    protected function set_status_and_exit_code() {
      if ($this->callable_info->is_skipped()) {
        $this->status = TEST_STATUS_SKIPPED;
        $this->exit_code = 0;
      } else {
        $is_xfail = $this->callable_info->is_xfail();
        switch ($this->result['exit_code']) {
          case 0:
            $this->status = ($is_xfail ? TEST_STATUS_XPASSED : TEST_STATUS_OK);
            $this->exit_code = ($is_xfail ? 1 : 0);
            break;

          case 1:
            $this->status = ($is_xfail ? TEST_STATUS_XFAILED : TEST_STATUS_FAILED);
            $this->exit_code = ($is_xfail ? 0 : 1);
            break;

          default:
            $this->status = TEST_STATUS_ERROR;
            $this->exit_code = 255;
        }
      }
    }

    protected function set_failure_point() {
      if (!empty($this->result['stdout']) && isset($this->result['stdout']['trace'])) {
        foreach ($this->result['stdout']['trace'] as &$frame) {
          if (
            $frame['file'] === $this->path_info->abs_path
            && array_key_exists($frame['line'], $this->code)
          ) {
            $this->result['stdout']['trace']['failure_point'] = $frame['line'];
            break;
          }
        }
      }
    }

  }
}
