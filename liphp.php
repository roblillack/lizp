<?php
class Lisp {
    private $_environment = array();

    public function Apply($sexp, $args, $values, $depth = 0) {

        if (is_array($sexp)) {
            //echo "\nLIST: " . $sexp->ToString() ." (depth: {$depth})\n";
            $exps = array();
            foreach ($sexp as $v) {
                $exps []= $this->Apply($v, $args, $values, $depth + 1);
            }
            //echo "TO  : " . $r->ToString() ."\n";
            return $exps;
        } elseif ($sexp instanceof Symbol) {

            //echo "\n";
            //echo "ARGS: " . $args->ToString() . "\n";
            //echo "VALS: " . $vals->ToString() . "\n";
            //echo "SYMB: " . $sexp->ToString() . "\n";
            foreach ($args as $i => $arg) {
                if ($arg instanceof Symbol &&
                    $sexp->name == $arg->name) {
                    $r = $this->Evaluate($values[$i]);
                    //echo "RETN: " . $r->ToString() . "\n";
                    return $r;
                }
            }
        }

        //echo "RETN: " . $sexp->ToString() . "\n";
        return $sexp;
    }

    public function Evaluate($sexp, $onlyLists = FALSE) {
        if ($sexp instanceof Symbol) {
            if ($onlyLists) {
                return $sexp;
            }
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
            $evald = $this->Evaluate($first, TRUE);
            echo Expression::Render($first) . " ~~> " . Expression::Render($evald) . "\n";
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
            $applied = $lambda->parameters === NULL ? $e : $this->Apply($e, $lambda->parameters, $args);
            //echo Expression::Render($e) . " ==> " . Expression::Render($applied) . "\n";
            $r = $this->Evaluate($applied, TRUE);
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

        return $this->Evaluate(Expression::Parse($args[0]));
    }

    private static function _parse() {
        $args = func_get_args();
        if (!is_string(@$args[0])) {
            throw new Exception("Syntax Error: (PARSE <string>)");
        }

        return Expression::Parse($args[0]);
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
        $lambda->parameters = empty($args[1]) ? NULL : $args[1];
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

    private function special_form_lambda($args) {
        if (!(is_array(@$args[1]) || @$args[1] === NULL || @$args[1] === FALSE)) {
            throw new Exception("Syntax Error: (LAMBDA (<params>*) <expr>*)");
        }

        $lambda = new Lambda;
        $lambda->parameters = empty($args[0]) ? NULL : $args[0];
        $lambda->expressions = array_slice($args, 1);

        return new $lambda;
    }

    private function special_form_quote($args) {
        if (!(@$args[0] instanceof Symbol)) {
            throw new Exception("Syntax Error: (QUOTE <expr>)");
        }

        $expr = new Symbol;
        $expr->name = $args[0]->name;
        return $expr;
    }

    private function special_form_dump($args) {
        foreach ($args as $arg) {
            echo Expression::Render($arg) . "\n";
        }
        return NULL;
    }

}

class Expression {
    const TYPE_NONE = 0;
    const TYPE_INTEGER = 1;
    const TYPE_STRING = 2;
    const TYPE_SYMBOL = 3;
    const TYPE_LIST = 4;
    const TYPE_FN = 5;
    const TYPE_T = 6;
    const TYPE_OBJECT = 7;

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

    public function ToPHP() {
        switch ($this->type) {
        case self::TYPE_INTEGER:
            return (int) $this->value;
        case self::TYPE_T:
            return TRUE;
        case self::TYPE_LIST:
            if (empty($this->value)) {
                return NULL;
            }
            $list = array();
            foreach ($this->value as $v) {
                $list []= $v->ToPHP();
            }
            return $list;
        case self::TYPE_SYMBOL:
        case self::TYPE_STRING:
            return (string) $this->value;
        default:
            return $this->value;
        }
    }

    private static function FindExpression(&$tokens, $i = 0) {
        if ($tokens[$i][0] != 'PAREN') {
            return array($tokens[$i]);
        }

        if ($tokens[$i][0] == 'PAREN' &&
            $tokens[$i][1] !== '(') {
            return array();
        }

        $open = 0;
        $count = count($tokens);
        for ($j = $i; $j < $count; $j++) {
            $t = $tokens[$j];
            if ($t[0] != 'PAREN') {
                continue;
            }
            $open += $t[1] == '(' ? 1 : -1;
            if (!$open) {
                echo "FOUND EXPRESSION:\n";
                self::DumpTokens(array_slice($tokens, $i, $j - $i + 1));
                return array_slice($tokens, $i, $j - $i + 1);
            }
        }
        return array();
    }

    public static function Parse($str) {
        if (is_array($str)) {
            $tokens = $str;
        } else {
            $tokens = self::Lex($str);
        }
        //self::DumpTokens($tokens);

        if (($count = count($tokens)) > 1) {
            if ($tokens[0][0] != 'PAREN' || $tokens[$count - 1][0] != 'PAREN' ||
                $tokens[0][1] != '(' || $tokens[$count - 1][1] != ')') {
                echo "Unbalanced Parenthesises in {$str}\n";
                return new self(self::TYPE_LIST, array());
            }
            $open = 0;
            $elements = array();
            $start = 1;
            for ($i = $start; $i < $count; $i++) {
                $t = $tokens[$i];
                if ($t[0] == 'PAREN') {
                    $open += $t[1] == '(' ? 1 : -1;
                }
                if (!$open) {
                    //echo "FOUND EXPRESSION:\n";
                    //self::DumpTokens(array_slice($tokens, $start, $i-$start+1));
                    $elements []= self::Parse(array_slice($tokens, $start, $i-$start+1));
                    $start = $i+1;
                }
            }

            return empty($elements) ? NULL : $elements;
        }

        if ($count == 0) {
            return NULL;
        }

        list($type, $value) = $tokens[0];
        switch ($type) {
        case 'INTEGER':
            return (int) $value;
        case 'STRING':
            return (string) $value;
        case 'T':
            return TRUE;
        case 'NIL':
            return NULL;
        default:
            $r = new Symbol();
            $r->name = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
            return $r;
            //return new self(self::TYPE_SYMBOL, mb_strtolower($value));
        }
    }

    public static function ParseFile($filename) {
        return self::Parse('(' . file_get_contents($filename) . ')');
    }

    public static function Lex($str) {
        $tokens = array($str);
        for (;;) {
            //self::DumpTokens($tokens);
            foreach ($tokens as $i => $txt) {
                if (is_array($txt)) {
                    continue;
                }
                if (preg_match('/^\s*$/', $txt, $m)) {
                    $tokens = array_merge(array_slice($tokens, 0, $i),
                                          array_slice($tokens, $i + 1));
                    continue 2;
                }
                if (preg_match('/^\s*([\(\)\[\]\{\}])\s*(.*)$/s', $txt, $m)) {
                    $tokens = array_merge(array_slice($tokens, 0, $i),
                                          array(array('PAREN', $m[1]), $m[2]),
                                          array_slice($tokens, $i + 1));
                    continue 2;
                }
                if (preg_match('/^\s*"((?:\\\"|[^"])*)"\s*(.*)$/s', $txt, $m)) {
                    $tokens = array_merge(array_slice($tokens, 0, $i),
                                          array(array('STRING', stripslashes($m[1])), $m[2]),
                                          array_slice($tokens, $i + 1));
                    continue 2;
                }
                if (preg_match('/^\s*([0-9]+)\s*(.*?)$/is', $txt, $m)) {
                    $tokens = array_merge(array_slice($tokens, 0, $i),
                                          array(array('INTEGER', $m[1]), $m[2]),
                                          array_slice($tokens, $i + 1));
                    continue 2;
                }
                if (preg_match('/^\s*t\s*(.*)$/is', $txt, $m)) {
                    $tokens = array_merge(array_slice($tokens, 0, $i),
                                          array(array('T', 1), $m[1]),
                                          array_slice($tokens, $i + 1));
                    continue 2;
                }
                if (preg_match('/^\s*nil\s*(.*)$/is', $txt, $m)) {
                    $tokens = array_merge(array_slice($tokens, 0, $i),
                                          array(array('NIL', 1), $m[1]),
                                          array_slice($tokens, $i + 1));
                    continue 2;
                }
                if (preg_match('/^\s*([^0-9][^\s\(\)\[\]\{\}]*)\s*(.*)$/is', $txt, $m)) {
                    $tokens = array_merge(array_slice($tokens, 0, $i),
                                          array(array('SYMBOL', $m[1]), $m[2]),
                                          array_slice($tokens, $i + 1));
                    continue 2;
                }
                throw new Exception("Unknown Token: {$txt}");
            }
            break;
        }
        return $tokens;
    }

    public static function DumpTokens($tokens) {
        foreach ($tokens as $t) {
            if (is_string($t)) {
                echo "* {$t}\n";
                continue;
            }
            list($type, $value) = $t;
            echo "{$type}: {$value}\n";
        }
        echo "\n";
    }
}

class Lambda extends Expression {
    public $arguments = NULL;
    public $expressions = array();
}

class Symbol extends Expression {
    public $name = NULL;
}


/*
$ast = new Expression(Expression::TYPE_LIST, array(
                          new Expression(Expression::TYPE_SYMBOL, '+'),
                          new Expression(Expression::TYPE_INTEGER, 1),
                          new Expression(Expression::TYPE_INTEGER, 2)));

echo $ast->ToString() . ' --> ' . $ast->Evaluate()->ToString() . "\n";
*/

if ($_SERVER['argc'] < 2) {
    echo "syntax: {$_SERVER['argv'][0]} FILE\n";
    exit(1);
}

//Expression::DumpTokens(Expression::Lex($_SERVER['argv'][1]));

//Expression::DumpTokens(Expression::Lex(file_get_contents($_SERVER['argv'][1])));
//exit(0);
$start = microtime(TRUE);
$expressions = Expression::ParseFile($_SERVER['argv'][1]);
echo "parsed {$_SERVER['argv'][1]} in " . number_format(microtime(TRUE)-$start, 4) . "s\n";
$env = new Lisp();

$start = microtime(TRUE);

foreach ($expressions as $expr) {
    //echo $expr->ToString() . "\n";
    $env->Evaluate($expr);
}

echo "executed {$_SERVER['argv'][1]} in " . number_format(microtime(TRUE)-$start, 4) . "s\n";
