<?php

class OIDB {
    private $db;
    private $cache;
    
    public function __construct ($config = array ()) {
        if (! class_exists ('mysqli')) throw new Exception ('mysqli extension is required');
        if (! is_array ($config)) throw new Exception ('Config is not array');
        $this->db = new mysqli (
            $config['host'],
            $config['username'],
            $config['passwd'],
            $config['dbname']
        );
        $this->db->query ('SET NAMES ' . (array_key_exists ('encoding', $config) ? $config['encoding'] : 'UTF8'));
        $cc = array_key_exists ('cache', $config) ? $config['cache'] : array ();
        if (! is_array ($cc)) throw new Exception ('Cache config is not array');
        $this->cache = new OICache (array (
            'cache_on'   => array_key_exists ('on',      $cc) ? $cc['on']      : true,
            'repository' => array_key_exists ('dir',     $cc) ? $cc['dir']     : 'oidb',
            'timeout'    => array_key_exists ('timeout', $cc) ? $cc['timeout'] : 0.5,
            'sleep'      => array_key_exists ('sleep',   $cc) ? $cc['sleep']   : 0.15,
            'retries'    => array_key_exists ('retries', $cc) ? $cc['retries'] : 10,
        ));
    }
    
    public function call ($procname) {
        $query = array ();
        for ($i = 1, $n = func_num_args (); $i < $n; $i++)
            $query[] = '"' . addslashes (func_get_arg ($i)) . '"';
        $data = $this->cache->read ($query = 'call ' . $procname . '(' . implode (', ', $query) . ')');
        if ($this->cache->locked()) {
            try {
                $res = $this->db->query ($query);
                $data = array ();
                while ($row = $res->fetch_assoc()) $data[] = $row;
                if ($this->db->more_results()) while ($this->db->next_result()); $res->free_result (); unset ($res);
                $this->cache->write ($data);
            }
            catch (Exception $e) {
                $this->cache->cancel ();
                throw $e;
            }
        }
        return $data;
    }
}

?>
