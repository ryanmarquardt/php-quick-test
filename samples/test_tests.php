<?php

function test_empty () {
}

function not_tested() {
  throw new \Exception("not_tested was called");
}

class TestClass {

  function test_empty () {
  }

  static function test_static_empty () {
  }

  function not_tested() {
    throw new \Exception("TestClass->not_tested was called");
  }

  function test_method_should_fail() {
    throw new \AssertionError("failure succeeded");
  }

  static function test_static_method_should_fail() {
    throw new \AssertionError("failure succeeded");
  }
}


function test_should_fail() {
  throw new \AssertionError("failure succeeded");
}

function test_should_error() {
  throw new \Exception("error succeeded");
}


#[skip]
function test_skip() {
  throw new \Exception("test not skipped");
}

#[xfail]
function test_expect_failure() {
  throw new \AssertionError("Expected");
}

#[xfail]
function test_unexpected_pass() {
}


#[fixture]
function my_fixture() {
  return 'value';
}

// function test_fixture($my_fixture) {
//   if ($my_fixture !== 'value') {
//     throw new \AssertionError('$my_fixture !== "value"');
//   }
// }
