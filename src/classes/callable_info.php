<?php

namespace QuickTest {

  define('CALL_TYPE_FUNCTION', 'function');
  define('CALL_TYPE_METHOD', 'method');
  define('CALL_TYPE_STATIC', 'static');

  class CallableInfo {
    public $call;

    private $_signature;
    private $_skip;
    private $_xfail;
    private $_fixture;
    private $_test;

    function __construct($path, $call, $signature) {
      $this->path = $path;
      $this->call = $call;
      $this->_skip = false;
      $this->_xfail = false;
      $this->_fixture = false;
      $this->_test = false;
      $this->_signature = $signature;

      if (
        (
          // Methods like TestXXX->testXXX are valid tests
          is_array($call)
          && str_starts_with($call[0], 'Test')
          && str_starts_with($call[1], 'test')
        )
        || (
          // Functions like testXXX are valid tests
          is_string($call)
          && str_starts_with($call, 'test')
        )
      ) {
        $this->_test = true;
      }

      if ($signature instanceof \ReflectionFunction) {
        $this->type = CALL_TYPE_FUNCTION;
      } elseif ($signature->isStatic()) {
        $this->type = CALL_TYPE_STATIC;
      } else {
        $this->type = CALL_TYPE_METHOD;
      }

      foreach ($signature->getAttributes() as $attribute) {
        $attribute_name = $attribute->getName();
        if (unqualified_name_is($attribute_name, 'skip')) {
          $this->_skip = true;
        } elseif (unqualified_name_is($attribute_name, 'xfail')) {
          $this->_xfail = true;
        } elseif (unqualified_name_is($attribute_name, 'fixture')) {
          $this->_fixture = $attribute;
        }
      }

    }

    static function from_function($path, $function_name) {
      return new self(
        $path,
        $function_name,
        new \ReflectionFunction($function_name),
      );
    }

    static function from_method($path, $class_name, $method_name) {
      $class_signature = new \ReflectionClass($class_name);
      $signature = $class_signature->getMethod($method_name);
      return new self($path, [$class_name, $method_name], $signature);
    }

    function __toString() {
      switch ($this->type) {
        case CALL_TYPE_FUNCTION:
          return $this->call;
        case CALL_TYPE_METHOD:
          return "{$this->call[0]}->{$this->call[1]}";
        case CALL_TYPE_STATIC:
          return "{$this->call[0]}::{$this->call[1]}";
      }
      throw new \TypeError("Unrecognized type: {$this->type}");
    }

    function is_fixture() {
      return $this->_fixture;
    }

    function is_test() {
      return $this->_test;
    }

    function is_skipped() {
      return $this->_skip;
    }

    function is_xfail() {
      return $this->_xfail;
    }

    function argv(): array {
      return (
        !is_array($this->call) || count($this->call) !== 2
        ? ['', ((array) $this->call)[0]]
        : $this->call
      );
    }

    function get_signature() {
      return $this->_signature;
    }

    function get_start_line(): int|false {
      return $this->_signature->getStartLine();
    }

    function get_end_line(): int|false {
      return $this->_signature->getEndLine();
    }
  }
}
