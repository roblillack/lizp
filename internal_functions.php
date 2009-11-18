<?php
function liphp_internal_fn_append($env, $args) {
    foreach ($args as $i => $arg) {
        if (!is_array($arg)) {
            if ($arg === NULL || $arg === FALSE) {
                $args[$i] = array();
            } else {
                throw new Exception("syntax: (APPEND <list>*)");
            }
        }
    }
    $r = @call_user_func_array('array_merge', $args);
    return empty($r) ? NULL : $r;
}

function liphp_internal_fn__atom($env, $args) {
    foreach ($args as $i) {
        if (is_array($i) && count($i) > 0) {
            return NULL;
        }
    }
    return TRUE;
}

function liphp_internal_fn_list($env, $args) {
    return $args;
}

function liphp_internal_fn__list($env, $args) {
    foreach ($args as $i) {
        if (!(is_array($i) && count($i) > 0)) {
            return NULL;
        }
    }
    return TRUE;
}

function liphp_internal_fn_exit($env, $args) {
    if (($r = @$args[0]) !== NULL && is_int($r)) {
        exit($r);
    }

    exit(0);
}

function liphp_internal_fn_first($env, $args) {
    if (count($args) !== 1 ||
        !(is_array($args[0]) || $args[0] === NULL || $args[0] === FALSE)) {
        throw new Exception("syntax: (FIRST <list>)");
    }

    return @$args[0][0];
}

function liphp_internal_fn_last($env, $args) {
    if (count($args) !== 1 ||
        !(is_array($args[0]) || $args[0] === NULL || $args[0] === FALSE)) {
        throw new Exception("syntax: (LAST <list>)");
    }

    if (!is_array($args[0]) || ($c = count($args[0])) == 0) {
        return NULL;
    }

    return $args[0][$c - 1];
}

function liphp_internal_fn_p($env, $args) {
    $arg = NULL;
    foreach ($args as $arg) {
        echo Expression::Render($arg) . "\n";
    }
    return $arg;
}

function liphp_internal_fn_print($env, $args) {
    foreach ($args as $arg) {
        if (is_string($arg) || is_int($arg)) {
            echo $arg;
        }
    }
    return NULL;
}

function liphp_internal_fn_println($env, $args) {
    foreach ($args as $arg) {
        if (is_string($arg) || is_int($arg)) {
            echo $arg;
        }
    }
    echo "\n";
    return NULL;
}

function liphp_internal_fn_sum($env, $args) {
    $sum = 0;

    foreach ($args as $arg) {
        if (is_int($arg) || is_double($arg)) {
            $sum += $arg;
        }
    }

    return $sum;
}

function liphp_internal_fn__multiply($env, $args) {
    $res = 1;
    foreach ($args as $arg) {
        if (is_int($arg) || is_double($arg)) {
            $res *= $arg;
        }
    }
    return $res;
}

function liphp_internal_fn__divide($env, $args) {
    $res = NULL;
    foreach ($args as $arg) {
        if (is_int($arg) || is_double($arg)) {
            if ($res === NULL) {
                $res = $arg;
            } else {
                $res /= $arg;
            }
        }
    }
    return $res === NULL ? 1 : $res;
}

function liphp_internal_fn__sub($env, $args) {
    $sum = NULL;
    foreach ($args as $arg) {
        if (is_int($arg) || is_double($arg)) {
            if ($sum === NULL) {
                $sum = $arg;
            } else {
                $sum -= $arg;
            }
        }
    }
    return $sum;
}


function liphp_internal_fn_length($env, $args) {
    if (count($args) !== 1 ||
        !(is_array($args[0]) || $args[0] === NULL || $args[0] === FALSE)) {
        throw new Exception("syntax: (LENGTH <list>)");
    }

    return is_array($args[0]) ? count($args[0]) : 0;
}

function liphp_internal_fn_eval($env, $args) {
    if (count($args) != 1 || !is_string(@$args[0])) {
        throw new Exception("syntax (EVAL <str>)");
    }

    $expr = Expression::Parse($args[0]);
    if (count($expr) > 1) {
        throw new Exception("Error: Multiple expressions given in " .
                            Expression::Render($arg[0]));
    }

    return $env->Evaluate(@$expr[0]);
}

function liphp_internal_fn_parse($env, $args) {
    if (!is_string(@$args[0])) {
        throw new Exception("Syntax Error: (PARSE <string>)");
    }

    $expr = Expression::NewParse($args[0]);
    if (count($expr) > 1) {
        throw new Exception("Error: Multiple expressions given in " .
                            Expression::Render($arg[0]));
    }

    return @$expr[0];
}

function liphp_internal_fn_map($env, $args) {
    if (count($args) !== 2 ||
        !($args[0] instanceof Lambda || $args[0] instanceof Symbol) ||
        (!is_array($args[1]) && $args[1] !== NULL)) {
        throw new Exception("syntax: (MAP <lambda> <list>)");
    }

    $r = array();
    foreach ((array) $args[1] as $i) {
        $r []= $env->Evaluate(array($args[0], $i));
    }

    return empty($r) ? NULL : $r;
}

function liphp_internal_fn_not($env, $args) {
    foreach ($args as $i) {
        if ((is_array($i) && count($i) > 0) ||
            (!is_array($i) && $i !== NULL && $i !== FALSE)) {
            return NULL;
        }
    }
    return TRUE;
}

function liphp_internal_fn_reduce($env, $args) {
    if (count($args) < 2 || count($args) > 3 ||
        !($args[0] instanceof Lambda || $args[0] instanceof Symbol) ||
        (!is_array($args[1]) && $args[1] !== NULL)) {
        throw new Exception("syntax: (REDUCE <lambda> <list> [<expr>])");
    }

    $r = isset($args[2]) ? $args[2] : FALSE;
    foreach ((array) $args[1] as $i) {
        if ($r === FALSE) {
            $r = $i;
            continue;
        }
        $r = $env->Evaluate(array($args[0], $r, $i));
    }

    return $r;
}
