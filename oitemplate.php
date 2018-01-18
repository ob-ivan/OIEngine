<?php
require_once ('utils.php');
require_once ('oitparse.php');
require_once ('oitcompile.php');
require_once ('oiscache.php');
require_once ('oiconfig.php');

class OITemplate {
    private $dirname;
    private $parsed;
    private $cache;
    
    public function __construct ($config = array ()) {
        if (! $config instanceof OIConfig) $config = new OIConfig ($config);
        if (0 < strlen (trim ($this->dirname = $config->getValue('dirname', 'templates')))) $this->dirname .= '/';
        $cc = $config->getSection ('cache');
        $this->cache = new OISCache (array (
            'cache_on'   => $cc->getValue ('on', true),
            'repository' => $cc->getValue ('dir', 'oit'),
        ));
        $this->parsed = array ();
    }
    
    public function clearCache ($sure = false) {
        if ($sure) $this->cache->clearCache ($sure);
    }
    
    public function loadTemplates ($tname) {
        $fn = $this->dirname . $tname . '.oit';
        if (! file_exists ($fn)) throw new Exception ('Template file does not exist: ' . $tname);
        if ($this->cache->stored ($tname))
            $parsed = $this->cache->read ($tname);
        else {
            try {
                $input = file_get_contents ($fn);
                $parsed = OITParse::parseInput ($input);
                $this->cache->write ($tname, $parsed);
            }
            catch (Exception $e) {
                throw new Exception ('OITemplate parse error in ' . $fn . ': ' . $e->getMessage());
            }
        }
        foreach ($parsed as $p) $this->parsed[] = $p;
    }
    
    public function makeOutput ($data = array ()) {
        try {
            $return = OITCompile::compile ($this->parsed, $data);
        }
        catch (Exception $e) {
            throw new Exception ('OITemplate compilation error: ' . $e->getMessage());
        }
    }
}
?>
