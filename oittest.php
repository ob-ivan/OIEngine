<?
    header ('Content-Type: text/html; charset=utf-8');
    $load = sys_getloadavg();
    if ($load[0] > 20) {
        header ('HTTP/1.1 503 Too busy, try again later');
        die ('Server is busy, load = (' . implode (', ', $load) . ')');
    }
    require_once ('utils.php');
    require_once ('oitemplate.php');
    require_once ('oiconfig.php');
    $config = new OIConfig ('oittest.conf');
    $oit = new OITemplate ($config);
    $oit->clearCache (ake ('nocache', $_GET) && $_GET['nocache'] == 1);
    $oit->loadTemplates ('processlist');
    print $oit->makeOutput ();
?>
