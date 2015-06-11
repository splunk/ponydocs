<?php

error_reporting(E_ALL | E_STRICT | E_DEPRECATED);

function autoload($class_name)
{
	require str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';
}

spl_autoload_register('autoload');