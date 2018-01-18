<?php

/**
 * Config loader. Part of Ob-Ivan's Engine.
 */
class OIConfig {
    
    private $data;
    
    public function __construct ($fn = '') { // file name
        $this->data = array ();
        if (! empty ($fn)) {
            if (is_array ($fn))
                $this->data = $fn;
            elseif (is_string ($fn) && file_exists ($fn)) {
                $f = fopen ($fn, 'rb');
                while (! feof ($f)) {
                    $l = fgets ($f, 1024);
                    if (preg_match ('/^\s*[#;]/', $l)) continue;
                    if (preg_match ('/^([\w.-]+)\s*=\s*/', $l, $m, PREG_OFFSET_CAPTURE)) {
                        $p = $m[1][0]; $l = substr ($l, $m[0][1]); $v = '';
                        if (preg_match ('/^\'([^\']*)\'/', $l, $m)) $v = $m[1];
                        elseif (preg_match ('/^"([^"]*)"/', $l, $m)) $v = $m[1];
                        elseif (preg_match ('/^[^\s]*/', $l, $m)) $v = $m[0];
                        $this->data[$p] = $v;
                    }
                }
                fclose ($f);
            }
        }
    }
    
    public function getValue ($pn, $default = '') { // parameter name
        if (array_key_exists ($pn, $this->data)) return $this->data[$pn];
        return $default;
    }
    
    public function getSection ($sn) { // section name
        $ret = array (); $l = strlen ($sn .= '.');
        foreach ($this->data as $k => $v) if (substr ($k, 0, $l) == $sn) $ret[substr ($k, $l)] = $v;
        return new OIConfig ($ret);
    }
}

?>
