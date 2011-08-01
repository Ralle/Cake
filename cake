#!/usr/local/bin/php
<?php

require_once dirname(__FILE__) . '/Cake.class.php';
require_once dirname(__FILE__) . '/CommandReader.class.php';

Cake::$currentDir = getcwd();

array_shift($argv);

CommandReader::read($argv);

?>