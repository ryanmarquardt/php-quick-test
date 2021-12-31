<?php

namespace QuickTest {
  require_once QUICKTEST_PATH . 'include/mocks.php';

  class Runner {
    function __construct($argv) {
      $this->parse_argv($argv);
    }

    function parse_argv($argv) {
      $this->path = $argv[2];
      $this->class_name = $argv[3];
      $this->method_name = $argv[4];
    }

    function run() {
      $exit_code = $this->run_test_case(
        $this->get_test_case()
      );

      foreach (Mocks\Data::$error_log as $error_logged) {
        fwrite(STDERR, "{$error_logged[0]}\n");
      }

      exit($exit_code);
    }

    function get_test_case(): callable|string {
      require $this->path;

      return (
        empty($this->class_name)
        ? $this->method_name
        : [new $this->class_name(), $this->method_name]
      );
    }

    function run_test_case($test_case) {
      try {
        $test_case();
      } catch (\AssertionError $failure) {
        return $this->handle_failure($failure);
      } catch (\Throwable $error) {
        return $this->handle_error($error);
      }
      return $this->handle_success();
    }

    function handle_success(): int {
      return 0;
    }

    function handle_failure(\AssertionError $failure): int {
      echo serialize([
        'message' => $failure->getMessage(),
        'trace' => array_merge(
          [
            [
              'file' => $failure->getFile(),
              'line' => $failure->getLine(),
              'class' => $this->class_name,
              'function' => $this->method_name,
              'type' => (
                empty($this->class_name)
                ? null
                : (
                  (new \ReflectionMethod($this->class_name, $this->method_name))->isStatic()
                  ? '::'
                  : '->'
                )
              ),
              'args' => [],
            ]
          ],
          $failure->getTrace()
        ),
      ]);
      return 1;
    }

    function handle_error(\Throwable $error): int {
      throw $error;
    }
  }
}
