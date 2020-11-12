<pre>
<?php 
$config = ['foo' => '', 'baz' => 99, 'zero' => 0];

var_dump('foo ?: ' . ($config['foo'] ?: 'default_foo'));
//var_dump('bar ?: ' . ($config['bar'] ?: 'default_bar')); // Error
var_dump('baz ?: ' . ($config['baz'] ?: 'default_baz'));
var_dump('zero ?: ' . ($config['zero'] ?: 'default_zero'));

var_dump('foo ?? ' . ($config['foo'] ?? 'default_foo'));
var_dump('bar ?? ' . ($config['bar'] ?? 'default_bar'));
var_dump('baz ?? ' . ($config['baz'] ?? 'default_baz'));
var_dump('zero ?? ' . ($config['zero'] ?? 'default_zero'));
?>
</pre>

    