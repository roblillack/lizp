<?php
@dl('lizp.so');

require_once(dirname(__FILE__) . '/internal_functions.php');
require_once(dirname(__FILE__) . '/special_forms.php');

class Lizp {
    public $environment = array();

    public function Evaluate($sexp) {
        //echo "EVAL: " . Expression::Render($sexp) . "\n";
        if ($sexp instanceof Symbol) {
            if (($r = @$this->environment[$sexp->name]) !== NULL) {
                return $r === FALSE ? NULL : $r;
            }
            var_dump($sexp);
            throw new Exception("Unknown identifier: " . $sexp->name);
        } elseif (!is_array($sexp)) {
            return $sexp;
        }

        if (empty($sexp)) {
            return NULL;
        }

        $lambda = $sexp[0];
        $args = array_slice($sexp, 1);

        if (is_array($lambda)) {
            $evald = $this->Evaluate($lambda);
            //echo Expression::Render($lambda) . " ~~> " . Expression::Render($evald) . "\n";
            $lambda = $evald;
        }

        if ($lambda instanceof Symbol &&
            ($val = @$this->environment[$lambda->name]) !== NULL) {
            $lambda = $val;
        }

        $phpName = NULL;
        if ($lambda instanceof Symbol) {
            $phpName = ($in = @self::$_internalNames[$lambda->name]) === NULL ?
                       str_replace('-', '_', $lambda->name) :
                       $in;
        }


        if ($lambda instanceof Macro) {
            $list = NULL;
            foreach ($lambda->expressions as $e) {
                // macro-expansion-time
                $applied = $lambda->arguments === NULL ? $e : lizp_apply($e, $lambda->arguments, $args);
                //echo Expression::Render($e) . " =APPLY=> " . Expression::Render($applied) . "\n";
                $expanded = $this->Evaluate($applied);
                //echo Expression::Render($applied) . " =MACROEXPAND=> " . Expression::Render($expanded) . "\n";
            }
            return $this->Evaluate($expanded);
        }

        if ($lambda instanceof Symbol) {
            // special forms (non-evaluated parameters)
            $specialForm = "lizp_special_form_{$phpName}";
            if (function_exists($specialForm)) {
                return call_user_func($specialForm, $this, $args);
            }
        }

        // We evaluate the parameters
        $params = array();
        $expandNext = FALSE;
        foreach ($args as $v) {
            if ($v instanceof AtSign) {
                $expandNext = TRUE;
                continue;
            }
            $r = $this->Evaluate($v);
            if ($expandNext && is_array($r)) {
                $params = array_merge($params, $r);
            } else {
                $params []= $r;
            }
            $expandNext = FALSE;
        }

        if ($lambda instanceof Lambda) {
            $r = NULL;
            foreach ($lambda->expressions as $e) {
                $args = array();
                foreach ($params as $i) {
                    if (is_array($i)) {
                        $i = array(new Symbol('quote'), $i);
                    }
                    $args []= $i;
                }
                //$params = $args;
                $applied = $lambda->arguments === NULL ? $e : lizp_apply($e, $lambda->arguments, $params);
                //echo Expression::Render($e) . " -APPLY-> " . Expression::Render($applied) . "\n";
                $r = $this->Evaluate($applied);
                //echo Expression::Render($applied) . " -EVAL-> " . Expression::Render($r) . "\n";
            }
            return $r;
        }

        if ($lambda instanceof Symbol) {
            // internal functions / php functions
            $internalFn = "lizp_internal_fn_{$phpName}";
            if (($isInternal = function_exists($internalFn)) ||
                function_exists($phpName)) {

                if ($isInternal) {
                    return call_user_func($internalFn, $this, $params);
                }

                $r = call_user_func_array($phpName, $params);
                if ($r === FALSE || (is_array($r) && empty($r))) {
                    $r = NULL;
                }
                return $r;
            }
        }

        throw new Exception("Unable to evaluate Expression: " . Expression::Render($sexp) .
                            " because function name evaluates to " . Expression::Render($lambda));
    }

    // Some Lizp functions need to get
    // mapped to more PHP-like internal names
    private static $_internalNames = array(
        '+'     => 'sum',
        '-'     => '_sub',
        '*'     => '_multiply',
        '/'     => '_divide',
        '<'     => '_lt',
        '>'     => '_gt',
        'atom?' => '_atom',
        'eq?'   => '_eq',
        'list?' => '_list',
        'nil?'  => 'not');
}

class Expression {

    public static function Render($expr) {
        if ($expr === NULL || $expr === FALSE ||
            (is_array($expr) && empty($expr))) {
            return 'nil';
        }

        if ($expr === TRUE) {
            return 't';
        }

        if (is_int($expr) || is_double($expr)) {
            return (string) $expr;
        }

        if (is_string($expr)) {
            return '"' . addslashes($expr) . '"';
        }

        if (is_array($expr)) {
            $out = array();
            $space = FALSE;
            foreach ($expr as $e) {
                if ($space) $out []= ' ';
                $out []= self::Render($e);
                $space = !($e instanceof Tilde || $e instanceof AtSign);
            }
            return '(' .  implode('', $out) . ')';
        }

        if ($expr instanceof Tilde) {
            return '~';
        }

        if ($expr instanceof AtSign) {
            return '@';
        }

        if ($expr instanceof Symbol) {
            return $expr->name;
        }

        if ($expr instanceof Lambda) {
            return '<lambda:' . self::Render($expr->arguments) . '>';
        }

        if ($expr instanceof Macro) {
            return '<macro:' . self::Render($expr->arguments) . '>';
        }

        return '<' . get_class($expr) . '>';
    }

    public static function Parse(&$s, &$position = NULL) {
        if ($position === NULL) {
            $pos = $position = 0;
        } else {
            $pos = $position;
        }
        $len = strlen($s);
        $tokens = array();
        $didClose = FALSE;

        $qStack = array();
        $qSymbol = new Symbol('quote');
        $qqSymbol = new Symbol('quasiquote');

        $append = FALSE;

        for (;;) {
            // append any expressions to our list
            if ($append !== FALSE) {
                while (count($qStack) > 0) {
                    $qChar = array_pop($qStack);
                    switch ($qChar) {
                    case "'": $append = array($qSymbol, $append); break;
                    case "`": $append = is_array($append) ?
                                        array_merge(array($qqSymbol), $append) :
                                        array($qSymbol, $append); break;
                    }
                }

                $tokens []= $append;
                $append = FALSE;
            }

            // end reached?
            if ($pos >= $len) {
                break;
            }

            // closing?
            if ($s[$pos] == ')') {
                if ($position === 0) {
                    throw new Exception("Missing (!");
                }
                $didClose = TRUE;
                $pos++;
                break;
            }

            // consume whitespace
            if (preg_match('/\s/', $s[$pos])) {
                $pos++;
                continue;
            }

            // consume comments
            if ($s[$pos] == ';') {
                if (($nl = strpos($s, "\n", $pos)) !== FALSE) {
                    $pos = $nl;
                }
                $pos++;
                continue;
            }

            // open paren
            if ($s[$pos] == '(') {
                $p = $pos+1;
                $append = self::Parse($s, $p);
                $pos = $p;
                continue;
            }

            // quote and quasiquote
            if ($s[$pos] == "`" || $s[$pos] == "'") {
                array_push($qStack, $s[$pos]);
                $pos++;
                continue;
            }

            // tilde
            if ($s[$pos] == "~") {
                $append = new Tilde;
                $pos++;
                continue;
            }

            // at sign
            if ($s[$pos] == "@") {
                $append = new AtSign;
                $pos++;
                continue;
            }

            // from here on i'll need an actual substring,
            // because the regexp functions do not allow matching
            // at a specified offset only. :(
            $sub = substr($s, $pos);

            // consume strings
            if (preg_match('/^"((?:\\\"|[^"])*)"/s', $sub, $m)) {
                $append = stripslashes($m[1]);
                $pos += strlen($m[1]) + 2;
                continue;
            }

            // consume integers
            if (preg_match('/^([+-]?[0-9]+)/s', $sub, $m)) {
                $append = (int) $m[1];
                $pos += strlen($m[1]);
                continue;
            }

            // consume symbols
            if (preg_match('/^([^0-9][^\s\(\)\[\]\{\}]*)/s', $sub, $m)) {
                $sname = $m[1];
                $supper = strtoupper($sname);
                if ($supper == 'T') {
                    $append = TRUE;
                } elseif ($supper == 'NIL') {
                    $append = NULL;
                } else {
                    $symbol = new Symbol($sname);
                    $append = $symbol;
                }

                $pos += strlen($sname);
                continue;
            }

            throw new Exception("Unexpected character at pos {$pos}: {$s[$pos]}");
        }

        if ($position !== 0 && !$didClose) {
            throw new Exception("Missing )!");
        }
        $position = $pos;
        return empty($tokens) ? NULL : $tokens;
    }
}

class Lambda extends Expression {
    public $arguments = NULL;
    public $expressions = array();
}

class Macro extends Expression {
    public $arguments = NULL;
    public $expressions = array();
}

if (!function_exists('lizp_apply')) {

class Symbol extends Expression {
    public $name = NULL;

    public function __construct($n) {
        $this->name = $n;
    }
}

class Tilde extends Expression {}
class AtSign extends Expression {}

function lizp_apply($sexp, $args, $values) {
    if (is_array($sexp)) {
        $exps = array();
        $expandNext = FALSE;
        foreach ($sexp as $v) {
            if ($v instanceof AtSign) {
                $expandNext = TRUE;
                continue;
            }
            $r = lizp_apply($v, $args, $values);
            if ($expandNext && is_array($r) &&
                @$r[0] instanceof Symbol && $r[0]->name == 'quote' &&
                is_array(@$r[1])) {
                $exps = array_merge($exps, $r[1]);
            } else {
                $exps []= $r;
            }
            $expandNext = FALSE;
        }
        return $exps;
    }

    if (!($sexp instanceof Symbol)) {
        return $sexp;
    }

    foreach ($args as $i => $arg) {
        if ($arg instanceof Symbol && $sexp->name == $arg->name) {
            if (@$args[$i-1]->name == '&rest') {
                return array(new Symbol('quote'),
                             array_slice($values, $i - 1));
            }
            return is_array(@$values[$i]) ?
                array(new Symbol('quote'), @$values[$i]) : @$values[$i];
        }
    }

    return $sexp;
}
}

//if ($_SERVER['argc'] < 2) {
//   echo "syntax: {$_SERVER['argv'][0]} FILE\n";
//    exit(1);
//}

$start = microtime(TRUE);
$input = file_get_contents($_SERVER['argv'][1]);
$expressions = Expression::Parse($input);
//echo "parsed {$_SERVER['argv'][1]} in " . number_format(microtime(TRUE)-$start, 4) . "s\n";
$env = new Lizp();

$start = microtime(TRUE);

foreach ($expressions as $expr) {
    $env->Evaluate($expr);
}

//echo "executed {$_SERVER['argv'][1]} in " . number_format(microtime(TRUE)-$start, 4) . "s\n";
