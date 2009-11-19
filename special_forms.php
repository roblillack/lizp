<?php
function liphp_special_form__eq($env, $args) {
    $last = FALSE;

    foreach ($args as $arg) {
        $ev = $env->Evaluate($arg);
        if ($last === FALSE) {
            $last = $ev;
            continue;
        }
        if ($last !== $ev) {
            return NULL;
        }
    }
    return TRUE;
}

function liphp_special_form__lt($env, $args) {
    $last = FALSE;

    foreach ($args as $arg) {
        $ev = $env->Evaluate($arg);
        if (!is_int($ev) && !is_double($ev)) {
            throw new Exception("syntax: (< <int|double>*)");
        }
        if ($last === FALSE) {
            $last = $ev;
            continue;
        }
        if ($last >= $ev) {
            return NULL;
        }
        $last = $ev;
    }
    return TRUE;
}

function liphp_special_form__gt($env, $args) {
    $last = FALSE;

    foreach ($args as $arg) {
        $ev = $env->Evaluate($arg);
        if (!is_int($ev) && !is_double($ev)) {
            throw new Exception("syntax: (> <int|double>*)");
        }
        if ($last === FALSE) {
            $last = $ev;
            continue;
        }
        if ($last <= $ev) {
            return NULL;
        }
        $last = $ev;
    }
    return TRUE;
}

function liphp_special_form_and($env, $args) {
    foreach ($args as $arg) {
        if ($env->Evaluate($arg) === NULL) {
            return NULL;
        }
    }
    return TRUE;
}

function liphp_special_form_if($env, $args) {
    return $env->Evaluate(@$args[0]) === NULL ?
        $env->Evaluate(@$args[2]) :
        $env->Evaluate(@$args[1]);
}

function liphp_special_form_unless($env, $args) {
    $r = NULL;

    if (($r = $env->Evaluate($args[0])) !== NULL) {
        return $r;
    }

    foreach (array_slice($args, 1) as $e) {
        $r = $env->Evaluate($e);
    }

    return $r;
}

function liphp_special_form_when($env, $args) {
    $r = NULL;

    if ($env->Evaluate($args[0]) === NULL) {
        return $r;
    }

    foreach (array_slice($args, 1) as $e) {
        $r = $env->Evaluate($e);
    }

    return $r;
}

function liphp_special_form_while($env, $args) {
    $r = NULL;

    while ($env->Evaluate($args[0]) !== NULL) {
        foreach (array_slice($args, 1) as $e) {
            $r = $env->Evaluate($e);
        }
    }

    return $r;
}

function liphp_special_form_define($env, $args) {
    if (count($args) != 2) {
        throw new Exception("(DEFINE) needs two parameters!");
    }
    if (!($args[0] instanceof Symbol)) {
        throw new Exception("Syntax Error: (DEFINE <id> <expr>)");
    }

    $r = $env->Evaluate($args[1]);
    return $env->environment[$args[0]->name] = ($r === NULL ? FALSE : $r);
}

function liphp_special_form_defun($env, $args) {
    if (!(@$args[0] instanceof Symbol) ||
        !(is_array(@$args[1]) || @$args[1] === NULL || @$args[1] === FALSE)) {
        throw new Exception("Syntax Error: (DEFUN <id> (<params>*) <expr>*)");
    }

    $lambda = new Lambda;
    $lambda->arguments = empty($args[1]) ? NULL : $args[1];
    $lambda->expressions = array_slice($args, 2);

    $env->environment[$args[0]->name] = $lambda;

    return Symbol::Make($args[0]->name);
}

function liphp_special_form_defmacro($env, $args) {
    if (!(@$args[0] instanceof Symbol) ||
        !(is_array(@$args[1]) || @$args[1] === NULL || @$args[1] === FALSE)) {
        throw new Exception("Syntax Error: (DEFMACRO <id> (<params>*) <expr>*)");
    }

    $lambda = new Macro;
    $lambda->arguments = empty($args[1]) ? NULL : $args[1];
    $lambda->expressions = array_slice($args, 2);
    $env->environment[$args[0]->name] = $lambda;
    return Symbol::Make($args[0]->name);
}

function liphp_special_form_do($env, $args) {
    $r = NULL;

    foreach ($args as $e) {
        $r = $env->Evaluate($e);
    }

    return $r;
}

function liphp_special_form_let($env, $args) {
    if (!(is_array(@$args[0]) || @$args[0] === NULL || @$args[0] === FALSE)) {
        throw new Exception("Syntax Error: (LET (<values>*) <expr>*)");
    }

    $lambda = new Lambda;
    $lambda->arguments = array();
    $expressions = array($lambda);

    foreach ($args[0] as $keyval) {
        if (!is_array($keyval) || !($keyval[0] instanceof Symbol)) {
            trigger_error("Ignoring " . Expression::Render($keyval) . " in (let) directive");
        }
        $lambda->arguments []= $keyval[0];
        $expressions []= $keyval[1];
    }

    $lambda->expressions = array_slice($args, 1);

    return $env->Evaluate($expressions);
}

function liphp_special_form_lambda($env, $args) {
    if (!(is_array(@$args[0]) || @$args[0] === NULL || @$args[0] === FALSE)) {
        throw new Exception("Syntax Error: (LAMBDA (<params>*) <expr>*)");
    }

    $lambda = new Lambda;
    $lambda->arguments = empty($args[0]) ? NULL : $args[0];
    $lambda->expressions = array_slice($args, 1);

    return $lambda;
}

function liphp_special_form_quote($env, $args) {
    if (count($args) != 1) {
        throw new Exception("Syntax Error: (QUOTE <expr>)");
    }

    return $args[0];
}

function liphp_special_form_quasiquote($env, $args) {
    //echo "qq-IN: " . Expression::Render($args) . "\n";

    $nextUnquoted = FALSE;
    $expandNext = FALSE;
    $r = array();

    foreach ($args as $i) {
        if ($i instanceof AtSign) {
            $expandNext = TRUE;
            continue;
        }
        if ($i instanceof Tilde) {
            $nextUnquoted = TRUE;
            continue;
        }

        if ($nextUnquoted) {
            $i = $env->Evaluate($i);
        } elseif (is_array($i)) {
            $i = liphp_special_form_quasiquote($env, $i);
        }

        if ($expandNext && is_array($i)) {
            $r = array_merge($r, $i);
        } else {
            $r []= $i;
        }

        $nextUnquoted = FALSE;
        $expandNext = FALSE;
    }

    //echo "qq-OUT: " . Expression::Render($r) . "\n";
    return $r;
}

function liphp_special_form_dump($env, $args) {
    foreach ($args as $arg) {
        echo Expression::Render($arg) . "\n";
    }
    return NULL;
}
