# php-redis-controller

This cache controller is an observer that interfaces with a Redis server.  It will check to see if a request is eligible for caching, and if so, determine if a cached copy is available.  When available, the cached copy is returned to the browser, including all http headers.  When not available, your PHP code will function as normal to generate content.  The controller will cache that content to be available on the next request.

The basic purpose of this php-redis-controller is to provide a lightweight (and easy to implement) front end cache to any PHP page you wish to make cacheable.  I use this in front of my Wordpress installs, and I generally get millisecond rendering times for pages that get cached.

### Requirements

Php-redis-controller depends on the PhpRedis extension provided by Nicolasff (available at https://github.com/nicolasff/phpredis).  Follow
the instructions there to acquire and install the extension.

### Implementation

Include redis_cache_controller/loader.php on any PHP page you wish to be managed by the cache controller.  Change the constants and
config variables at the top of loader.php to match your environment.

Test using the provided sample.php.

### Integration with your site

Assuming your Redis server is running and avaliable via the PhpRedis extension on your server, the php-redis-controller will begin caching
and serving from cache automatically.

There are some behaviors you can control, such as disabling caching for for pages based on querystring
arguments, path segments, and cookies.  See loader.php for an example.

You (or your PHP code) can also embed special tokens into the content which will be evaluated by the cache controller.  See the example in
sample.php.
