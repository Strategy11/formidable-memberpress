<?php
/*
Plugin Name: Formidable MemberPress
Description: Integrate MemberPress and Formidable
Author: Strategy11
Author URI: http://strategy11.com
Version: 1.0a
Text Domain: frm_mepr
*/

if ( !defined('ABSPATH') ) {
    die('You are not allowed to call this page directly.');
}

function frm_mepr_autoloader($class_name){
    if ( ! preg_match('/^FrmMepr.+$/', $class_name) ) {
        return;
    }
    
    $path = dirname(__FILE__);
    
    if ( preg_match('/^.+Controller$/', $class_name) ) {
        $filepath = $path ."/app/controllers/{$class_name}.php";
    } else if ( preg_match('/^.+Helper$/', $class_name) ) {
        $filepath = $path ."/app/helpers/{$class_name}.php";
    } else {
        //$filepath = $path ."/app/models/{$class_name}.php";
    }

    if ( file_exists($filepath) ) {
        include_once($filepath);
    }
}

// if __autoload is active, put it on the spl_autoload stack
if ( is_array(spl_autoload_functions()) && in_array('__autoload', spl_autoload_functions()) ) {
    spl_autoload_register('__autoload');
}

// Add the autoloader
spl_autoload_register('frm_mepr_autoloader');

$controllers = @glob( dirname(__FILE__) .'/app/controllers/*', GLOB_NOSORT );
foreach ( $controllers as $controller ) {
    $class = preg_replace( '#\.php#', '', basename($controller) );
    if ( preg_match( '#FrmMepr.*Controller#', $class ) ) {
        $obj = new $class;
    }
}