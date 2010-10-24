<?php
require_once(dirname(__FILE__) . '/lizp.php');

class LizpCompiler {
    public $environment = array();
    private $_asm = NULL;

    private function Emit($str) {
        if ($this->_asm === NULL) {
            $this->_asm = array();
            $this->_asm []= '<?php';
            $this->_asm []= 'require_once("lizp.php");';
            $this->_asm []= '$env = new Lizp();';
        }
        $this->_asm []= $str;
    }

    public function GetAsm() {
        return implode("\n", $this->_asm);
    }

    public function Compile($sexp) {
        $this->Emit("// " . Expression::Render($sexp));

        if ($sexp instanceof Symbol) {
            $this->Emit("\$r = @\$env->environment['" . addslashes($sexp->name) . "'];");
            return;
        } elseif (!is_array($sexp)) {
            $this->Emit('$r = ' . LizpCompiler::EmitExpression($sexp) . ';');
            return;
        }

        $lambda = $sexp[0];
        $args = array_slice($sexp, 1);

        if (is_array($lambda)) {
            $evald = $this->Compile($lambda);
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
                $applied = $lambda->arguments === NULL ? $e : $this->Apply($e, $lambda->arguments, $args);
                //echo Expression::Render($e) . " =APPLY=> " . Expression::Render($applied) . "\n";
                $expanded = $this->Evaluate($applied);
                //echo Expression::Render($applied) . " =MACROEXPAND=> " . Expression::Render($expanded) . "\n";
            }
            return $this->Compile($expanded);
        }

        if ($lambda instanceof Symbol) {
            // special forms (non-evaluated parameters)
            $specialForm = "lizp_special_form_{$phpName}";
            if (function_exists($specialForm)) {
                $this->Emit("\$r = $specialForm(\$env, " . LizpCompiler::EmitExpression($args) . ");");
                return;
            }
        }

        // We evaluate the parameters
        $params = array();
        $expandNext = FALSE;
        $rand = rand(1, 1000000);
        foreach ($args as $i => $v) {
            if ($v instanceof AtSign) {
                $expandNext = TRUE;
                continue;
            }
            $this->Compile($v);
            $this->Emit("\$param_{$rand}_$i = \$r;");
            if ($expandNext && is_array($r)) {
                $params = array_merge($params, $r);
            } else {
                $params []= "\$param_{$rand}_$i"; //r;
            }
            $expandNext = FALSE;
        }

        if ($lambda instanceof Lambda) {
            $r = NULL;
            foreach ($lambda->expressions as $e) {
                $args = array();
                foreach ($params as $i) {
                    if (is_array($i)) {
                        $i = array(Symbol::Make('quote'), $i);
                    }
                    $args []= $i;
                }
                //$params = $args;
                $applied = $lambda->arguments === NULL ? $e : $this->Apply($e, $lambda->arguments, $params);
                //echo Expression::Render($e) . " -APPLY-> " . Expression::Render($applied) . "\n";
                // $r =
                $this->Compile($applied);
                //echo Expression::Render($applied) . " -EVAL-> " . Expression::Render($r) . "\n";
            }
            return; // $r
        }

        if ($lambda instanceof Symbol) {
            // internal functions / php functions
            $internalFn = "lizp_internal_fn_{$phpName}";
            if (($isInternal = function_exists($internalFn)) ||
                function_exists($phpName)) {

                if ($isInternal) {
                    $this->Emit("\$r = $internalFn(\$env, array(" . implode(', ', $params) . "));");
                    return;
                }

                $this->Emit("\$r = $phpName(" . implode(', ', $params) . ");");
                $this->Emit('if (is_array($r) && count($r) == 0) {');
                $this->Emit('    $r = NULL;');
                $this->Emit('}');
                return;
            }
        }

        $this->Emit("/* Unable to evaluate Expression: " . Expression::Render($sexp) .
                    " because function name evaluates to " . Expression::Render($lambda) . " */");
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

    public static function EmitExpression($expr) {
        if ($expr === NULL || $expr === FALSE ||
            (is_array($expr) && empty($expr))) {
            return 'NULL';
        }

        if ($expr === TRUE) {
            return 'true';
        }

        if (is_int($expr) || is_double($expr)) {
            return (string) $expr;
        }

        if (is_string($expr)) {
            return "'" . addslashes($expr) . "'";
        }

        if (is_array($expr)) {
            $out = array();
            foreach ($expr as $e) {
                $out []= self::EmitExpression($e);
            }
            return 'array(' .  implode(', ', $out) . ')';
        }

        if ($expr instanceof Tilde) {
            return 'new Tilde()';
        }

        if ($expr instanceof AtSign) {
            return 'new AtSign()';
        }

        if ($expr instanceof Symbol) {
            return "Symbol::Make('{$expr->name}')";
        }

        if ($expr instanceof Lambda) {
            return '<lambda:' . Expression::Render($expr->arguments) . '>';
        }

        if ($expr instanceof Macro) {
            return '<macro:' . Expression::Render($expr->arguments) . '>';
        }

        return '<' . get_class($expr) . '>';
    }

}

$cmd = @$_SERVER['argv'][1];
if (is_readable($cmd)) {
    $start = microtime(TRUE);
    $input = file_get_contents($cmd);
    $expressions = Expression::Parse($input);
    echo "parsed {$_SERVER['argv'][1]} in " . number_format(microtime(TRUE)-$start, 4) . "s\n";

    $compiler = new LizpCompiler();

    $start = microtime(TRUE);

    foreach ($expressions as $expr) {
        $compiler->Compile($expr);
    }

    $outfile = preg_replace('/\.lizp$/', '.php', $cmd);

    file_put_contents($outfile, $compiler->GetAsm() . "\n");

    echo "$outfile created.\n";
}