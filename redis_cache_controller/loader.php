<?php

	require_once("api.php");

	// Create an instance of the cache controller early, before anything else happens in your app.
	// The objective is for caching to be at the front-end.  If there is no cache copy, the observer
	// will set up buffering and cache the content at the end of the request.
	$CWP_Cache = new REDIS_Cache_Controller( array(
						   'app_name' => RCC_APP_NAME,
						   'server' => RCC_HOST,
						   'database' => RCC_DB,
						   'autopilot' => $RCC_AUTOPILOT,
						   'directive_key' => RCC_DIRECTIVE_KEY,
						   'ttl' => RCC_TTL,
                                                   'cache_control_max_age' => 0,
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