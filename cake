#!/usr/local/bin/php
<?php

require_once dirname(__FILE__) . '/Cake.class.php';
require_once dirname(__FILE__) . '/CommandReader.class.php';

Cake::$currentDir = getcwd();

array_shift($argv);

try
{
	CommandReader::read($argv);
}
catch (Exception $e)
{
	echo "Error:\r\n", $e->getMessage(), "\r\n";
}

?>