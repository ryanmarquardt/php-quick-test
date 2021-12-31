<?php

namespace QuickTest {

  define('PIPE_OUTPUT_BINARY', ['pipe', 'wb']);
  define('PIPE_INPUT_BINARY', ['pipe', 'rb']);


  function read_all_from_pipe($pipe): string|null {
    if (!is_resource($pipe)) {
      throw new TypeError(
        '$pipe must be of type resource, ' . gettype($pipe) . ' given'
      );
    }

    $result = '';

    do {
      $chunk = fread($pipe, 1024);
      $result .= $chunk;
    } while (strlen($chunk) > 0);

    fclose($pipe);
    return $result;
  }


  function run_subprocess(array $argv, array $env = []): array {
    $pipe_handles = [];

    $process = proc_open(
      $argv,
      [  // $descriptorspec
        1 => PIPE_OUTPUT_BINARY,
        2 => PIPE_OUTPUT_BINARY,
      ],
      $pipe_handles,
      '.',  // $cwd
      $env,
      [
        'bypass_shell' => true,
      ],
    );

    if (!is_resource($process)) {
      throw new \Exception(sprintf("proc_open returned non-resource %s", $process));
    }

    $stdout = (
      isset($pipe_handles[1])
      ? unserialize(read_all_from_pipe($pipe_handles[1]))
      : null
    );
    $stderr = (
      isset($pipe_handles[2])
      ? read_all_from_pipe($pipe_handles[2])
      : null
    );

    $exit_code = proc_close($process);

    return [
      'exit_code' => $exit_code,
      'stdout' => $stdout,
      'stderr' => $stderr,
    ];
  }

}
