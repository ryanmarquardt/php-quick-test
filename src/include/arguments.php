<?php


namespace QuickTest {

  class Option {
    public array $attributes;
    public array $parameters;

    function __construct(
      public string $key,
      public $process,
    ) {
      if (!is_callable($process)) {
        throw new \TypeError('$process must be callable');
      }

      $signature = new \ReflectionFunction($process);

      foreach ($signature->getAttributes() as $attribute) {
        $parts = explode('\\', $attribute->getName());
        $unqualified_name = array_pop($parts);

        $this->attributes[$unqualified_name] = $attribute->getArguments();
      }

      $this->parameters = [];

      foreach ($signature->getParameters() as $parameter) {
        $this->parameters[] = $parameter;
      }
    }
  }

  class Options {
    public ?string $script_name;

    public function __construct(
      public array $matches,
    ) {
      $this->script_name = null;

      foreach ($this->matches as $key => &$match) {
        if (!($match instanceof Option)) {
          $match = new Option($key, $match);
        }
      }

      foreach ($this->matches as $key => &$match) {
        if (!($match instanceof Option)) {
          throw new \Exception();
        }
      }
    }

    public function parse_argv(array $argv): array {
      $this->script_name = array_shift($argv);

      $processing_opts = true;
      $posargs = [];

      while ($argv) {
        $opt =& $argv[0];
        $short_opt = substr($argv[0], 0, 2);
        var_export(['$argv' => $argv, '$posargs' => $posargs]);

        if ($processing_opts && str_starts_with($opt, '-') && $opt !== '-') {
          // Treat this argument as an option

          if ($short_opt === '-h' || $opt === '--help') {
            $this->show_help();
            exit(2);

          } elseif ($opt === '--') {
            $processing_opts = false;
            array_shift($argv);

          } elseif (array_key_exists($short_opt, $this->matches)) {
            ($this->matches[$short_opt]->process)();

            if (strlen($opt) > 2) {
              $opt = '-' . substr($opt, 2);
            } else {
              array_shift($argv);
            }

          } else {
            $this->show_usage();
            exit(2);

          }
        } else {
          // Treat this argument as a positional argument
          $posargs[] = array_shift($argv);

        }
      }
      return $posargs;
    }

    function show_usage() {
      $parts = [];
      $posargs = [];
      foreach ($this->matches as $key => $match) {
        if (str_starts_with($key, '--')) {
          $part = "{$key}";
        } elseif (str_starts_with($key, '-')) {
          $part = "{$key}";
        }
        foreach ($match->parameters as $parameter) {
          $part = "{$part} {$parameter->getName()}";
        }
        $parts[] = "[{$part}]";
      }
      $args = implode(' ', $parts);
      echo "Usage: {$this->script_name} {$args}\n";
    }

    function show_help() {
      $this->show_usage();
      echo "\n";

      foreach ($this->matches as $key => $match) {
        if (isset($match->attributes['help'])) {
          echo "  {$key} {$match->attributes['help'][0]}\n";
        } else {
          echo "  {$key}\n";
        }
      }
?>

<?php
    }
  }

  function parse_argv($argv, $matches) {
    (new Options($matches))->parse_argv($argv);
  }

}
