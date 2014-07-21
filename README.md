# php-redis-controller

This cache controller is an observer that interfaces with a Redis server.  It will check to see if a request is eligible for caching, and if so, determine if a cached copy is available.  When available, the cached copy is returned to the browser, including all http headers.  When not available, your PHP code will function as normal to generate content.  The controller will cache that content to be available on the next request.

The basic purpose of this php-redis-controller is to provide a lightweight (and easy to implement) front end cache to any PHP page you wish to make cacheable.  I use this in front of my Wordpress installs, and I generally get millisecond rendering times for pages that get cached.

### Requirements

Php-redis-controller depends on the PhpRedis extension provided by Nicolasff (available at https://github.com/nicolasff/phpredis).  Follow the instructions there to acquire and install the extension.

### Implementation

Include rcc-config.php and ./redis_cache_controller/loader.php on any PHP page you wish to be managed by the cache controller.  Change the constants and config variables in rcc-config.php to match your environment (if you are caching WordPress, then use rcc-wordpress-config.php instead of rcc-config.php).

Test that caching is working by using the provided sample.php.

You can also load php-redis-controller in a non-caching mode if you wish to interact with via its API:


```php
require_once("rcc-config.php");
$RCC_AUTOPILOT = false;
require_once("redis_cache_controller/loader.php");

```

This sets autopilot mode to false, so you would need to control caching.

### Cache Sets and Flushing

Php-redis-controller allows you to define sets or collections of caches.  While each page in cache is tracked individually, each is also tracked according to which set it belongs to.  This makes it possible to delete all of the pages in a set at once.  For example, I use this in a Wordpress system to flush all cache entries associated with a blog post every time the post is edited or receives a comment.  Flushing can be triggered by two special directives that can be appended to a URL in order to flush items out of the cache.  Usage:
http://mysite.com/mypage.php?_r=flush      --   this will flush mypage.php out of the cache
http://mysite.com/mypage.php?_r=flushall   --   this will flush all pages belonging to mypage.php's cache set.

You can also flush cache entries using API calls, just load load php-redis-controller with its autopilot off.  Then you can make calls such as this:

```php
$CWP_Cache->flush_set('cache set name');   // removes all cache entries belonging to cache set 'cache set name'
$CWP_Cache->flush_item('cache set name', 'cache key');  // removes cache entry with key = 'cache key'

```

### Integration with your site

Assuming your Redis server is running and avaliable via the PhpRedis extension on your server, the php-redis-controller will begin caching and serving from cache automatically.

There are some behaviors you can control, such as disabling caching for for pages based on querystring
arguments, path segments, and cookies.  You can also define HTML strings that will be replaced before caching, useful for doings things like removing values from form variables, or making sure a login form is not caching user names.

See rcc-config.php (or rcc-wordpress-config.php) if you are using with WordPress.

You (or your PHP code) can also embed special tokens into the content which will be evaluated by the cache controller.  See the example in sample.php.

### WordPress Integration

Take a look at the wordpress-sample.php file.  Basically, you want to use something like this instead of the normal index.php used by WordPress (i.e., you could change which file is the default index in your WordPress root).  You will also want to copy the file in the WordPress-plugin into your WP plugins folder, then in wp-admin activate the RCC Functions plugin.
