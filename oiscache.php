<?php

require_once ('oiconfig.php');

/**
 * Simple Cache
 *
 * usage:
 * 
 *  $cache = new OISCache ($cache_config);
 *  ...
 *  if ($cache->stored ($dataid = 'unique_data_identifier'))
 *      $data = $cache->read ($dataid);
 *  else {
 *      $data = calculate_data (...);
 *      $cache->write ($data);
 *  }
 *  make_usage_of ($data);
 *
 */
class OISCache {
    private $repository;
    private $storage_dir;
    private $cache_on;
    private $datafn;
    
    /**
     * @param $config array includes following parameters:
     *      cache_on    bool    whether class is used or not
     *      repository  string  directory name for storing cache values
     */
    public function __construct ($config = array ()) {
        if (! $config instanceof OIConfig) $config = new OIConfig ($config);
        if ($this->cache_on = $config->getValue ('cache_on', true)) {
            if (strlen (trim ($this->repository = preg_replace ('~[^\w/]~', '', $config->getValue ('repository', 'default')))) == 0) 
                throw new Exception ('Repository name is empty');
            // эта директория должна быть недоступна для скачивания
            $this->storage_dir = dirname(__FILE__) . '/cache';
            $this->datafn = array ();
        }
        $this->locked = false;
    }
    
    private static function removeDir ($path) {
        if (is_dir($path)) {
            if ($dh = opendir($path)) {
                while (false !== ($sf = readdir($dh))) {
                    if ($sf == '.' || $sf == '..') continue;
                    if (! self::removeDir ($np = $path . '/' . $sf)) throw new Exception ($np . ' could not be deleted');
                }
                closedir($dh);
            }
            return rmdir($path);
        }
        return unlink($path);
    }
    
    public function clearCache ($sure = false) {
        if ($sure) self::removeDir ($this->storage_dir . '/' . $this->repository);
    }
    
    public function stored ($dataid) {
        if (! $this->cache_on) return false;
        if (! array_key_exists ($dataid, $this->datafn)) {
            $md5 = md5 ($dataid);
            $dir = $this->storage_dir . '/' . $this->repository . '/' . $md5[0] . '/' . $md5[1] . '/' . $md5[2];
            if (! file_exists ($dir)) if (! mkdir ($dir, 0755, 1)) throw new Exception ('Could not create directory: ' . $dir);
            $this->datafn[$dataid] = $dir . '/' . substr ($md5, 3) . '.data';
        }
        return file_exists ($this->datafn[$dataid]);
    }
    
    // returns data if it is stored.
    // throws exception on incorrect use.
    public function read ($dataid) {
        if (! $this->cache_on) throw new Exception ('Cache is off, please don\'t try to read data');
        return unserialize (file_get_contents ($this->datafn[$dataid]));
    }
    
    public function write ($dataid, &$data) {
        if ($this->cache_on) {
            $f = fopen ($this->datafn[$dataid], 'wb');
            fputs ($f, serialize ($data));
            fclose ($f);
        }
    }
}
?>
