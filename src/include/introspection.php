<?php

namespace QuickTest {
  function unqualified_name_is($name, $match) {
    return ($name === $match || str_ends_with($name, "\\$match"));
  }
}
