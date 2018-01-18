<?php
function pre () {
    switch (func_num_args ()) {
        case 0: print '<pre>----</pre>'; return;
        case 1: print '<pre>'; print_r ($v = func_get_arg(0)); print '</pre>'; return $v;
        case 2: print '<pre>' . func_get_arg(0) . ' = '; print_r ($v = func_get_arg(1)); print '</pre>'; return $v;
    }
}
function pre_ () {
    print '<pre>';
    for ($i = 0; $i < func_num_args(); $i++) print_r (func_get_arg ($i));
    print '</pre>';
}
function ake ($k, $a) { return array_key_exists ($k, $a); }
?>
