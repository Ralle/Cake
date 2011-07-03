#!/usr/local/bin/php
<?php

$workDir = getcwd();
$confName = '.cake';

class conf
{
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
	private static $path = '';
	
	public static function init($path)
	{
		if (file_exists($path))
		{
			$answer = readline("A config does already exist, do you wish to overwrite it? [y/n]\r\n");
			if ($answer != 'y')
			{
				exit("Cancelled\r\n");
			}
		}
		$json = json_encode(self::$data);
		if (file_put_contents($path, $json) === false)
		{
			throw new Exception('Failed to write file');
		}
		echo 'Initialized empty config at ', $path, "\r\n";
		self::$path = $path;
	}
	
	public static function findConf()
	{
		global $workDir, $confName;

		$done = false;
		$lookDir = $workDir;

		while (!$done)
		{
			$confPath = $lookDir . '/' . $confName;
			if (!file_exists($confPath))
			{
				$tmpDir = dirname($lookDir);
				if ($tmpDir == $lookDir)
				{
					throw new Exception('No config');
				}
				$lookDir = $tmpDir;
			}
			else
			{
				return $confPath;
			}
		}
	}
	
	public static function load()
	{
		$path = self::findConf();
		$contents = file_get_contents($path);
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
		self::$path = $path;
	}
	
	public static function save()
	{
		self::requireInited();
		$r = file_put_contents(self::$path, json_encode(self::$data));
		if ($r === false)
		{
			throw new Exception('Failed to save config');
		}
	}
	
	public static function requireInited()
	{
		if (self::$path == '')
		{
			throw new Exception('Config not loaded');
		}
	}
	
	public static function setConnection($field, $data)
	{
		self::requireInited();
		self::$data['conn'][$field] = $data;
		self::save();
	}
	
	public static function setIgnore($path)
	{
		self::requireInited();
		if (!isset(self::$data['ignore'][$path]))
		{
			self::$data['ignore'][$path] = 1;
			echo "Ignored ", $path, "\r\n";
			conf::save();
		}
		else
		{
			echo "Path ", $path, " already ignored\r\n";
		}
	}
	
	public static function delIgnore($path)
	{
		self::requireInited();
		if (isset(self::$data['ignore'][$path]))
		{
			unset(self::$data['ignore'][$path]);
			echo 'Deleted ignore ', $path, "\r\n";
			conf::save();
		}
		else
		{
			echo 'Ignore not found', "\r\n";
		}
	}
	
	public static function getRelativeDir()
	{
		global $workDir;
		self::requireInited();
		$rootDir = dirname(self::$path);
		return substr($workDir, strlen($rootDir));
	}
	
	public static function transferred($path)
	{
		// echo 'Transferred() ', $path, "\r\n";
		self::$data['states'][$path] = filemtime('.' . $path);
		self::save();
	}
	public static function hasChanged($path)
	{
		$path = substr($path, 1);
		// echo 'hasChanged() ', $path, "\r\n";
		return (
			!isset(self::$data['states'][$path]) || 
			self::$data['states'][$path] < filemtime('.' . $path)
		);
	}
	
	public static function rootDir()
	{
		return dirname(self::$path);
	}
}

class sync
{
	public static function start($dryRun)
	{
		conf::requireInited();
		$dir = '.';
		self::runDir($dir, $dryRun);
	}
	
	private static function ignored($path)
	{
		// remove the dot
		$path = conf::getRelativeDir() . substr($path, 1);
		$path2 = $path . '/';
		$ignore = (
			isset(conf::$data['ignore'][$path]) || 
			isset(conf::$data['ignore'][$path2])
		);
		
		$ignore = ($ignore || in_array(basename($path), array(
			'.DS_Store',
			'.ftpssh_settings',
			'.cake',
		)));
		
		if ($ignore)
		{
			return true;
		}
		
		return false;
	}
	
	private static function runDir($dir, $dryRun)
	{
		$dh = opendir($dir);
		while ($entry = readdir($dh))
		{
			if ($entry != '.' && $entry != '..')
			{
				$entryPath = $dir . '/' . $entry;
				if (! self::ignored($entryPath))
				{
					if (is_dir($entryPath))
					{
						self::runDir($entryPath, $dryRun);
					}
					else
					{
						if (conf::hasChanged($entryPath))
						{
							self::transfer($entryPath, $dryRun);
						}
					}
				}
			}
		}
	}
	
	private static function transfer($path, $dryRun)
	{
		foreach(conf::$data['conn'] as $k => $v)
		{
			$k = 'r' . $k;
			$$k = $v;
		}
		
		$to = $rpath . conf::getRelativeDir() . substr($path,1);
		
		echo 'Transferring ', $path, ' to ', $to, "\r\n";
		
		if ($dryRun)
		{
			return;
		}
		
		switch (conf::$data['conn']['protocol'])
		{
			case 'ftp':
				$command = '/usr/bin/ftp -V -u ' .
					escapeshellarg('ftp://' . 
					$ruser . ':' . $rpass . 
					'@' . $rhost . '/' . $to) . ' ' . 
					escapeshellarg($path) . ' 2>&1';
				break;
				
			case 'scp':
				$command = 'scp ' . 
					escapeshellarg($path) . ' ' . 
					$ruser . '@' . 
					$rhost . ':' . escapeshellarg($to) . ' 2>&1';
				break;
			
			default:
				throw new Exception('Unkown protocol');
				break;
		}
		
		$r = shell_exec($command);
		$success = ($r == '');

		if ($success)
		{
			conf::transferred(substr($path,1));
		}

		echo $success ? 'Success' : 'Failed (' . $r . ')', "\r\n";
		if (!$success)
		{
			echo $command;
			exit;
		}
	}
	
	public static function cleanUp()
	{
		$rootDir = conf::rootDir();
		
		$any = false;
		
		foreach (conf::$data['states'] as $file => $v)
		{
			echo 'Test ', $file, "\r\n";
			if (!file_exists($rootDir . '/' . $file))
			{
				echo 'File ', $file, ' does not exist anymore, will be removed', '\r\n';
				$any = true;
			}
		}
		
		if (!$any)
		{
			echo 'No entries were deleted', "\r\n";
		}
	}
}

function readArguments($argv)
{
	global $workDir, $confName;
	
	array_shift($argv);
	if (isset($argv[0]))
	{
		switch ($argv[0])
		{
			case 'init':
				$dir = $workDir . '/' . $confName;
				conf::init($dir);
				break;
				
			case 'config':
				conf::load();
				if (isset($argv[1]) && isset($argv[2]))
				{
					switch ($argv[1])
					{
						case 'host':
						case 'user':
						case 'pass':
						case 'protocol':
						case 'path':
							conf::setConnection($argv[1], $argv[2]);
							break;
					}
				}
				else
				{
					exit ("Need more arguments\r\n");
				}
				
				break;
				
			case 'ignore':
				conf::load();
				if (isset($argv[1]))
				{
					$tdir = conf::getRelativeDir();
					if (is_dir($argv[1]))
					{
						$lastChar = substr($argv[1], strlen($argv[1])-1);
						if ($lastChar != '/')
						{
							$argv[1] .= '/';
						}
					}
					$pt = $tdir . '/' . $argv[1];
					conf::setIgnore($pt);
				}
				break;
			
			case 'unignore':
				conf::load();
				if (isset($argv[1]))
				{
					$tdir = conf::getRelativeDir();
					$pt = $tdir . '/' . $argv[1];
					conf::delIgnore($pt);
				}
				break;
			
			case 'debug':
				conf::load();
				print_r(conf::$data);
				break;
			
			case 'n':
				sync(true);
				break;
			
			case 'clean':
				sync::cleanUp();
				break;
		}
	}
	else
	{
		// begin deciding what to do
		sync();
	}
}

function sync($dry = false)
{
	conf::load();
	sync::start($dry);
}

ftp_connect('localhost');

try
{
	readArguments($argv);
}
catch (Exception $e)
{
	exit ($e->getMessage() . "\r\n");
}

?>