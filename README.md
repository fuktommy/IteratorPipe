IteratorPipe
============

PHP library to use iterators and arrays with method chain.

Sample
------
    $pipe = IteratorPipe::factory(array(11, 12, 13, 14))
          ->filter(function($k, $v) {return $v % 2 === 0; })
          ->map(function($k, $v) {return $v / 2; });
    $pipe->toArray(); // array(1 => 6, 3 => 7)
