<?php

namespace QuickTest {
  function with_context($context, $block) {
    $context->with_me($block);
  }

  class Context {
    function __construct($manager) {
      $this->manager = $manager;
    }

    static function from_setup_teardown($setup = null, $teardown = null) {
      return new self(function (&$error) use ($setup, $teardown) {
        if ($setup !== null) {
          $setup();
        }
        yield;
        if ($teardown !== null) {
          if (!$teardown($error)) {
            throw $error;
          }
        }
      });
    }

    function with_me($block) {
      $index = 0;
      $error = null;
      $caught = null;
      foreach ($this->manager() as $yielded) {
        if ($index === 0) {
          // Setup has been called and $yielded contains value to pass to the block
          $index = 1;
          try {
            $block($yielded);
          } catch (\Throwable $thrown) {
            // Suppress the error until teardown code can be run
            $error = $thrown;
          }
        } else {
          // Teardown has been called, but the loop yielded again.
          throw new \Exception("Manager yielded twice");
        }
      }
      if ($index === 0) {
        // The loop was never entered, which is required for the block to be called.
        throw new \Exception("Manager did not yield to the block");
      }
    }
  }
}
