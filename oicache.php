<?php

require_once ('oiconfig.php');

/**
 * usage:
 * 
 *  $cache = new OICache ($cache_config);
 *  ...
 *  $data = $cache->read ($dataid = 'unique_data_identifier');
 *  if ($cache->locked()) {
 *      try {
 *          $data = calculate_data (...);
 *          $cache->write ($data);
 *      }
 *      catch (Exception $e) {
 *          $cache->cancel ();
 *          handle_exception ($e);
 *      }
 *  }
 *  make_usage_of ($data);
 *
 */
class OICache {
    private $repository;
    private $storage_dir;
    private $locked;
    private $cache_on;
    private $timeout;
    private $retries;
    private $sleep;
    private $datafn;
    private $lockfn;
    
    /**
     * @param $config array includes following parameters:
     *      cache_on    bool    whether class is used or not
     *      repository  string  directory name for storing cache values
     *      timeout     float   time in seconds given for a single process to finish calculations.
     *                          if this time has elapsed, other process tries to calculate the data.
     *      sleep       float   interval in seconds to check if data was written by another process
     *      retries     int     upper bound for data-was-written check
     */
    public function __construct ($config = array ()) {
        throw new Exception ('OICache class was disabled due to bad performance. Use OISCache instead.');
        if (! $config instanceof OIConfig) $config = new OIConfig ($config);
        if ($this->cache_on = $config->getValue ('cache_on', true)) {
            if (strlen (trim ($this->repository = preg_replace ('~[^\w/]~', '', $config->getValue ('repository', 'default')))) == 0) 
                throw new Exception ('Repository name is empty');
            // эта директория должна быть недоступна для скачивания
            $this->storage_dir = dirname(__FILE__) . '/cache';
            $this->timeout = floor ($config->getValue ('timeout', 0.1) * 1000000);
            $this->sleep = floor ($config->getValue ('sleep', 0.01) * 1000000);
            $this->retries = $config->getValue ('retries', 20);
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
    
    public function locked () {
        return $this->locked;
    }
    
    private function timestamp () {
        return date ('YmdHisu');
    }
    
    // returns data if it is in cache, sets lock otherwise.
    // if lock is obtained, calculate the data and store it with write(),
    // this will release the lock.
    public function read (&$dataid) {
        // if cache is not being used, act as if it is always empty
        if (! $this->cache_on) {
            $this->locked = true;
            return false;
        }
        $md5 = md5 ($dataid);
        $dir = $this->storage_dir . '/' . $this->repository . '/' . $md5[0] . '/' . $md5[1] . '/' . $md5[2];
        if (! file_exists ($dir)) if (! mkdir ($dir, 0755, 1)) throw new Exception ('Could not create directory: ' . $dir);
        $pref = $dir . '/' . substr ($md5, 3) . '.';
        if (file_exists ($this->datafn = $pref . 'data'))
            if ($this->wait ())
                return unserialize (file_get_contents ($this->datafn));
        $this->lockfn = $pref . 'lock';
        while (true) {
            pre ($this->lockfn);
            die;
            if (file_exists ($this->lockfn)) {
                if ($this->wait ())
                    return unserialize (file_get_contents ($this->datafn));
                else
                    continue;
            }
            // выборы самого быстрого процесса.
            $f = fopen ($this->lockfn, 'ab');
            fputs ($f, ($rnd = uniqid ()) . ' ' . $this->timestamp() . ' write' . "\n");
            fclose ($f);
            $f = fopen ($this->lockfn, 'rb');
            $s = fgets ($f, 1024);
            fclose ($f);
            preg_match ('/^(\d+) /', $s, $matches);
            if ($matches[1] == $rnd) {
                // победили
                $this->locked = true;
                return false;
            }
            // ждём, что победитель отпустить лок, или что его выкинут по таймауту.
            if ($this->wait ())
                return unserialize (file_get_contents ($this->datafn));
        }
    }
    
    // return true if lock was released by other process.
    // return false if it has timed out
    private function wait () {
        if (! $this->cache_on) return true;
        for ($i = 0; $i < $this->retries; $i++) {
            // другой процесс обнаружил таймаут и убил файл. бросаемся на выборы лидера.
            if (! file_exists ($this->lockfn)) return false;
            $f = fopen ($this->lockfn, 'rb');
            $s = fgets ($f, 1024);
            fclose ($f);
            preg_match ('/^(\d+) (\d+) (\w+)$/', $s, $matches);
            if ($matches[3] == 'success') return true;
            if ($this->timestamp() > intval ($matches[2]) + $this->timeout) {
                @unlink ($this->lockfn);
                return false;
            }
            usleep ($this->sleep);
        }
        throw new Exception ('Waited for lock release at "' . $this->lockfn . '" and timed out');
    }
    
    public function write (&$data) {
        if ($this->cache_on) {
            $f = fopen ($this->datafn, 'wb');
            fputs ($f, serialize ($data));
            fclose ($f);
            // release lock on success
            $f = fopen ($this->lockfn, 'wb');
            fputs ($f, '0 0 success');
            fclose ($f);
        }
        $this->locked = false;
    }
    
    // release lock on fail
    public function cancel () {
        if ($this->cache_on) @unlink ($this->lockfn);
        $this->locked = false;
    }
}
?>
