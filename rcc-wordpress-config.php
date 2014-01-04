<?php
	// These constants define config options for the cache controller.  Set them to match your environment
	define("RCC_APP_NAME", 'Your app name');		// Your app name
	define("RCC_HOST", 'localhost');			// Redis server
	define("RCC_DB", 1);					// Redis server database
	$RCC_AUTOPILOT = true;					// In autopilot mode, observing and cache control happens automagically (this is the default)
	define("RCC_DIRECTIVE_KEY", '_r');			// Querystring arg reserved for passing cache control directives (such as _r=flush)
	define("RCC_TTL", 302400);				// TTL for cache entries, in seconds

        // You can disable caching for pages based on querystring arguments, path segments, and cookies.
        // For querystring arguments, any time one of these is passed in the querystring (http://mysite.com?preview=true)
        // the cache for that URL will be disabled
        $no_cache_querystrings = array('preview','comment_post_redirect');

        // For path segments, any time the URI contains one of the path segments
        // the cache for that URL will be disabled
        $no_cache_segments = array('wp-admin');

        // For cookies, any time the request contains cookies matching any of these values
        // the cache for that URL will be disabled
        $no_cache_cookies = array('wordpress_logged_in');