<?

require_once ('utils.php');

class OITCompile {

    public static function compile (&$code, &$data) {
        $consts = array ();
        $tems = array ();
        $main = false;
        foreach ($code as $key => $item) {
            if ($item[OIT_KEY_TYPE] == OIT_TYPE_VARIABLE) {
                $vars = array ();
                $args = array ();
                foreach ($item[OIT_KEY_CHILD] as $child)
                    foreach (self::compileNode ($child, $data, $args, $consts, $vars, $tems) as $add)
                        $consts[$item[OIT_KEY_NAME]][] = $add;
            }
            elseif ($item[OIT_KEY_TYPE] == OIT_TYPE_TEMPLATE) {
                if ($item[OIT_KEY_NAME] == '_')
                    $main = &$code[$key];
                else
                    $tems[$item[OIT_KEY_NAME]] = &$code[$key];
            }
        }
        if ($main) {
            $vars = array ();
            $args = array ();
            foreach (self::compileNode ($main, $data, $args, $consts, $vars, $tems) as $node)
                $output .= self::toOutput ($node);
            return $output;
        }
        throw new Exception ('Template named _ was not found!');
    }
    
    private static function compileNode (&$node, &$data, &$args, &$consts, &$vars, &$tems) {
        if (! is_array ($node)) throw new Exception ('$node is not an array');
        if (! array_key_exists (OIT_KEY_TYPE, $node)) throw new Exception ('$node does not contain type');
        $return = array ();
        switch ($node[OIT_KEY_TYPE]) {
            case OIT_TYPE_TEMPLATE:
                $new_args = array ();
                if (array_key_exists (OIT_KEY_PARAM, $node)) {
                    foreach ($node[OIT_KEY_PARAM] as $defarg) {
                        $found = false;
                        foreach ($args as $argname => $argvalue) {
                            if ($argname == $defarg[OIT_KEY_NAME]) {
                                // arguments are passed already compiled
                                $new_args[$argname] = $argvalue;
                                $found = true;
                                break;
                            }
                        }
                        if (! $found) {
                            // compile default argument values
                            $new_args[$defarg[OIT_KEY_NAME]] = array ();
                            $empty1 = $empty2 = array ();
                            if (array_key_exists (OIT_KEY_CHILD, $defarg))
                                foreach ($defarg[OIT_KEY_CHILD] as $child)
                                    foreach (self::compileNode ($child, $data, $empty1, $consts, $empty2, $tems) as $add)
                                        $new_args[$defarg[OIT_KEY_NAME]][] = $add;
                        }
                    }
                }
                $new_vars = array ();
                if (array_key_exists (OIT_KEY_CHILD, $node))
                    foreach ($node[OIT_KEY_CHILD] as $child)
                        foreach (self::compileNode ($child, $data, $new_args, $consts, $new_vars, $tems) as $add)
                            $return[] = $add;
            break;
            case OIT_TYPE_VARIABLE:
                $children = array ();
                if (array_key_exists (OIT_KEY_CHILD, $node))
                    foreach ($node[OIT_KEY_CHILD] as $child)
                        foreach (self::compileNode ($child, $data, $args, $consts, $vars, $tems) as $add)
                            $children[] = $add;
                $vars[$node[OIT_KEY_NAME]] = $children;
            break;
            case OIT_TYPE_ATTRIBUTE: case OIT_TYPE_STYLE:
                $children = array ();
                if (array_key_exists (OIT_KEY_CHILD, $node))
                    foreach ($node[OIT_KEY_CHILD] as $child)
                        foreach (self::compileNode ($child, $data, $args, $consts, $vars, $tems) as $add)
                            $children[] = $add;
                $return[] = array (OIT_KEY_TYPE => $node[OIT_KEY_TYPE], OIT_KEY_NAME => $node[OIT_KEY_NAME], OIT_KEY_CHILD => $children);
            break;
            case OIT_TYPE_IF:
                $test = self::toBoolean (self::compileNode ($node[OIT_KEY_TEST], $data, $args, $consts, $vars, $tems));
                if ($test[OIT_KEY_VALUE] == OIT_VALUE_TRUE && array_key_exists (OIT_KEY_CHILD, $node))
                    foreach ($node[OIT_KEY_CHILD] as $child)
                        foreach (self::compileNode ($child, $data, $args, $consts, $vars, $tems) as $add)
                            $return[] = $add;
            break;
            case OIT_TYPE_WHILE:
                while (true) {
                    $test = self::toBoolean (self::compileNode ($node[OIT_KEY_TEST], $data, $args, $consts, $vars, $tems));
                    if ($test[OIT_KEY_VALUE] == OIT_VALUE_FALSE) break;
                    if (array_key_exists (OIT_KEY_CHILD, $node))
                        foreach ($node[OIT_KEY_CHILD] as $child)
                            foreach (self::compileNode ($child, $data, $args, $consts, $vars, $tems) as $add)
                                $return[] = $add;
                }
            break;
            case OIT_TYPE_FOR:
                if (array_key_exists (OIT_KEY_CHILD, $node)) {
                    $test = self::compileNode ($node[OIT_KEY_TEST], $data, $args, $consts, $vars, $tems);
                    foreach ($test[0][OIT_KEY_VALUE] as $elem)
                        foreach ($node[OIT_KEY_CHILD] as $child)
                            foreach (self::compileNode ($child, $elem[OIT_KEY_VALUE], $args, $consts, $vars, $tems) as $add)
                                $return[] = $add;
                }
            break;
            case OIT_TYPE_CHOOSE:
                if (array_key_exists (OIT_KEY_CHILD, $node)) {
                    foreach ($node[OIT_KEY_CHILD] as $case) {
                        if ($case[OIT_KEY_TYPE] == OIT_TYPE_WHEN) {
                            $test = self::toBoolean (self::compileNode ($case[OIT_KEY_TEST], $data, $args, $consts, $vars, $tems));
                            if ($test[OIT_KEY_VALUE] == OIT_VALUE_TRUE && array_key_exists (OIT_KEY_CHILD, $case)) {
                                foreach ($case[OIT_KEY_CHILD] as $child)
                                    foreach (self::compileNode ($child, $data, $args, $consts, $vars, $tems) as $add) 
                                        $return[] = $add;
                                break;
                            }
                        }
                        elseif ($case[OIT_KEY_TYPE] == OIT_TYPE_OTHERWISE) {
                            if (array_key_exists (OIT_KEY_CHILD, $case))
                                foreach ($case[OIT_KEY_CHILD] as $child)
                                    foreach (self::compileNode ($child, $data, $args, $consts, $vars, $tems) as $add) 
                                        $return[] = $add;
                            break;
                        }
                    }
                }
            break;
            case OIT_TYPE_STRING: case OIT_TYPE_NUMBER:
                $return[] = $node;
            break;
            case OIT_TYPE_DATA:
                $name = $node[OIT_KEY_NAME];
                // priority is from local to global: vars, args, consts, data.
                // vars, args, consts contain raw output from compileNode
                if (array_key_exists ($name, $vars)) {
                    foreach ($vars[$name] as $add)
                        $return[] = $add;
                }
                elseif (array_key_exists ($name, $args)) {
                    foreach ($args[$name] as $add)
                        $return[] = $add;
                }
                elseif (array_key_exists ($name, $consts)) {
                    foreach ($consts[$name] as $add)
                        $return[] = $add;
                }
                else {
                    if (is_array ($data) && array_key_exists ($name, $data))
                        $value = $data[$name];
                    elseif ($name == '_')
                        $value = $data;
                        
                    if (is_array ($value)) {
                        $array = array ();
                        foreach ($value as $item)
                            $array[] = array (OIT_KEY_TYPE => self::getPhpType ($item), OIT_KEY_VALUE => $item);
                        $return[] = array (OIT_KEY_TYPE => OIT_TYPE_ARRAY, OIT_KEY_VALUE => $array);
                    }
                    else
                        $return[] = array (OIT_KEY_TYPE => self::getPhpType ($value), OIT_KEY_VALUE => $value);
                }
            break;
            case OIT_TYPE_CALL:
                throw new Exception ('User functions are not implemented yet');
            break;
            case OIT_TYPE_TAG:
                if (array_key_exists ($node[OIT_KEY_NAME], $tems)) { // template
                    // compile passed arguments
                    $new_args = array ();
                    foreach ($node[OIT_KEY_ATTRS] as $attr) {
                        $new_args[$attr[OIT_KEY_NAME]] = array ();
                        if (array_key_exists (OIT_KEY_CHILD, $attr))
                            foreach ($attr[OIT_KEY_CHILD] as $child)
                                foreach (self::compileNode ($child, $data, $args, $consts, $vars, $tems) as $add)
                                    $new_args[$attr[OIT_KEY_NAME]][] = $add;
                    }
                    // create local scope and make a recursive call.
                    $new_vars = array ();
                    foreach (self::compileNode ($tems[$node[OIT_KEY_NAME]], $data, $new_args, $consts, $new_vars, $tems) as $add)
                        $return[] = $add;
                }
                else { // html tag
                    $attributes = array ();
                    if (array_key_exists (OIT_KEY_ID, $node)) {
                        $attributes[] = array (
                            OIT_KEY_TYPE => OIT_TYPE_ATTRIBUTE, 
                            OIT_KEY_NAME => 'id', 
                            OIT_KEY_CHILD => array (array (OIT_KEY_TYPE => OIT_TYPE_STRING, OIT_KEY_VALUE => $node[OIT_KEY_ID]))
                        );
                    }
                    if (array_key_exists (OIT_KEY_CLASS, $node)) {
                        $attributes[] = array (
                            OIT_KEY_TYPE => OIT_TYPE_ATTRIBUTE, 
                            OIT_KEY_NAME => 'class', 
                            OIT_KEY_CHILD => array (array (OIT_KEY_TYPE => OIT_TYPE_STRING, OIT_KEY_VALUE => implode (' ', $node[OIT_KEY_CLASS])))
                        );
                    }
                    if (array_key_exists (OIT_KEY_ATTRS, $node))
                        foreach ($node[OIT_KEY_ATTRS] as $attr)
                            foreach (self::compileNode ($attr, $data, $args, $consts, $vars, $tems) as $add)
                                $attributes[] = $add;
                    $styles = array ();
                    if (array_key_exists (OIT_KEY_STYLE, $node))
                        foreach ($node[OIT_KEY_STYLE] as $style)
                            foreach (self::compileNode ($style, $data, $args, $consts, $vars, $tems) as $add)
                                $styles[] = $add;
                    $children = array ();
                    if (array_key_exists (OIT_KEY_CHILD, $node)) {
                        foreach ($node[OIT_KEY_CHILD] as $child)
                            foreach (self::compileNode ($child, $data, $args, $consts, $vars, $tems) as $add)
                                switch ($add[OIT_KEY_TYPE]) {
                                    case OIT_TYPE_ATTRIBUTE: $attributes[] = $add; break;
                                    case OIT_TYPE_STYLE:     $styles[]     = $add; break;
                                    default:                 $children[]   = $add;
                                }
                    }
                    $return[] = array (
                        OIT_KEY_TYPE  => OIT_TYPE_TAG, 
                        OIT_KEY_NAME  => $node[OIT_KEY_NAME], 
                        OIT_KEY_CLASS => $classes,
                        OIT_KEY_ATTRS => $attributes,
                        OIT_KEY_STYLE => $styles,
                        OIT_KEY_CHILD => $children,
                    );
                }
            break;
            case OIT_TYPE_OPERATOR:
                switch ($node[OIT_KEY_NAME]) {
                    case OIT_OPERATOR_OR:
                        $result = self::toBoolean (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        if ($result[OIT_KEY_VALUE] == OIT_VALUE_TRUE) 
                            $return[] = $result;
                        else
                            $return[] = self::toBoolean (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                    break;
                    case OIT_OPERATOR_AND:
                        $result = self::toBoolean (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        if ($result[OIT_KEY_VALUE] == OIT_VALUE_FALSE) 
                            $return[] = $result;
                        else
                            $return[] = self::toBoolean (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                    break;
                    case OIT_OPERATOR_NOT_EQUAL: case OIT_OPERATOR_EQUAL:
                        $left = self::toString (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        $right = self::toString (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                        $return[] = array (
                            OIT_KEY_TYPE => OIT_TYPE_BOOLEAN, 
                            OIT_KEY_VALUE => ($left[OIT_KEY_VALUE] == $right[OIT_KEY_VALUE] xor $node[OIT_KEY_NAME] == OIT_OPERATOR_EQUAL) ? 
                                OIT_VALUE_FALSE : OIT_VALUE_TRUE
                        );
                    break;
                    case OIT_OPERATOR_LEQ: case OIT_OPERATOR_GREATER:
                        $left = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        $right = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                        $return[] = array (
                            OIT_KEY_TYPE => OIT_TYPE_BOOLEAN, 
                            OIT_KEY_VALUE => ($left[OIT_KEY_VALUE] > $right[OIT_KEY_VALUE] xor $node[OIT_KEY_NAME] == OIT_OPERATOR_GREATER) ? 
                                OIT_VALUE_FALSE : OIT_VALUE_TRUE
                        );
                    break;
                    case OIT_OPERATOR_GREQ: case OIT_OPERATOR_LESS:
                        $left = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        $right = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                        $return[] = array (
                            OIT_KEY_TYPE => OIT_TYPE_BOOLEAN, 
                            OIT_KEY_VALUE => ($left[OIT_KEY_VALUE] < $right[OIT_KEY_VALUE] xor $node[OIT_KEY_NAME] == OIT_OPERATOR_LESS) ? 
                                OIT_VALUE_FALSE : OIT_VALUE_TRUE
                        );
                    break;
                    case OIT_OPERATOR_BIN_PLUS:
                        $left = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        $right = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                        $return[] = array (OIT_KEY_TYPE => OIT_TYPE_NUMBER, OIT_KEY_VALUE => $left[OIT_KEY_VALUE] + $right[OIT_KEY_VALUE]);
                    break;
                    case OIT_OPERATOR_BIN_MINUS:
                        $left = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        $right = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                        $return[] = array (OIT_KEY_TYPE => OIT_TYPE_NUMBER, OIT_KEY_VALUE => $left[OIT_KEY_VALUE] - $right[OIT_KEY_VALUE]);
                    break;
                    case OIT_OPERATOR_CONCAT:
                        $left = self::toString (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        $right = self::toString (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                        $return[] = array (OIT_KEY_TYPE => OIT_TYPE_STRING, OIT_KEY_VALUE => $left[OIT_KEY_VALUE] . $right[OIT_KEY_VALUE]);
                    break;
                    case OIT_OPERATOR_MULTIPLY:
                        $left = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        if ($result[OIT_KEY_VALUE] == 0)
                            $return[] = $result;
                        else {
                            $right = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                            $return[] = array (OIT_KEY_TYPE => OIT_TYPE_NUMBER, OIT_KEY_VALUE => $left[OIT_KEY_VALUE] * $right[OIT_KEY_VALUE]);
                        }
                    break;
                    case OIT_OPERATOR_DIVIDE:
                        $left = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        if ($result[OIT_KEY_VALUE] == 0)
                            $return[] = $result;
                        else {
                            $right = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                            if ($right[OIT_KEY_VALUE] == 0) throw new Exception ('Division by zero!');
                            $return[] = array (OIT_KEY_TYPE => OIT_TYPE_NUMBER, OIT_KEY_VALUE => $left[OIT_KEY_VALUE] / $right[OIT_KEY_VALUE]);
                        }
                    break;
                    case OIT_OPERATOR_MODULO:
                        $left = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        if ($result[OIT_KEY_VALUE] == 0)
                            $return[] = $result;
                        else {
                            $right = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                            if ($right[OIT_KEY_VALUE] == 0) throw new Exception ('Division by zero!');
                            $return[] = array (OIT_KEY_TYPE => OIT_TYPE_NUMBER, OIT_KEY_VALUE => $left[OIT_KEY_VALUE] % $right[OIT_KEY_VALUE]);
                        }
                    break;
                    case OIT_OPERATOR_RANGE:
                        $left = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        $right = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][1], $data, $args, $consts, $vars, $tems));
                        $range = array ($left);
                        if ($left[OIT_KEY_VALUE] < $right[OIT_KEY_VALUE]) {
                            while ($left[OIT_KEY_VALUE] < $right[OIT_KEY_VALUE]) {
                                $left[OIT_KEY_VALUE]++;
                                $range[] = $left;
                            }
                        }
                        elseif ($left[OIT_KEY_VALUE] > $right[OIT_KEY_VALUE]) {
                            while ($left[OIT_KEY_VALUE] > $right[OIT_KEY_VALUE]) {
                                $left[OIT_KEY_VALUE]++;
                                $range[] = $left;
                            }
                        }
                        $return[] = array (OIT_KEY_TYPE => OIT_TYPE_ARRAY, OIT_KEY_VALUE => $range);
                    break;
                    case OIT_OPERATOR_UN_PLUS:
                        $return[] = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                    break;
                    case OIT_OPERATOR_UN_MINUS:
                        $result = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        $result[OIT_KEY_VALUE] *= -1;
                        $return[] = $result;
                    break;
                    case OIT_OPERATOR_NOT:
                        $result = self::toNumber (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        $result[OIT_KEY_VALUE] = $result[OIT_KEY_VALUE] == OIT_VALUE_TRUE ? OIT_VALUE_FALSE : OIT_VALUE_TRUE;
                        $return[] = $result;
                    break;
                    case OIT_OPERATOR_PREG_MATCH:
                        $left = self::toString (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        $right = self::toString (self::compileNode ($node[OIT_KEY_CHILD][0], $data, $args, $consts, $vars, $tems));
                        preg_match ($right, $left, $matches);
                        $return[] = array (OIT_KEY_TYPE => OIT_TYPE_ARRAY, OIT_KEY_VALUE => $matches);
                    break;
                    default:
                        throw new Exception (__METHOD__ . ': unknown operator ' . $node[OIT_KEY_NAME]);
                    break;
                }
            break;
            default:
                throw new Exception (__METHOD__ . ': node type ' . $node[OIT_KEY_TYPE] . ' cannot be handled!');
            break;
        }
        return $return;
    }
    
    private static function toOutput ($node) {
        switch ($node[OIT_KEY_TYPE]) {
            case OIT_TYPE_TAG:
                $return = '<' . $node[OIT_KEY_NAME];
                if (count ($node[OIT_KEY_CLASS]) > 0) {
                    $return .= ' class="';
                    $classes = array ();
                    foreach ($node[OIT_KEY_CLASS] as $class)
                        $classes[] = $class[OIT_KEY_CLASS];
                }
                foreach ($node[OIT_KEY_ATTRS] as $attr)
                    $return .= ' ' . self::toOutput ($attr);
                if (count ($node[OIT_KEY_STYLE]) > 0) {
                    $return .= ' style="';
                    $styles = array ();
                    foreach ($node[OIT_KEY_STYLE] as $style)
                        $styles[] = self::toOutput ($style);
                    $return .= self::escapeAttribute (implode (';', $styles)) . '"';
                }
                if (count ($node[OIT_KEY_CHILD]) > 0 || preg_match ('/^(script|textarea)$/i', $node[OIT_KEY_NAME])) {
                    $return .= '>';
                    foreach ($node[OIT_KEY_CHILD] as $child)
                        $return .= self::toOutput ($child);
                    return $return . '</' . $node[OIT_KEY_NAME] . '>';
                }
                else
                    return $return . '/>';
            case OIT_TYPE_STRING: case OIT_TYPE_NUMBER: case OIT_TYPE_REGEXP:
                return $node[OIT_KEY_VALUE];
            case OIT_TYPE_BOOLEAN:
                return $node[OIT_KEY_VALUE] == OIT_VALUE_TRUE ? 'true' : 'false';
            case OIT_TYPE_ATTRIBUTE:
                $return = $node[OIT_KEY_NAME];
                if (count ($node[OIT_KEY_CHILD]) > 0) {
                    $return .= '="';
                    foreach ($node[OIT_KEY_CHILD] as $child)
                        $return .= self::escapeAttribute (self::toOutput ($child));
                    $return .= '"';
                }
                return $return;
            case OIT_TYPE_STYLE:
                if (count ($node[OIT_KEY_CHILD]) > 0) {
                    $return = $node[OIT_KEY_NAME]. ':';
                    foreach ($node[OIT_KEY_CHILD] as $child)
                        $return .= self::toOutput ($child);
                }
                return $return;
        }
        throw new Exception (__METHOD__ . ': node type ' . $node[OIT_KEY_TYPE] . ' cannot be handled!');
    }
    
    private static function toNumber ($nodes) {
        $return = self::toString ($nodes);
        $return[OIT_KEY_TYPE] = OIT_TYPE_NUMBER;
        $return[OIT_KEY_VALUE] = floatval ($return[OIT_KEY_VALUE]);
        return $return;
    }
    
    private static function toString ($nodes) {
        $value = '';
        foreach ($nodes as $node) $value .= self::toOutput ($node);
        return array (OIT_KEY_TYPE => OIT_TYPE_STRING, OIT_KEY_VALUE => $value);
    }
    
    private static function toBoolean ($nodes) {
        $return = self::toString ($nodes);
        $return[OIT_KEY_TYPE] = OIT_TYPE_BOOLEAN;
        if (empty ($return[OIT_KEY_VALUE]) || $return[OIT_KEY_VALUE] == 'false')
            $return[OIT_KEY_VALUE] = OIT_VALUE_FALSE;
        else
            $return[OIT_KEY_VALUE] = OIT_VALUE_TRUE;
        return $return;
    }
    
    private static function getPhpType ($value) {
        if (is_bool    ($value)) return OIT_TYPE_BOOLEAN;
        if (is_numeric ($value)) return OIT_TYPE_NUMBER;
        if (is_string  ($value)) return OIT_TYPE_STRING;
        if (is_array   ($value)) return OIT_TYPE_ARRAY;
    }
    
    private static function escapeAttribute ($string) {
        return str_replace (array ('"'), array ('&quot;'), $string);
    }
}

?>
