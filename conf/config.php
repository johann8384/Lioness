<?php
//error_reporting(E_ALL ^ E_DEPRECATED);
error_reporting(E_ERROR);
ini_set('display_errors','On');
set_time_limit(60);
ini_set('memory_limit', '512M');
date_default_timezone_set('America/Los_Angeles');
define('BASE_PATH', '/Users/jcreasy/code/Lioness');
/**
 *
 * @param string $className Class or Interface name automatically
 *              passed to this function by the PHP Interpreter
 */
function autoLoader($className){
    $directories = array(
      BASE_PATH . '/lib/',
      '',
      'lib/',
      '../lib/',
      '../../lib/',
      '../../../lib/',
    );

    //Add your file naming formats here
    $fileNameFormats = array(
      '%s.php',
      '%s.class.php',
      'class.%s.php',
      '%s.inc.php'
    );

    // this is to take care of the PEAR style of naming classes
    $path = str_ireplace('_', '/', $className);
    if(@include_once $path.'.php'){
       	return;
    }

    foreach($directories as $directory){
       	foreach($fileNameFormats as $fileNameFormat){
            $path = $directory.sprintf($fileNameFormat, $className);
            if(file_exists($path)){
               	include_once $path;
               	return;
            }
       	}
    }
}

spl_autoload_register('autoLoader');
