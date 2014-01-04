<?php
	// Sample WordPress launcher implementing redis_cache_controller as a front-end cache
	error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
	ini_set('display_errors', 1);
	define("REDIS", true);

	// Include this loader at the beginning of your page's execution, and your page will be managed by the
	// cache controller.
	require_once("rcc-wordpress-config.php");
	require_once("redis_cache_controller/loader.php");

	define('WP_USE_THEMES', true);
	require('./wp-blog-header.php');
?>