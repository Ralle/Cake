<?php

class Cake
{
  public static $currentDir;
  public static $rootDir;
  public static $confPath;
  public static $relativeDir;
  
  private static $cleanUpList = array();
  
  private static $standardFiles = array(
    '.DS_Store',
    '.ftpssh_settings',
    '.cake',
    '.dropbox',
    '.git',
    '.gitignore',
    '.svn',
  );
  
  private static $connectionTypes = array(
    'ftp',
    'scp',
    'local',
  );
  
  public static $data = array(
    'conn' => array(
      'host' => '',
      'user' => '',
      'pass' => '',
      'path' => '',
      'protocol' => '',
    ),
    'ignore' => array(),
    'states' => array(),
  );
  
  private static $loaded = false;
  private static $foundConf = false;
  
  const confName = '.cake';
  
  // find the configuration file and set the directories and paths accordingly
  public static function findConfig()
  {
    self::$foundConf = false;
    $done = false;
    $lookDir = self::$currentDir;
    
    while (!$done)
    {
      $tryPath = $lookDir . '/' . self::confName;
      if (!file_exists($tryPath))
      {
        // go one level up in the directory tree
        $tmpDir = dirname($lookDir);
        // if there is no level above, we have not found any
        if ($tmpDir == $lookDir)
        {
          return false;
        }
        $lookDir = $tmpDir;
      }
      else
      {
        self::$confPath = $tryPath;
        self::$rootDir = $lookDir;
        self::$relativeDir = substr(getcwd(), strlen(self::$rootDir)+1);
        self::$foundConf = true;
        return true;
      }
    }
  }
  
  public static function forceLoad()
  {
    if (self::$loaded)
    {
      return;
    }
    try
    {
      self::findConfig();
      self::loadConfig();
    }
    catch (Exception $e)
    {
      echo "No configuration found\r\n";
      echo $e->getMessage(), "\r\n";
      exit;
    }
  }
  
  public static function loadConfig()
  {
    if (!self::$foundConf)
    {
      throw new Exception('Configuration has not yet been located');
    }
    if (self::$loaded)
    {
      throw new Exception('Cannot load config when already loaded');
    }
    $contents = file_get_contents(self::$confPath);
    if ($contents === false)
    {
      throw new Exception('Failed to get contents');
    }
    $json = json_decode($contents, true);
    if ($json === null)
    {
      throw new Exception('Not valid JSON');
    }
    self::$data = $json;
    self::$loaded = true;
  }
  
  public static function saveConfig()
  {
    $r = file_put_contents(self::$confPath, json_encode(self::$data));
    if ($r === false)
    {
      throw new Exception('Failed to save config');
    }
  }
  
  public static function newConfig()
  {
    $file = self::$currentDir . '/' . self::confName;
    if (file_exists($file))
    {
      throw new Exception('A configuration does already exist in this directory, it cannot be overwritten');
    }
    self::$confPath = $file;
    self::saveConfig();
  }
  
  public static function sanitizePath($path)
  {
    if (preg_match('#^\/?(.*?)\/?$#', $path, $m))
    {
      $path = $m[1];
    }
    return $path;
  }
  
  public static function makeRelative($path)
  {
    return (self::$relativeDir ? self::$relativeDir . '/' : '') . $path;
  }
  
  public static function setConnection($field, $data)
  {
    self::forceLoad();
    $possibleFields = array('host', 'user', 'pass', 'protocol', 'path');
    if (!in_array($field, $possibleFields))
    {
      throw new Exception('Unknown field ' . $field);
    }
    
    if ($field == 'protocol')
    {
      $data = strtolower($data);
      if (!in_array($data, self::$connectionTypes))
      {
        throw new Exception('Protocol must be one of the following: '. implode(',', self::$connectionTypes));
      }
    }
    
    if ($field == 'path')
    {
      // remove prefixed and suffixed slashes
      $data = self::sanitizePath($data);
      $data = self::makeRelative($data);
    }
    self::$data['conn'][$field] = $data;
    self::saveConfig();
  }
  
  public static function setIgnore($path)
  {
    self::forceLoad();
    
    $path = self::sanitizePath($path);
    $path = self::makeRelative($path);
    
    if (!isset(self::$data['ignore'][$path]))
    {
      self::$data['ignore'][$path] = 1;
      echo "Ignored ", $path, "\r\n";
      self::saveConfig();
      if (!file_exists(self::$rootDir . '/' . $path))
      {
        echo "Note: The entered path does not exist\r\n";
      }
    }
    else
    {
      echo "Path ", $path, " already ignored\r\n";
    }
  }
  
  public static function delIgnore($path)
  {
    self::forceLoad();
    
    $path = self::sanitizePath($path);
    $path = self::makeRelative($path);
    
    if (isset(self::$data['ignore'][$path]))
    {
      unset(self::$data['ignore'][$path]);
      echo 'Deleted ignore ', $path, "\r\n";
      self::saveConfig();
    }
    else
    {
      echo 'Ignore not found: ', $path, "\r\n";
    }
  }
  
  public static function cleanUp()
  {
    self::forceLoad();
    
    self::runDir(array('self', 'transferCleanUp'), false);
    
    foreach (self::$data['states'] as $path => $state)
    {
      if (!isset(self::$cleanUpList[$path]))
      {
        echo 'Removed ', $path, "\r\n";
        unset(self::$data['states'][$path]);
      }
    }
    self::saveConfig();
  }
  
  private static function transferCleanUp($path)
  {
    self::$cleanUpList[$path] = true;
  }
  
  public static function setDone()
  {
    self::forceLoad();
    
    self::runDir(array('self', 'transferSetDone'));
  }
  
  public static function reset()
  {
    self::forceLoad();
    self::$data['states'] = array();
    self::saveConfig();
  }
  
  private static function setTransferred($path)
  {
    self::forceLoad();
    
    $path = self::sanitizePath($path);
    $relPath = self::makeRelative($path);
    
    self::$data['states'][$relPath] = filemtime($path);
    self::saveConfig();
  }
  
  private static function hasChanged($path)
  {
    self::forceLoad();
    
    $path = self::sanitizePath($path);
    $relPath = self::makeRelative($path);
    
    return (
      !isset(self::$data['states'][$relPath]) || 
      self::$data['states'][$relPath] < filemtime($path)
    );
  }
  
  private static function isIgnored($path)
  {
    self::forceLoad();
    
    $path = self::sanitizePath($path);
    $relPath = self::makeRelative($path);
    
    $ignore = isset(self::$data['ignore'][$relPath]);
    
    $ignore = ($ignore || in_array(basename($path), self::$standardFiles));
    
    return $ignore;
  }
  
  public static function upload($dryRun = false)
  {
    echo "Initiating upload", ($dryRun ? ' (dryrun)' : ''), "\r\n";
    
    if ($dryRun)
    {
      self::runDir(array('self', 'transferDryRun'));
    }
    else
    {
      self::runDir(array('self', 'transfer'));
    }
  }
  
  private static function runDir($callback, $doAll = true)
  {
    self::forceLoad();
    
    $relDir = self::$relativeDir;
    self::$relativeDir = '';
    chdir(self::$rootDir);
    
    self::runDirRec($callback, $doAll, '');
    
    chdir(self::$currentDir);
    self::$relativeDir = $relDir;
    
    echo "Done\r\n";
  }
  
  private static function runDirRec($callback, $doAll, $dir)
  {
    $dh = opendir($dir ? $dir : '.');
    while ($entry = readdir($dh))
    {
      if ($entry != '.' && $entry != '..')
      {
        $entryPath = ($dir ? $dir . '/' : '') . $entry;
        if (!$doAll || !self::isIgnored($entryPath))
        {
          if (is_dir($entryPath))
          {
            self::runDirRec($callback, $doAll, $entryPath);
          }
          else
          {
            if (!$doAll || self::hasChanged($entryPath))
            {
              call_user_func($callback, $entryPath);
            }
          }
        }
      }
    }
  }
  
  private static function transferSetDone($path)
  {
    self::setTransferred($path);
  }
  
  private static function transferDryRun($path)
  {
    $rpath = self::$data['conn']['path'];
    
    $dir = dirname($path);
    $to = ($rpath ? $rpath . '/' : '') . ($dir != '.' ? $dir : '');
    $to = self::sanitizePath($to);
    
    echo 'Transferring ', $path, ' to ', $to, "\r\n";
  }
  
  private static function transfer($path)
  {
    foreach(self::$data['conn'] as $k => $v)
    {
      $k = 'r' . $k;
      $$k = $v;
    }
    
    $dir = dirname($path);
    $to = $rpath . '/' . ($dir != '.' ? $dir : '');
    $to = self::sanitizePath($to);
    
    echo 'Transferring ', $path, ' to ', $to, "\r\n";
    
    switch (self::$data['conn']['protocol'])
    {
      case 'ftp':
        $reqFields = array('user', 'pass', 'host', 'path');
        foreach ($reqFields as $f)
        {
          if (!isset(self::$data['conn'][$f]))
          {
            throw new Exception('Missing field ', $f);
          }
        }
        
        $command = '/usr/bin/ftp -V -u ' .
          escapeshellarg('ftp://' . 
          $ruser . ':' . $rpass . 
          '@' . $rhost . '/' . $to . '/') . ' ' . 
          escapeshellarg($path) . ' 2>&1';
        break;
        
      case 'scp':
        $reqFields = array('user', 'pass', 'host', 'path');
        foreach ($reqFields as $f)
        {
          if (!isset(self::$data['conn'][$f]))
          {
            throw new Exception('Missing field ', $f);
          }
        }
        
        $command = 'scp ' . 
          escapeshellarg($path) . ' ' . 
          $ruser . '@' . 
          $rhost . ':' . escapeshellarg($to.'/') . ' 2>&1';
        break;
        
      case 'local':
        $reqFields = array('path');
        foreach ($reqFields as $f)
        {
          if (!isset(self::$data['conn'][$f]))
          {
            throw new Exception('Missing field ', $f);
          }
        }
        
        @mkdir('/' . $to, 0777, true);
        $command = 'cp ' . 
          escapeshellarg($path) . 
          ' /' . escapeshellarg($to) . ' 2>&1';
        break;
    }
    
    $r = shell_exec($command);
    $success = ($r == '');
    
    if ($success)
    {
      self::setTransferred($path);
    }

    echo $success ? 'Success' : "Failed:\r\n" . $r, "\r\n";
    if (!$success)
    {
      echo $command, "\r\n";
      exit;
    }
  }
}


?>