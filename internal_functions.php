<?php
function lizp_internal_fn_append($env, $args) {
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

function lizp_internal_fn__atom($env, $args) {
    foreach ($args as $i) {
        if (is_array($i) && count($i) > 0) {
            return NULL;
        }
    }
    return TRUE;
}

function lizp_internal_fn_list($env, $args) {
    return $args;
}

function lizp_internal_fn__list($env, $args) {
    foreach ($args as $i) {
        if (!(is_array($i) && count($i) > 0)) {
            return NULL;
        }
    }
    return TRUE;
}

function lizp_internal_fn_exit($env, $args) {
    if (($r = @$args[0]) !== NULL && is_int($r)) {
        exit($r);
    }

    exit(0);
}

function lizp_internal_fn_first($env, $args) {
    if (count($args) !== 1 ||
        !(is_array($args[0]) || $args[0] === NULL || $args[0] === FALSE)) {
        throw new Exception("syntax: (FIRST <list>)");
    }

    return @$args[0][0];
}

function lizp_internal_fn_last($env, $args) {
    if (count($args) !== 1 ||
        !(is_array($args[0]) || $args[0] === NULL || $args[0] === FALSE)) {
        throw new Exception("syntax: (LAST <list>)");
    }

    if (!is_array($args[0]) || ($c = count($args[0])) == 0) {
        return NULL;
    }

    return $args[0][$c - 1];
}

function lizp_internal_fn_rest($env, $args) {
    if (count($args) !== 1 ||
        !(is_array($args[0]) || $args[0] === NULL || $args[0] === FALSE)) {
        throw new Exception("syntax: (REST <list>)");
    }

    if (!is_array($args[0]) || ($c = count($args[0])) < 2) {
        return NULL;
    }

    return array_slice($args[0], 1);
}

function lizp_internal_fn_p($env, $args) {
    $arg = NULL;
    foreach ($args as $arg) {
        echo Expression::Render($arg) . "\n";
    }
    return $arg;
}

function lizp_internal_fn_print($env, $args) {
    foreach ($args as $arg) {
        if (is_string($arg) || is_int($arg)) {
            echo $arg;
        }
    }
    return NULL;
}

function lizp_internal_fn_println($env, $args) {
    foreach ($args as $arg) {
        if (is_string($arg) || is_int($arg)) {
            echo $arg;
        }
    }
    echo "\n";
    return NULL;
}

function lizp_internal_fn_sum($env, $args) {
    $sum = 0;

    foreach ($args as $arg) {
        if (is_int($arg) || is_double($arg)) {
            $sum += $arg;
        }
    }

    return $sum;
}

function lizp_internal_fn__multiply($env, $args) {
    $res = 1;
    foreach ($args as $arg) {
        if (is_int($arg) || is_double($arg)) {
            $res *= $arg;
        }
    }
    return $res;
}

function lizp_internal_fn__divide($env, $args) {
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

function lizp_internal_fn__sub($env, $args) {
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


function lizp_internal_fn_length($env, $args) {
    if (count($args) !== 1 ||
        !(is_array($args[0]) || $args[0] === NULL || $args[0] === FALSE)) {
        throw new Exception("syntax: (LENGTH <list>)");
    }

    return is_array($args[0]) ? count($args[0]) : 0;
}

function lizp_internal_fn_eval($env, $args) {
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

function lizp_internal_fn_parse($env, $args) {
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

function lizp_internal_fn_make_list($env, $args) {
    $r = array();
    $size = (int)@$args[0];

    if (@$args[1] instanceof Lambda ||
        @$args[1] instanceof Symbol) {
        for ($i = 0; $i < $size; $i++) {
            $r []= $env->Evaluate(array($args[1], $i));
        }
    } else {
        while ($size--) {
            $r []= NULL;
        }
    }

    return empty($r) ? NULL : $r;
}

function lizp_internal_fn_map($env, $args) {
    if (count($args) !== 2 ||
        !($args[0] instanceof Lambda || $args[0] instanceof Symbol) ||
        (!is_array($args[1]) && $args[1] !== NULL)) {
        throw new Exception("syntax: (MAP <lambda> <list>)");
    }

    $r = array();
    foreach ((array) $args[1] as $i => $v) {
        $r []= $env->Evaluate(array($args[0],
                array(Symbol::Make('quote'), $v),
                array(Symbol::Make('quote'), $i)));
    }

    return empty($r) ? NULL : $r;
}

function lizp_internal_fn_not($env, $args) {
    foreach ($args as $i) {
        if ((is_array($i) && count($i) > 0) ||
            (!is_array($i) && $i !== NULL && $i !== FALSE)) {
            return NULL;
        }
    }
    return TRUE;
}

function lizp_internal_fn_nth($env, $args) {
    if (count($args) !== 2 ||
        !is_int($args[0]) ||
        (!is_array($args[1]) && $args[1] !== NULL)) {
        throw new Exception("syntax: (NTH <pos> <list>)");
    }

    $r = @$args[1][$args[0]];
    return ($r === FALSE || (is_array($r) && empty($r))) ? NULL : $r;
}

function lizp_internal_fn_reduce($env, $args) {
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

function lizp_internal_fn_str($env, $args) {
    $out = array();
    foreach ($args as $i) {
        if ($i instanceof Symbol) {
            $out []= $i->name;
        } elseif ($i instanceof Macro ||
                  $i instanceof Lambda) {
            continue;
        } else {
            $out []= (string) $i;
        }
    }

    return implode('', $out);
}

function lizp_internal_fn_symbol_name($env, $args) {
    if (count($args) != 1) {
        throw new Exception("Syntax Error: (SYMBOL-NAME <symbol>)");
    }

    if (!($args[0] instanceof Symbol)) {
        return NULL;
    }

    return $args[0]->name;
}
