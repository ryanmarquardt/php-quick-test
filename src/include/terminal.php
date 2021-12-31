<?php

namespace QuickTest {

  /*
   * Format a string for colorful output to the terminal.
   */
  function color(string $text, ?string $color = null, ?string $bg_color = null): string {
    static $COLORS = [
      'black' => '0',
      'red' => '1',
      'green' => '2',
      'yellow' => '3',
      'blue' => '4',
      'magenta' => '5',
      'cyan' => '6',
      'white' => '7',
    ];
    $spec = implode(
      ';',
      array_filter(
        [
          array_key_exists($color, $COLORS) ? "3{$COLORS[$color]}" : null,
          array_key_exists($bg_color, $COLORS) ? "4{$COLORS[$bg_color]}" : null,
        ]
      )
    );
    return "\e[{$spec}m{$text}\e[0m";
  }

}
