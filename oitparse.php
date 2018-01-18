<?

require_once ('oitconsts.php');
require_once ('utils.php');

class OITParse {
    
    public static function parseInput ($input) {
        $return = array ();
        $names = array (OIT_TYPE_TEMPLATE => array (), OIT_TYPE_VARIABLE => array ());
        $ol = -1;
        if (preg_match ('/^\xef\xbb\xbf/', $input)) $input = substr ($input, 3); // utf-8 bom prefix
        $input = trim ($input);
        while (! empty ($input)) {
            if ($ol == ($l = strlen ($input)))
                throw new Exception ('Parse error at \'' . substr ($input, 0, 100) . '\'');
            $o = $input;
            $new = self::parseTerm ($input);
            if ($new !== false) {
                if (! in_array ($new[OIT_KEY_TYPE], array (OIT_TYPE_TEMPLATE, OIT_TYPE_VARIABLE)))
                    throw new Exception ('Only &templates and *variables are allowed at top level');
                if (in_array ($new[OIT_KEY_NAME], $names[$new[OIT_KEY_TYPE]]))
                    throw new Exception ('Duplicate name: ' . $old[OIT_KEY_NAME]);
                $return[] = $new;
                $names[$new[OIT_KEY_TYPE]][] = $new[OIT_KEY_NAME];
            }
            elseif (! empty ($input))
                throw new Exception ('Erroneous term \'' . substr ($o, 0, 100) . '\'');
            $ol = $l;
        }
        return $return;
    }

    private static function parseTerm (&$input) {
        $input = ltrim ($input);
        if (preg_match ('/[,;]/', $input[0])) $input = ltrim (substr ($input, 1));
        while (preg_match ('/--|\[\[/', $ct = substr ($input, 0, 2))) {
            if ($ct[0] == '-') {
                if (false === ($n = strpos ($input, "\n"))) $n = strpos ($input, "\r");
                if ($n === false) {
                    $input = '';
                    return false;
                }
                $input = ltrim (substr ($input, $n + 1));
            }
            else {
                if (false === ($n = strpos ($input, ']]', 2))) {
                    $input = '';
                    return false;
                }
                $input = ltrim (substr ($input, $n + 2));
            }
        }
        if ($input[0] == '&') {
            $input = ltrim (substr ($input, 1));
            preg_match ('/\w*/', $input, $matches);
            $input = ltrim (substr ($input, strlen ($name = $matches[0])));
            $params = array ();
            while ($input[0] == '@') $params[] = self::parseAttribute ($input);
            if (! empty ($params)) $params = array (OIT_KEY_PARAM => $params);
            return array (OIT_KEY_TYPE => OIT_TYPE_TEMPLATE, OIT_KEY_NAME => $name) + $params + self::parseDefinition ($input);
        }
        elseif ($input[0] == '*') {
            $input = ltrim (substr ($input, 1));
            preg_match ('/\w*/', $input, $matches);
            $input = ltrim (substr ($input, strlen ($name = $matches[0])));
            return array (OIT_KEY_TYPE => OIT_TYPE_VARIABLE, OIT_KEY_NAME => $name) + self::parseDefinition ($input);
        }
        elseif ($input[0] == '@') {
            return self::parseAttribute ($input);
        }
        elseif ($input[0] == '$') {
            return self::parseStyle ($input);
        }
        elseif ($input[0] == '!') {
            $input = ltrim (substr ($input, 1));
            preg_match ('/\w*/', $input, $matches);
            $input = ltrim (substr ($input, strlen ($name = $matches[0])));
            $add = array ();
            $ok = false;
            if (preg_match ('/^(if|wh(en|ile)|for)$/i', $name)) {
                $ok = true;
                if ($input[0] == '{') $add = array (OIT_KEY_TEST => self::parseExpression ($input));
                else $ok = false;
            }
            if (preg_match ('/^(choo|otherwi)se$/i', $name)) $ok = true;
            if ($ok) return array (OIT_KEY_TYPE => constant ('OIT_TYPE_' . strtoupper ($name))) + $add + self::parseDefinition ($input);
            throw new Exception ('Unknown control: ' . $name);
        }
        elseif (in_array ($input[0], array ('"', '\''))) {
            return self::parseString ($input);
        }
        elseif ($input[0] == '{') {
            return self::parseExpression ($input);
        }
        else {
            preg_match ('/^\w*/', $input, $matches);
            $input = ltrim (substr ($input, strlen ($name = $matches[0])));
            $id = $class = array ();
            if ($input[0] == '#') {
                $input = ltrim (substr ($input, 1));
                preg_match ('/^\w*/', $input, $matches);
                $input = ltrim (substr ($input, strlen ($id = $matches[0])));
                $id = array (OIT_KEY_ID => $id);
            }
            while ($input[0] == '.') {
                $input = ltrim (substr ($input, 1));
                preg_match ('/^\w*/', $input, $matches);
                $input = ltrim (substr ($input, strlen ($class[] = $matches[0])));
            }
            if (! empty ($class)) $class = array (OIT_KEY_CLASS => $class);
            $attrs = $style = array ();
            while (in_array ($input[0], array ('@', '$'))) {
                if ($input[0] == '@') {
                    if ($add = self::parseAttribute ($input)) $attrs[] = $add;
                }
                elseif ($input[0] == '$') {
                    if ($add = self::parseStyle ($input)) $style[] = $add;
                }
            }
            if (! empty ($attrs)) $attrs = array (OIT_KEY_ATTRS => $attrs);
            if (! empty ($style)) $style = array (OIT_KEY_STYLE => $style);
            return array (OIT_KEY_TYPE => OIT_TYPE_TAG, OIT_KEY_NAME => $name) + 
                $id + $class + $attrs + $style + self::parseDefinition ($input, ':');
        }
    }

    private static function parseAttribute (&$input) {
        $input = ltrim ($input);
        if ($input[0] != '@') return false;
        $input = ltrim (substr ($input, 1));
        preg_match ('/^[\w:-]*/', $input, $matches);
        $input = ltrim (substr ($input, strlen ($name = $matches[0])));
        $def = array ();
        return array (OIT_KEY_TYPE => OIT_TYPE_ATTRIBUTE, OIT_KEY_NAME => $name) + self::parseDefinition($input);
    }

    private static function parseStyle (&$input) {
        $input = ltrim ($input);
        if ($input[0] != '$') return false;
        $input = ltrim (substr ($input, 1));
        preg_match ('/^[\w:-]*/', $input, $matches);
        $input = ltrim (substr ($input, strlen ($name = $matches[0])));
        $def = array ();
        return array (OIT_KEY_TYPE => OIT_TYPE_STYLE, OIT_KEY_NAME => $name) + self::parseDefinition($input);
    }

    private static function parseDefinition (&$input, $eqsign = '=') {
        $input = ltrim ($input);
        $equal = false;
        $children = array ();
        $equal = false;
        if ($input[0] == $eqsign) {
            $input = ltrim (substr ($input, 1));
            $equal = true;
        }
        if ($input[0] == '(') {
            $input = ltrim (substr ($input, 1));
            while (! empty ($input) && $input[0] != ')') {
                if ($ol == ($l = strlen ($input))) 
                    throw new Exception ('Parse error at \'' . substr ($input, 0, 100) . '\'');
                $children[] = self::parseTerm ($input);
                $ol = $l;
            }
            if (empty ($input)) throw new Exception ('Unexpected end of input! Probably \')\' is missing.');
            $input = ltrim (substr ($input, 1));
        }
        elseif ($input[0] == '{')
            $children[] = self::parseExpression ($input);
        elseif ($equal) {
            if (in_array ($input[0], array ('\'', '"')))
                $children[] = self::parseString ($input);
            else {
                preg_match ('/^[\w:-]*/', $input, $matches);
                $input = ltrim (substr ($input, strlen ($value = $matches[0])));
                $children[] = array (OIT_KEY_TYPE => OIT_TYPE_STRING, OIT_KEY_VALUE => $value);
            }
        }
        return array (OIT_KEY_CHILD => $children);
    }
    
    private static function parseString (&$input) {
        $input = ltrim ($input);
        if (! in_array ($input[0], array ('\'', '"'))) return false;
        $quote = $input[0];
        for ($i = 1, $l = strlen ($input); $i < $l; $i++) if ($input[$i] == $quote) break;
        $ret = substr ($input, 1, $i - 1);
        $input = ltrim (substr ($input, $i + 1));
        return array (OIT_KEY_TYPE => OIT_TYPE_STRING, OIT_KEY_VALUE => $ret);
    }
    
    /**
     * MUST be sorted in descending order by second field.
     * see http://isr.by.ru/prolog/ch3_3.htm for references
     */
    private static $operators = array (
        array ('|',  900, 'xfy', OIT_OPERATOR_OR),
        array ('&',  800, 'xfy', OIT_OPERATOR_AND),
        array ('!=', 700, 'xfx', OIT_OPERATOR_NOT_EQUAL),
        array ('=',  700, 'xfx', OIT_OPERATOR_EQUAL),
        array ('<=', 700, 'xfx', OIT_OPERATOR_LEQ),
        array ('<',  700, 'xfx', OIT_OPERATOR_LESS),
        array ('>=', 700, 'xfx', OIT_OPERATOR_GREQ),
        array ('>',  700, 'xfx', OIT_OPERATOR_GREATER),
        array ('~',  600, 'xfx', OIT_OPERATOR_PREG_MATCH),
        array ('+',  500, 'yfx', OIT_OPERATOR_BIN_PLUS),
        array ('-',  500, 'yfx', OIT_OPERATOR_BIN_MINUS),
        array ('++', 500, 'yfx', OIT_OPERATOR_CONCAT),
        array ('*',  400, 'yfx', OIT_OPERATOR_MULTIPLY),
        array ('/',  400, 'yfx', OIT_OPERATOR_DIVIDE),
        array ('%',  300, 'xfx', OIT_OPERATOR_MODULO),
        array ('..', 200, 'xfx', OIT_OPERATOR_RANGE),
        array ('+',  100, 'fx',  OIT_OPERATOR_UN_PLUS),
        array ('-',  100, 'fx',  OIT_OPERATOR_UN_MINUS),
        array ('!',  100, 'fy',  OIT_OPERATOR_NOT),
        array ('.',    1, 'yfx', OIT_OPERATOR_DOT),
    );
    
    private static $operatorsnew = array (
        array (array (OITOP_X, '/\|/',   OITOP_Y), 900, OIT_OPERATOR_OR),
        array (array (OITOP_X, '/&/',    OITOP_Y), 800, OIT_OPERATOR_AND),
        array (array (OITOP_X, '/!=/',   OITOP_X), 700, OIT_OPERATOR_NOT_EQUAL),
        array (array (OITOP_X, '/=/',    OITOP_X), 700, OIT_OPERATOR_EQUAL),
        array (array (OITOP_X, '/<=/',   OITOP_X), 700, OIT_OPERATOR_LEQ),
        array (array (OITOP_X, '/</',    OITOP_X), 700, OIT_OPERATOR_LESS),
        array (array (OITOP_X, '/>=/',   OITOP_X), 700, OIT_OPERATOR_GREQ),
        array (array (OITOP_X, '/>/',    OITOP_X), 700, OIT_OPERATOR_GREATER),
        array (array (OITOP_X, '/~/',    OITOP_X), 600, OIT_OPERATOR_PREG_MATCH),
        array (array (OITOP_Y, '/\+/',   OITOP_X), 500, OIT_OPERATOR_BIN_PLUS),
        array (array (OITOP_Y, '/-/',    OITOP_X), 500, OIT_OPERATOR_BIN_MINUS),
        array (array (OITOP_Y, '/\+\+/', OITOP_X), 500, OIT_OPERATOR_CONCAT),
        array (array (OITOP_Y, '/\*/',   OITOP_X), 400, OIT_OPERATOR_MULTIPLY),
        array (array (OITOP_Y, '~/~',    OITOP_X), 400, OIT_OPERATOR_DIVIDE),
        array (array (OITOP_X, '/%/',    OITOP_X), 300, OIT_OPERATOR_MODULO),
        array (array (OITOP_X, '/\.\./', OITOP_X), 200, OIT_OPERATOR_RANGE),
        array (array (         '/\+/',   OITOP_X), 100, OIT_OPERATOR_UN_PLUS),
        array (array (         '/-/',    OITOP_X), 100, OIT_OPERATOR_UN_MINUS),
        array (array (         '/!/',    OITOP_Y), 100, OIT_OPERATOR_NOT),
        array (array (OITOP_Y, '/\./',   OITOP_X),   1, OIT_OPERATOR_DOT),
    );

    /**
     * @return array (expr, subexprs, free) - slice of original expr from start to end.
     * also performs trimming to simplify further analysis.
     * the essential is to extract subexpressions and free zones with correct indexing
     */
    private static function getExpressionSlice ($start, $end, $expr, $subexprs, $free) {
        if ($end < 0) $end += strlen ($expr) - 1;
        while (preg_match ('/\s/', $expr[$start]) && $start <= $end) $start++;
        if ($start > $end) return array('', array (), array ());
        while (preg_match ('/\s/', $expr[$end])) $end--;
        $nse = array ();
        foreach ($subexprs as $se) {
            if ($se[1] < $start || $se[2] > $end) continue;
            $nse[] = array ($se[0], $se[1] - $start, $se[2] - $start);
        }
        $nf = array ();
        $last = 0;
        foreach ($nse as $se) {
            if ($se[1] < $last) continue;
            if ($se[1] > $last) $nf[] = array ($last, $se[1] - 1);
            $last = $se[2] + 1;
        }
        if ($end - $start > $last) $nf[] = array ($last, $end - $start + 1);
        $return = array (substr ($expr, $start, $end - $start + 1), $nse, $nf);
        return $return;
    }
    
    /** 
     * @return array(array(expr, subexprs, free), ...) which are comma-separated subexpressions of expr
     */
    private static function splitExpression ($expr, $subexprs, $free) {
        $c = array (-1);
        foreach ($free as $f)
            if (false !== ($n = strpos (substr ($expr, $f[0], $f[1] - $f[0] + 1), ',')))
                $c[] = $f[0] + $n;
        if (1 == ($n = count($c))) return array (array ($expr, $subexprs, $free));
        $c[] = strlen ($expr);
        for ($i = 0, $ret = array (); $i < $n; $i++)
            $ret[] = self::getExpressionSlice ($c[$i] + 1, $c[$i+1] - 1, $expr, $subexprs, $free);
        return $ret;
    }
    
    private static function buildExpressionNodes ($expr, $subexprs, $free) {
        $grouped = false;
        $l = strlen ($expr);
        
        // grouping parentheses
        while (($c = count ($subexprs)) > 0 &&
            $subexprs[0][0] == '(' &&
            $subexprs[0][1] == 0 &&
            $subexprs[0][2] == $l - 1
        ) {
            list ($expr, $subexprs, $free) = self::getExpressionSlice (1, -1, $expr, $subexprs, $free);
            $l = strlen ($expr);
            $grouped = true;
        }
        
        // string literal = single- or double-quoted subexpression
        if ($c == 1 && 
            ($subexprs[0][0] == '\'' || $subexprs[0][0] == '"') &&
            $subexprs[0][1] == 0 &&
            $subexprs[0][2] == $l - 1
        )
            return array (OIT_KEY_TYPE => OIT_TYPE_STRING, OIT_KEY_VALUE => substr ($expr, 1, -1));
        
        // numeric literal = digits and stuff
        if (preg_match ('/^[+-]?\d+(\.\d+)?$/', $expr))
            return array (OIT_KEY_TYPE => OIT_TYPE_NUMBER, OIT_KEY_VALUE => $expr);
        
        // list literal = bracketed comma-separated expression list
        // gotta make usage of subexprs!
        if (preg_match ('/^\[(.*)\]$/', $expr, $matches)) {
            pre ('expr', $expr);
            pre ('subexprs', $subexprs);
            die;
            list ($nexpr, $nsubexprs, $nfree) = self::getExpressionSlice (1, -1, $expr, $subexprs, $free);
            $elems = array ();
            if (! empty ($nexpr))
                foreach (self::splitExpression ($nexpr, $nsubexprs, $nfree) as $li)
                    $elems[] = self::buildExpressionNodes ($li[0], $li[1], $li[2]);
            return array (OIT_KEY_TYPE => OIT_TYPE_LIST, OIT_KEY_CHILD => $elems);
        }
        
        // regexp literal = slash-quoted string plus modifiers
        if ($c == 1 && 
            $subexprs[0][0] == '/' &&
            $subexprs[0][1] == 0 &&
            preg_match ('/^\w*$/', substr ($expr, $subexprs[0][2] + 1))
        )
            return array (OIT_KEY_TYPE => OIT_TYPE_REGEXP, OIT_KEY_VALUE => $expr);
        
        // data read or a local variable = single word
        if (preg_match ('/^\w+$/', $expr))
            return array (OIT_KEY_TYPE => OIT_TYPE_DATA, OIT_KEY_NAME => $expr);
        
        // function call = word followed by a single parenthesized subexpression.
        // since we have no objects or methods, no expression evaluate to a function.
        // so there's no need to assign a priority to function call operation.
        if ($c > 0 && 
            $subexprs[0][0] == '(' && 
            $subexprs[0][1] > 0 &&
            $subexprs[0][2] == $l - 1 &&
            preg_match ('/^(\w+)\s*$/', substr ($expr, 0, $subexprs[0][1]), $matches)
        ) {
            $fname = $matches[1];
            list ($nexpr, $nsubexprs, $nfree) = self::getExpressionSlice ($subexprs[0][1] + 1, $subexprs[0][2] - 1, $expr, $subexprs, $free);
            $args = array ();
            if (! empty ($nexpr))
                foreach (self::splitExpression ($nexpr, $nsubexprs, $nfree) as $a)
                    $args[] = self::buildExpressionNodes ($a[0], $a[1], $a[2]);
            return array (OIT_KEY_TYPE => OIT_TYPE_CALL, OIT_KEY_NAME => $fname, OIT_KEY_ATTRS => $args);
        }
        
        // children by expression[expression].
        // implement it, you bastard!
        // and make usage of subexprs!
        // and don't forget to set priority to 1 (same as .)
        
        // complex expression built with operatorial syntax.
        // prefix and postfix unary, and infix binary only. not enough.
        if (true) {
            foreach (self::$operators as $op) {
                foreach ($free as $f) {
                    if (false !== ($n = strpos (substr ($expr, $f[0], $f[1] - $f[0] + 1), $op[0]))) {
                        $n += $f[0];
                        $child = array ();
                        if (preg_match ('/^f/', $op[2]) && $n > 0) continue;
                        if (preg_match ('/f$/', $op[2]) && $n + strlen ($op[0]) > $l) continue;
                        if (preg_match ('/^[xy]f/', $op[2])) {
                            if (false === ($left = self::getExpressionSlice (0, $n - 1, $expr, $subexprs, $free))) continue;
                            if (false === ($left = self::buildExpressionNodes ($left[0], $left[1], $left[2]))) continue;
                            if (preg_match ('/^xf/', $op[2]) && array_key_exists (OIT_KEY_PRIOR, $left) && $left[OIT_KEY_PRIOR] == $op[1]) continue;
                            $child[] = $left;
                        }
                        if (preg_match ('/f[xy]$/', $op[2])) {
                            if (false === ($right = self::getExpressionSlice ($n + strlen ($op[0]), $l, $expr, $subexprs, $free))) continue;
                            if (false === ($right = self::buildExpressionNodes ($right[0], $right[1], $right[2]))) continue;
                            if (preg_match ('/fx$/', $op[2]) && array_key_exists (OIT_KEY_PRIOR, $right) && $right[OIT_KEY_PRIOR] == $op[1]) continue;
                            $child[] = $right;
                        }
                        return array (OIT_KEY_TYPE => OIT_TYPE_OPERATOR, OIT_KEY_PRIOR => $grouped ? 0 : $op[1], 
                            OIT_KEY_NAME => $op[3], OIT_KEY_CHILD => $child);
                    }
                }
            }
        }
        else {
            $xy = array (OITOP_X, OITOP_Y);
            foreach (self::$operatorsnew as $op) {
                // $rxs is a list of all regexps in operator definition.
                // $vars is array of possible positions for each regexp from $rxs.
                $vars = $rxs = array ();
                foreach ($op[0] as $rx) if (! in_array ($rx, $xy)) {
                    $rxs[] = $rx;
                    $curvars = array ();
                    foreach ($free as $f) {
                        // determine curvars
                        $curpos = $f[0];
                        while ($curpos <= $f[1]) {
                            if (preg_match ('', substr(), $matches, PREG_OFFSET_CAPTURE)) {
                            }
                        }
                    }
                    $vars[] = $curvars;
                }
            }
        }
        return false;
    }

    private static function compareFunction ($a, $b) {
        return $a[1] - $b[1];
    }
    
    private static function parseExpression (&$input) {
        if ($input[0] != '{') return array ();
        $input = ltrim (substr ($input, 1));
        $expr = '';
        $parstack = array ();
        $subexprs = array ();
        $free = array ();
        $last = 0;
        $i = 0;
        $rxok = true;
        $skip = false;
        for (; ! empty ($input); $i++) {
            $c = $input[0];
            $input = substr ($input, 1);
            if (empty ($parstack) && $c == '}') break;
            $expr .= $c;
            if ($skip) { $skip = false; continue; }
            $skip = false;
            if ($parstack[0][1] == '\'') {
                if ($c == '\\') {
                    $skip = true;
                    continue;
                }
                if ($c == '\'') {
                    list ($j,) = array_shift ($parstack);
                    $subexprs[] = array ('\'', $j, $i);
                    $last = $i + 1;
                    $rxok = false;
                }
                continue;
            }
            if ($parstack[0][1] == '"') {
                if ($c == '\\') {
                    $skip = true;
                    continue;
                }
                if ($c == '"') {
                    list ($j,) = array_shift ($parstack);
                    $subexprs[] = array ('"', $j, $i);
                    $last = $i + 1;
                    $rxok = false;
                }
                continue;
            }
            if ($parstack[0][1] == '/') {
                if ($c == '\\') {
                    $skip = true;
                    continue;
                }
                if ($c == '/') {
                    list ($j,) = array_shift ($parstack);
                    $subexprs[] = array ('/', $j, $i);
                    $last = $i + 1;
                    $rxok = false;
                }
                continue;
            }
            if ($c == ')') {
                if ($parstack[0][1] != '(') throw new Exception ('Parentheses mismatch: ' . $parstack[0][1] . ' and )');
                list ($j,) = array_shift ($parstack);
                $subexprs[] = array ('(', $j, $i);
                $last = $i + 1;
                $rxok = false;
                continue;
            }
            if ($c == ']') {
                if ($parstack[0][1] != '[') throw new Exception ('Parentheses mismatch: ' . $parstack[0][1] . ' and ]');
                list ($j,) = array_shift ($parstack);
                $subexprs[] = array ('[', $j, $i);
                $last = $i + 1;
                $rxok = false;
                continue;
            }
            if ($c == '}') {
                if ($parstack[0][1] != '{') throw new Exception ('Parentheses mismatch: ' . $parstack[0][1] . ' and }');
                list ($j,) = array_shift ($parstack);
                $subexprs[] = array ('{', $j, $i);
                $last = $i + 1;
                $rxok = false;
                continue;
            }
            if (preg_match ('~[(\[{\'"]~', $c) || $rxok && $c == '/') {
                if ($last < $i && empty ($parstack)) $free[] = array ($last, $i - 1);
                array_unshift ($parstack, array ($i, $c));
                continue;
            }
            if (! preg_match ('/\s/', $c)) $rxok = ! preg_match ('/\w/', $c);
        }
        if ($last < $i) $free[] = array ($last, $i - 1);
        usort ($subexprs, __CLASS__ . '::compareFunction');
        if (false === ($return = self::buildExpressionNodes ($expr, $subexprs, $free)))
            throw new Exception ('Error in expression: ' . $expr);
        return $return;
    }
}

?>
