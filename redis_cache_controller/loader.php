<?php

	require_once("api.php");

	// These constants define config options for the cache controller.  Set them to match your environment
	define("RCC_APP_NAME", 'Your app name');		// Your app name
	define("RCC_HOST", 'localhost');			// Redis server
	define("RCC_DB", 1);					// Redis server database
	define("RCC_AUTOPILOT", true);				// In autopilot mode, observing and cache control happens automagically (this is the default)
	define("RCC_DIRECTIVE_KEY", '_r');			// Querystring arg reserved for passing cache control directives (such as _r=flush)
	define("RCC_TTL", 302400);				// TTL for cache entries, in seconds

        // You can disable caching for pages based on querystring arguments, path segments, and cookies.
        // For querystring arguments, any time one of these is passed in the querystring (http://mysite.com?preview=true)
        // the cache for that URL will be disabled
        $no_cache_querystrings = array('preview','update_feedwordpress');

        // For path segments, any time the URI contains one of the path segments
        // the cache for that URL will be disabled
        $no_cache_segments = array('admin','api');

        // For cookies, any time the request contains cookies matching any of these values
        // the cache for that URL will be disabled
        $no_cache_cookies = array('wordpress_logged_in');

	// Create an instance of the cache controller early, before anything else happens in your app.
	// The objective is for caching to be at the front-end.  If there is no cache copy, the observer
	// will set up buffering and cache the content at the end of the request.
	$CWP_Cache = new REDIS_Cache_Controller( array(
						   'app_name' => RCC_APP_NAME,
						   'server' => RCC_HOST,
						   'database' => RCC_DB,
						   'autopilot' => RCC_AUTOPILOT,
						   'directive_key' => RCC_DIRECTIVE_KEY,
						   'ttl' => RCC_TTL,
						   'no_cache_for' => array(
								'arguments' => $no_cache_querystrings,	    // querystring arguments
								'segments' => $no_cache_segments,           // parts of the path
								'cookies' => $no_cache_cookies              // parts of cookies
						        )
						   )
					       );

	if (!REDIS) $CWP_Cache->disable();

	// The key for a cache entry is derived from the REQUEST_URI.
	$_uri = $_SERVER['REQUEST_URI'];
	
	// Each cache entry is stored in a set.  You can define many sets.  The benefit is that you will be able
	// to flush all of the items in a set using a single directive.
	$cache_set = 'default';

	// Set cache keys
	// In cache controller's autopilot mode, the controller will start observing, managing, and if
	// we are returning cache, script execution will end right here
	$CWP_Cache->set_key(
			      $_uri,
			      $cache_set
			    );