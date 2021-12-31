<?php

function require_class($name) {
  require_once QUICKTEST_PATH . "classes/{$name}.php";
}


function require_lib($name) {
  require_once QUICKTEST_PATH . "include/{$name}.php";
}
