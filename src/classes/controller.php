<?php


namespace QuickTest {

  require_class('callable_info');
  require_class('path_info');
  require_class('test_info');
  require_class('context_manager');
  require_lib('introspection');
  require_lib('arguments');
  require_lib('filesystem');
  require_lib('subprocess');
  require_lib('terminal');

  define('VERBOSITY_SUPPRESS_ALL', -3);
  define('VERBOSITY_SUPPRESS_FAILURES', -2);
  define('VERBOSITY_SUPPRESS_PROGRESS', -1);
  define('VERBOSITY_COMPRESSED_PROGRESS', 0);
  define('VERBOSITY_FULL_PROGRESS', 1);

  class Controller {
    protected int $exit_code;
    protected array $counts;
    protected int $test_cases_found;
    protected array $test_cases;
    protected array $fixtures;
    protected array $summary;
    protected array $RESULT_STATUS;
    private int $verbosity;
    private string $quick_test_path;
    protected string $cwd;

    public function __construct($argv) {
      $this->exit_code = 0;
      $this->counts = [
        TEST_STATUS_SKIPPED => 0,
        TEST_STATUS_OK => 0,
        TEST_STATUS_FAILED => 0,
        TEST_STATUS_XFAILED => 0,
        TEST_STATUS_XPASSED => 0,
        TEST_STATUS_ERROR => 0,
      ];
      $this->test_cases_found = 0;
      $this->test_cases = [];
      $this->fixtures = [];
      $this->summary = [];
      $this->cwd = getcwd();

      $this->parse_argv($argv);
    }

    protected function parse_argv($argv): void {
      $this->quick_test_path = $argv[0];

      // Defaults
      $verbosity = VERBOSITY_COMPRESSED_PROGRESS;
      $test_pattern = '';

      parse_argv(
        $argv,
        [
          '-v' => (
            #[help('Increase verbosity')]
            function () use (&$verbosity) {
              $verbosity++;
            }
          ),
          '-q' =>(
            #[help('Decrease verbosity')]
            function () use (&$verbosity) {
              $verbosity--;
            }
          ),
          '-k' => (
            #[help('Select tests matching regex')]
            function ($pattern) use (&$test_pattern) {
              $test_pattern = $pattern;
            }
          ),
        ],
      );

      $this->set_verbosity($verbosity);
    }

    function get_verbosity(): int {
      return $this->verbosity;
    }

    function set_verbosity(int $value): void {
      $this->verbosity = $value;

      if ($this->verbosity <= VERBOSITY_SUPPRESS_PROGRESS) {
        $this->RESULT_STATUS = [
          TEST_STATUS_OK => '',
          TEST_STATUS_FAILED => '',
          TEST_STATUS_ERROR => '',
          TEST_STATUS_SKIPPED => '',
          TEST_STATUS_XFAILED => '',
          TEST_STATUS_XPASSED => '',
        ];
      } elseif ($this->verbosity == VERBOSITY_COMPRESSED_PROGRESS) {
        $this->RESULT_STATUS = [
          TEST_STATUS_OK => ".",
          TEST_STATUS_FAILED => color('F', 'yellow'),
          TEST_STATUS_ERROR => color('E', null, 'red'),
          TEST_STATUS_SKIPPED => "s",
          TEST_STATUS_XFAILED => "x",
          TEST_STATUS_XPASSED => color('P', 'red'),
        ];
      } else {
        $this->RESULT_STATUS = [
          TEST_STATUS_OK => color('OK', 'green') . "\n",
          TEST_STATUS_FAILED => color('FAILED', 'yellow') . "\n",
          TEST_STATUS_ERROR => color('ERROR', 'red') . "\n",
          TEST_STATUS_SKIPPED => "SKIPPED\n",
          TEST_STATUS_XFAILED => color('XFAIL', 'green') . "\n",
          TEST_STATUS_XPASSED => color('XPASS', 'red') . "\n",
        ];
      }
    }

    function run(): int {
      $this->collect_tests();
      $result = $this->run_tests();
      $this->report();
      return $result;
    }

    // Collection phase

    protected function collect_tests(): void {
      $this->pre_collect();

      foreach ($this->discover_testable_files(getcwd()) as $abs_path) {
        $path_info = new PathInfo($abs_path);;

        $this->pre_collect_file($path_info);
        $tests = [];

        foreach ($this->discover_callables($abs_path) as $callable_info) {
          if ($callable_info->is_fixture()) {
            $this->fixtures[$callable_info->call] = $callable_info;

          } elseif ($callable_info->is_test()) {
            $tests[] = $test = new TestInfo($path_info, $callable_info);

          }
          $this->post_collect_test($test);
        }
        $this->test_cases[$path_info->abs_path] = $tests;
        $this->post_collect_file($path_info);
      }
      $this->post_collect();
    }

    protected function pre_collect(): void {}

    protected function pre_collect_file(PathInfo &$path_info): void {}

    protected function post_collect_test(TestInfo &$test_info): void {}

    protected function post_collect_file(PathInfo &$path_info): void {}

    protected function post_collect(): void {
      $file_count = count($this->test_cases);
      $files = "{$file_count} file" . ($file_count == 1 ? '' : 's');
      foreach ($this->test_cases as $path => $tests) {
        $this->test_cases_found += count($tests);
      }
      $tests = "{$this->test_cases_found} test" . ($this->test_cases_found == 1 ? '' : 's');
      if (VERBOSITY_SUPPRESS_PROGRESS < $this->verbosity) {
        echo "Found {$tests} in {$files}\n\n";
      }
    }

    /*
     * Find files ending in '.php' recursively
     */
    function discover_testable_files(string $path): \Iterator {
      foreach (get_files_recursive($path) as $subpath) {
        if (
          $this->filename_is_testable(substr($subpath, strrpos($subpath, '/') + 1))
        ) {
          yield $subpath;
        }
      }
    }

    function filename_is_testable(string $filename): bool {
      return (
        (
          str_starts_with($filename, 'test_')
          && str_ends_with($filename, '.php')
        )
        || str_ends_with($filename, '_test.php')
      );
    }

    /*
     * Include a file, and return a list of callable, testable functions and methods.
     */
    function discover_callables(string $path): \Iterator {
      $before_functions = get_defined_functions()['user'];
      $before_classes = get_declared_classes();

      require $path;

      foreach (
        array_diff(get_defined_functions()['user'], $before_functions)
        as $function_name
      ) {
        yield CallableInfo::from_function($path, $function_name);
      }

      foreach (array_diff(get_declared_classes(), $before_classes) as $class_name) {
        foreach (get_class_methods($class_name) as $method_name) {
          yield CallableInfo::from_method($path, $class_name, $method_name);
        }
      }
    }

    // Run phase

    function run_tests(): int {
      $this->pre_run();

      foreach ($this->test_cases as $path => &$tests) {
        $this->pre_run_file($path, $tests);

        foreach ($tests as &$test_info) {
          $this->pre_run_test($test_info);

          $result = [];

          if (!$test_info->callable_info->is_skipped()) {
            $command = array_merge(
              [PHP_BINARY, $this->quick_test_path, 'run', $path],
              $test_info->callable_info->argv()
            );
            $result = run_subprocess($command);
          }

          $test_info->set_result($result);
          $this->exit_code = max($this->exit_code, $test_info->exit_code);

          $this->post_run_test($test_info);
        }
        $this->post_run_file($path, $tests);
      }
      $this->post_run();
      return $this->exit_code;
    }

    protected function pre_run(): void {}

    protected function pre_run_file(string $path, array &$tests): void {
      if ($this->verbosity === VERBOSITY_COMPRESSED_PROGRESS) {
        echo "$path: ";
      }
    }

    protected function pre_run_test(TestInfo &$test_info): void {
      if (VERBOSITY_FULL_PROGRESS <= $this->verbosity) {
        printf(
          "%s:%s... ",
          $test_info->path_info->abs_path,
          $test_info->callable_info,
        );
      }
    }

    protected function post_run_test(TestInfo &$test_info): void {
      $this->counts[$test_info->status]++;
      echo $this->RESULT_STATUS[$test_info->status];
    }

    protected function post_run_file(string $path, array &$tests): void {
      if ($this->verbosity === VERBOSITY_COMPRESSED_PROGRESS) {
        echo "\n";
      }
    }

    protected function post_run(): void {}

    function report() {
      if (VERBOSITY_SUPPRESS_PROGRESS < $this->verbosity) {
        echo ".\n";
      }

      if (count($this->test_cases) === 0) {
        $this->report_no_files();

      } else if ($this->test_cases_found === 0) {
        $this->report_no_tests();

      } else {
        foreach ($this->test_cases as $path => &$tests) {
          foreach ($tests as $index => $test_info) {
            [
              TEST_STATUS_OK => [$this, 'report_summary_ok'],
              TEST_STATUS_FAILED => [$this, 'report_summary_failed'],
              TEST_STATUS_ERROR => [$this, 'report_summary_error'],
              TEST_STATUS_SKIPPED => [$this, 'report_summary_skipped'],
              TEST_STATUS_XFAILED => [$this, 'report_summary_xfailed'],
              TEST_STATUS_XPASSED => [$this, 'report_summary_xpassed'],
            ][$test_info->status]($test_info);
          }
        }
        $this->report_summary_all();
      }

    }

    function report_no_files(): void {
      echo color(
        "No files containing testable functions or methods were found under {$this->cwd}\n",
        'red',
      );
    }

    function report_no_tests(): void {
      $file_count = count($this->test_cases);
      echo color(
        (
          "No testable functions or methods were found in {$file_count} file"
          . ($file_count === 1 ? '' : 's')
        ),
        'red',
      );
    }

    function report_summary_ok(TestInfo $test_info): void {}

    function report_summary_failed(TestInfo $test_info): void {
      if (VERBOSITY_SUPPRESS_FAILURES < $this->verbosity) {
        if (
          isset($test_info->code)
          && isset($test_info->result['stdout']['trace']['failure_point'])
        ) {
          printf(
            "-- %s in %s --\nAssertion failed on line %s: %s\n\n",
            $test_info->callable_info,
            $test_info->path_info->rel_path,
            $test_info->result['stdout']['trace']['failure_point'],
            color($test_info->result['stdout']['message'], 'yellow'),
          );
          foreach ($test_info->code as $lineno => $line) {
            if ($test_info->result['stdout']['trace']['failure_point'] === $lineno) {
              echo "> ", color($line, 'yellow');
            } else {
              echo "  $line";
            }
          }
        } else {
          printf(
            "-- %s in %s --\nAssertion failed: %s\n",
            $test_info->callable_info,
            $test_info->path_info->rel_path,
            color($test_info->result['stdout']['message'], 'yellow'),
          );
        }
        echo "\n";
      }
    }

    function report_summary_error(TestInfo $test_info): void {
      if (VERBOSITY_SUPPRESS_ALL < $this->verbosity) {
        printf(
          "-- %s in %s --\n",
          $test_info->callable_info,
          $test_info->path_info->rel_path,
        );
        echo color($test_info->result['stderr'], 'red'), "\n";
      }
    }

    function report_summary_skipped(TestInfo $test_info): void {}

    function report_summary_xfailed(TestInfo $test_info): void {}

    function report_summary_xpassed(TestInfo $test_info): void {
      if (VERBOSITY_SUPPRESS_FAILURES < $this->verbosity) {
        printf(
          "%s:%s -- %s --\n\n",
          $test_info->path_info->rel_path,
          $test_info->callable_info,
          strtoupper($test_info->status),
        );
      }
    }

    function report_summary_all() {
      $types = [];
      $maybe_add = function (
        int $count, string $name, ?string $plural_name = null
      ) use (&$types) {
        if ($count > 0) {
          $types[] = "{$count} " . (
            empty($plural_name) || $count === 1 ? $name : $plural_name
          );
        }
      };
      $maybe_add($this->counts[TEST_STATUS_OK], 'success', 'successes');
      $maybe_add($this->counts[TEST_STATUS_FAILED], 'failure', 'failures');
      $maybe_add($this->counts[TEST_STATUS_ERROR], 'error', 'errors');
      $maybe_add($this->counts[TEST_STATUS_SKIPPED], 'skipped');
      $maybe_add($this->counts[TEST_STATUS_XFAILED], 'expected to fail');
      $maybe_add($this->counts[TEST_STATUS_XPASSED], 'unexpectedly passing');
      echo (
        implode(", ", $types)
        . " out of {$this->test_cases_found} tests found.\n"
      );
    }

  }
}
