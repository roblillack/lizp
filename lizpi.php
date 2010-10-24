<?php
require_once(dirname(__FILE__) . '/lizp.php');

if ($_SERVER['argc'] < 2) {
    echo "syntax: {$_SERVER['argv'][0]} FILE\n";
    exit(1);
}

$cmd = @$_SERVER['argv'][1];
if (is_readable($cmd)) {
    $start = microtime(TRUE);
    $input = file_get_contents($cmd);
    $expressions = Expression::Parse($input);

    $env = new Lizp();
    foreach ($expressions as $expr) {
        $env->Evaluate($expr);
    }
}