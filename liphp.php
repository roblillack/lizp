<?php
class Lisp {
    private $_environment = array();

    public function Apply($sexp, $args, $values) {
        if (is_array($sexp)) {
            $exps = array();
            foreach ($sexp as $v) {
                $exps []= $this->Apply($v, $args, $values);
            }
            return $exps;
        } elseif ($sexp instanceof Symbol) {
            foreach ($args as $i => $arg) {
                if ($arg instanceof Symbol && $sexp->name == $arg->name) {
                    $r = $this->Evaluate($values[$i]);
                    return $r;
                }
            }
        }

        return $sexp;
    }

    public function Evaluate($sexp) {
        if ($sexp instanceof Symbol) {
            if (($r = @$this->_environment[$sexp->name]) !== NULL) {
                return $r === FALSE ? NULL : $r;
            }
            throw new Exception("Unknown identifier: " . $sexp->name);
        } elseif (!is_array($sexp)) {
            return $sexp;
        }

        if (empty($sexp)) {
            return NULL;
        }

        $first = $sexp[0];
        $args = array_slice($sexp, 1);

        if (is_array($first)) {
            $evald = $this->Evaluate($first);
            //echo Expression::Render($first) . " ~~> " . Expression::Render($evald) . "\n";
            $first = $evald;
        }

        if ($first instanceof Symbol) {
            // special forms
            switch ($first->name) {
            case 'and':    return self::special_form_and($args);
            case 'define': return self::special_form_define($args);
            case 'defun':  return self::special_form_defun($args);
            case 'do':     return self::special_form_do($args);
            case 'dump':   return self::special_form_dump($args);
            case 'eq?':    return self::special_form_eq($args);
            case 'if':     return self::special_form_if($args);
            case 'lambda': return self::special_form_lambda($args);
            case 'let':    return self::special_form_let($args);
            case 'quote':  return self::special_form_quote($args);
            case 'unless': return self::special_form_unless($args);
            case 'when':   return self::special_form_when($args);
            case 'while':  return self::special_form_while($args);
            }

            // internal functions
            if (($fnName = @self::$_internalFunctions[$first->name]) !== NULL) {
                $params = array();
                foreach ($args as $v) {
                    $params []= $this->Evaluate($v);
                }
                if (function_exists("self::{$fnName}")) {
                    return call_user_func_array("self::{$fnName}", $params);
                } else {
                    return call_user_func_array(array($this, $fnName), $params);
                }
            }

            // php functions
            if (function_exists($first->name)) {
                $params = array();
                foreach ($args as $v) {
                    $params []= $this->Evaluate($v);
                }
                $r = call_user_func_array($first->name, $params);
                if ($r === FALSE || (is_array($r) && empty($r))) {
                    $r = NULL;
                }
                return $r;
            }

            $lambda = @$this->_environment[$first->name];
        } else {
            $lambda = $first;
        }

        if (!($lambda instanceof Lambda)) {
            throw new Exception("Unable to evaluate Expression: " . Expression::Render($sexp) .
                                " because function name evaluates to " . Expression::Render($lambda));
        }

        $r = NULL;
        foreach ($lambda->expressions as $e) {
            $applied = $lambda->arguments === NULL ? $e : $this->Apply($e, $lambda->arguments, $args);
            //echo Expression::Render($e) . " ==> " . Expression::Render($applied) . "\n";
            $r = $this->Evaluate($applied);
        }
        return $r;
    }

    private static $_internalFunctions = array(
        'sum'     => '_sum',
        '+'       => '_sum',
        '-'       => '_sub',
        '*'       => '_multiply',
        '/'       => '_divide',
        'parse'   => '_parse',
        'print'   => '_print',
        'println' => '_println',
        'p'       => '_dump',
        'exit'    => '_exit',
        'eval'    => '_eval');

    private static function _exit() {
        $args = func_get_args();
        if (($r = @$args[0]) !== NULL && is_int($r)) {
            exit($r);
        }

        exit(0);
    }

    private static function _dump() {
        $arg = NULL;
        foreach (func_get_args() as $arg) {
            echo Expression::Render($arg) . "\n";
        }
        return $arg;
    }

    private static function _print() {
        foreach (func_get_args() as $arg) {
            if (is_string($arg) || is_int($arg)) {
                echo $arg;
            }
        }
        return NULL;
    }

    private static function _println() {
        foreach (func_get_args() as $arg) {
            if (is_string($arg) || is_int($arg)) {
                echo $arg;
            }
        }
        echo "\n";
        return NULL;
    }

    private static function _sum() {
        $sum = 0;

        foreach (func_get_args() as $arg) {
            if (is_int($arg)) {
                $sum += $arg;
            }
        }

        return $sum;
    }

    private static function _multiply() {
        $res = 1;
        foreach (func_get_args() as $arg) {
            if (is_int($arg)) {
                $res *= $arg;
            }
        }
        return $res;
    }

    private static function _divide() {
        $res = NULL;
        foreach (func_get_args() as $arg) {
            if (is_int($arg)) {
                if ($res === NULL) {
                    $res = $arg;
                } else {
                    $res /= $arg;
                }
            }
        }
        return $res === NULL ? 1 : $res;
    }

    private static function _sub() {
        $sum = NULL;
        foreach (func_get_args() as $arg) {
            if (is_int($arg)) {
                if ($sum === NULL) {
                    $sum = $arg;
                } else {
                    $sum -= $arg;
                }
            }
        }
        return $sum;
    }

    private function _eval() {
        if (count($args = func_get_args()) != 1 || !is_string(@$args[0])) {
            throw new Exception("syntax (EVAL <str>)");
        }

        $expr = Expression::Parse($args[0]);
        if (count($expr) > 1) {
            throw new Exception("Error: Multiple expressions given in " .
                                Expression::Render($arg[0]));
        }

        return $this->Evaluate(@$expr[0]);
    }

    private static function _parse() {
        $args = func_get_args();
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

    private function special_form_eq($args) {
        $last = FALSE;

        foreach ($args as $arg) {
            $ev = $this->Evaluate($arg);
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

    private function special_form_and($args) {
        foreach ($args as $arg) {
            if ($this->Evaluate($arg) === NULL) {
                return NULL;
            }
        }
        return TRUE;
    }

    private function special_form_if($args) {
        return $this->Evaluate(@$args[0]) === NULL ?
            $this->Evaluate(@$args[2]) :
            $this->Evaluate(@$args[1]);
    }

    private function special_form_unless($args) {
        $r = NULL;

        if ($this->Evaluate($args[0]) !== NULL) {
            return $r;
        }

        foreach (array_slice($args, 1) as $e) {
            $r = $this->Evaluate($e);
        }

        return $r;
    }

    private function special_form_when($args) {
        $r = NULL;

        if ($this->Evaluate($args[0]) === NULL) {
            return $r;
        }

        foreach (array_slice($args, 1) as $e) {
            $r = $this->Evaluate($e);
        }

        return $r;
    }

    private function special_form_while($args) {
        $r = NULL;

        while ($this->Evaluate($args[0]) !== NULL) {
            foreach (array_slice($args, 1) as $e) {
                $r = $this->Evaluate($e);
            }
        }

        return $r;
    }

    private function special_form_define($args) {
        if (count($args) != 2) {
            throw new Exception("(DEFINE) needs two parameters!");
        }
        if (!($args[0] instanceof Symbol)) {
            throw new Exception("Syntax Error: (DEFINE <id> <expr>)");
        }

        $r = $this->Evaluate($args[1]);
        return $this->_environment[$args[0]->name] = ($r === NULL ? FALSE : $r);
    }

    private function special_form_defun($args) {
        if (!(@$args[0] instanceof Symbol) ||
            !(is_array(@$args[1]) || @$args[1] === NULL || @$args[1] === FALSE)) {
            throw new Exception("Syntax Error: (DEFUN <id> (<params>*) <expr>*)");
        }

        $lambda = new Lambda;
        $lambda->arguments = empty($args[1]) ? NULL : $args[1];
        $lambda->expressions = array_slice($args, 2);

        $this->_environment[$args[0]->name] = $lambda;
    }

    private function special_form_do($args) {
        $r = NULL;

        foreach ($args as $e) {
            $r = $this->Evaluate($e);
        }

        return $r;
    }

    private function special_form_let($args) {
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

        return $this->Evaluate($expressions);
    }

    private function special_form_lambda($args) {
        if (!(is_array(@$args[0]) || @$args[0] === NULL || @$args[0] === FALSE)) {
            throw new Exception("Syntax Error: (LAMBDA (<params>*) <expr>*)");
        }

        $lambda = new Lambda;
        $lambda->arguments = empty($args[0]) ? NULL : $args[0];
        $lambda->expressions = array_slice($args, 1);

        return $lambda;
    }

    private function special_form_quote($args) {
        if (count($args) != 1) {
            throw new Exception("Syntax Error: (QUOTE <expr>)");
        }

        return $args[0];
    }

    private function special_form_dump($args) {
        foreach ($args as $arg) {
            echo Expression::Render($arg) . "\n";
        }
        return NULL;
    }

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

        if (is_int($expr)) {
            return (string) $expr;
        }

        if (is_string($expr)) {
            return '"' . addslashes($expr) . '"';
        }

        if (is_array($expr)) {
            $out = array();
            foreach ($expr as $e) {
                $out []= self::Render($e);
            }
            return '(' .  implode(' ', $out) . ')';
        }

        if ($expr instanceof Symbol) {
            return $expr->name;
        }

        if ($expr instanceof Lambda) {
            return '<lambda:' . self::Render($expr->arguments) . '>';
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

        for (;;) {
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
                $tokens []= self::Parse($s, $p);
                $pos = $p;
                continue;
            }

            // from here on i'll need an actual substring,
            // because the regexp functions do not allow matching
            // at a specified offset only. :(
            $sub = substr($s, $pos);

            // consume strings
            if (preg_match('/^"((?:\\\"|[^"])*)"/s', $sub, $m)) {
                $tokens []= stripslashes($m[1]);
                $pos += strlen($m[1]) + 2;
                continue;
            }

            // consume integers
            if (preg_match('/^([0-9]+)/s', $sub, $m)) {
                $tokens []= (int) $m[1];
                $pos += strlen($m[1]);
                continue;
            }

            // consume symbols
            if (preg_match('/^([^0-9][^\s\(\)\[\]\{\}]*)/s', $sub, $m)) {
                $sname = $m[1];
                $supper = strtoupper($sname);
                if ($supper == 'T') {
                    $tokens []= TRUE;
                } elseif ($supper == 'NIL') {
                    $tokens []= NULL;
                } else {
                    $symbol = new Symbol;
                    $symbol->name = $sname;
                    $tokens []= $symbol;
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

class Symbol extends Expression {
    public $name = NULL;
}

if ($_SERVER['argc'] < 2) {
    echo "syntax: {$_SERVER['argv'][0]} FILE\n";
    exit(1);
}

$start = microtime(TRUE);
//$expressions = Expression::ParseFile($_SERVER['argv'][1]);
$input = file_get_contents($_SERVER['argv'][1]);
$expressions = Expression::Parse($input);
echo "parsed {$_SERVER['argv'][1]} in " . number_format(microtime(TRUE)-$start, 4) . "s\n";
$env = new Lisp();

$start = microtime(TRUE);

foreach ($expressions as $expr) {
    $env->Evaluate($expr);
}

echo "executed {$_SERVER['argv'][1]} in " . number_format(microtime(TRUE)-$start, 4) . "s\n";
