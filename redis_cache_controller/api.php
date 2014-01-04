<?php

	// This cache controller is an observer that interfaces with your Redis server.  It will check to see if
	// this request is eligible for caching, and if so, determine if a cached copy is available.  Ideally, you
	// implemented this so that Cached content will be returned before your app does any heavy lifting, so no
	// overhead!!  Otherwise, if there is no cache copy, your app is allowed to render output, which will be
	// buffered by the cache controller before being sent.

	// This cache controller utilizes the PhpRedis extension provided by Nicolasff and available at
	// https://github.com/nicolasff/phpredis
	// Follow the instructions to acquire and download the extension before attempting to use the cache controller.

	define('REDIS_Cache_controller_loaded', true);
	define('REDIS_Cache_stage_OFF', 0);
	define('REDIS_Cache_stage_GENERATE', 1);
	define('REDIS_Cache_stage_STORE', 2);
	define('REDIS_Cache_stage_CACHED', 3);
	define('REDIS_Cache_directive_key', '_r');

	define('REDIS_Cache_Default_TTL', 302400);	// default time to live is 3 1/2 days (3600 * 24 * 3.5)

	class REDIS_Cache_Controller {

		// Flag tells programs we can cache:
		protected $caching_enabled = false;

		// Redis object, connection status, and class info
		protected $redis = null;
		protected $connected = false;
		protected $server = 'localhost';
		protected $database = 1;
		protected $app_name = "MrBoson's Redis Cache Controller, version 1.0";
		protected $directive_key = REDIS_Cache_directive_key;
		protected $autopilot = true;

                protected $no_cache_req_parts = array(
                                                        'paths' => array(),
                                                        'segments' => array(),
                                                        'arguments' => array(),
                                                        'cookies' => array(),
                                                      );

		// Keys used by Redis
		protected $_set = '';
		protected $_key = '';
		protected $_category = '';

		// Copy of the URL parts
		protected $uri = '';
		protected $query_s = '';
		protected $http_host = '';
		protected $directive = '';	// passed in querystring argument $directive_key

		// content stores
		protected $_cached_stage = REDIS_Cache_stage_OFF;
		protected $_content = '';
		protected $_generated = '';
		protected $_duration = 0;
		protected $_expires = '';
		protected $_headers = array();

		// Time to live
		protected $_ttl = REDIS_Cache_Default_TTL;

		// Setting to return in header Cache-control
		protected $_cache_control_max_age = 0;

		// timing stats
		protected $_start_time = 0;
		protected $_end_time = 0;

		function __construct($args = array()) {
			$this->_start_time = microtime(true);

			$this->uri = $_SERVER['REQUEST_URI'];
			$this->query_s = $_SERVER['QUERY_STRING'];
			$this->http_host = $_SERVER['HTTP_HOST'];

			// Init arguments
			$this->directive_key = (isset($args['directive_key']) && $args['directive_key']) ? $args['directive_key'] : REDIS_Cache_directive_key;
			if (isset($_GET[$this->directive_key])) {
				$this->directive = $_GET[$this->directive_key];
			}
			$this->server = (isset($args['server']) && $args['server']) ? $args['server'] : 'localhost';
			$this->database = (isset($args['database']) && intval($args['database'])) ? intval($args['database']) : 1;
			$this->autopilot = (isset($args['autopilot'])) ? $args['autopilot'] : $this->autopilot;
			$this->_ttl = (isset($args['ttl']) && intval($args['ttl'])) ? intval($args['ttl']) : REDIS_Cache_Default_TTL;
			$this->_cache_control_max_age = (isset($args['cache_control_max_age']) && intval($args['cache_control_max_age'])) ? intval($args['cache_control_max_age']) : 0;
                        if (isset($args['no_cache_for'])) {
                            $this->no_cache_req_parts['segments'] = isset($args['no_cache_for']['segments'])
                                                                  ? $args['no_cache_for']['segments']
                                                                  : array();
                            $this->no_cache_req_parts['arguments'] = isset($args['no_cache_for']['arguments'])
                                                                   ? $args['no_cache_for']['arguments']
                                                                   : array();
                            $this->no_cache_req_parts['cookies'] = isset($args['no_cache_for']['cookies'])
                                                                 ? $args['no_cache_for']['cookies']
                                                                 : array();
                        }
			if (isset($args['app_name']) && $args['app_name']) {
				$this->app_name = $args['app_name'];
			}

			// Instantiate the object here, that way if we fail our object won't load
			// and the caller can handle the error the way they want.
			$this->redis = new Redis();

			$this->can_we_cache();
		}

		protected function connect() {
			if ($this->caching_enabled && !$this->connected) {
				try {
					$this->connected = $this->redis->pconnect($this->server);
					$this->redis->select($this->database);
				} catch (RedisException $e) {
					$this->connected = false;
					return false;
				}
			}

			return $this->caching_enabled && $this->connected;
		}

		function disable() {
			$this->caching_enabled = false;
		}

		protected function make_key($item) {
			// often $item is a URI which contains querystrings.
			// Strip off the directive stuff
			if ($this->directive) {
				$key = str_replace($this->directive_key.'='.$this->directive,'',$item);
				$this->uri = str_replace($this->directive_key.'='.$this->directive,'',$this->uri);
				$this->uri = str_replace('&&','&',$this->uri);
				$this->uri = str_replace('?&','?',$this->uri);
				$this->uri = trim($this->uri, '?&');
			} else {
				$key = $item;
			}

			$key = trim($key, '?&');

			return md5('packaged:'.$this->_category.':'.$this->http_host.':'.$key);
		}

		function set_key($item, $category = 'default') {
			$this->_category = $category;

			// Create set from the $category
			$this->_set = md5($category.':'.$this->http_host);

			// Create content key
			$this->_key = $this->make_key($item);

			// Handle any key affected directives
			if ($this->directive) {
				// Now handle directives:
				$reload = false;
				switch($this->directive) {
					case 'flush':
						$this->flush_item($this->_set, $this->_key);
						$reload = true;
						break;

					case 'flushall':
						$this->flush_set_key($this->_set);
						$reload = true;
						break;

					case 'expire':
						
						break;

					case 'persist':
						
						break;
				}
				if ($reload) {
					header( 'Refresh: 0; url=' . $this->uri ) ;
					exit(0);
				}

			}
			if ($this->autopilot) {
				$this->try_page_cache();
			}
		}

		function get_set_key() {
			return $this->_set;
		}

		function get_item_key() {
			return $this->_key;
		}

		// Tests to determine if we should be caching or not
		function can_we_cache() {
			$use_caching = true;

			if ($this->autopilot) {
				// Look for query strings registered by calling program that would disable caching:
				if ($use_caching && $this->query_s && !empty($this->no_cache_req_parts['arguments'])) {
				    foreach($this->no_cache_req_parts['arguments'] as $argument) {
					$use_caching = !array_key_exists($argument,$_GET);
					if (!$use_caching) {
					    break;
					}
				    }
				}
				// Look for path segments registered by calling program that would disable caching:
				if ($use_caching && !empty($this->no_cache_req_parts['segments'])) {
				    $url_parts = parse_url($_SERVER['REQUEST_URI']);
				    if (isset($url_parts['path'])) {
					$segments_match = array_intersect($this->no_cache_req_parts['segments'], explode('/', $url_parts['path']));
					$use_caching = empty($segments_match);
				    }
				    unset($url_parts);
				}
				// Look for cookie fragments registered by calling program that would disable caching:
				if ($use_caching && !empty($this->no_cache_req_parts['cookies'])) {
				    $cookie = var_export($_COOKIE, true);
				    foreach($this->no_cache_req_parts['cookies'] as $crumb) {
					$use_caching = !preg_match("/$crumb/", $cookie);
					if (!$use_caching) {
					    break;
					}
				    }
				    unset($cookie);
				}
			}

			$this->caching_enabled = $use_caching;

			return $use_caching;
		}

		function flush_set($set = '') {
			if ($set) {
				$this->flush_set_key(md5($set.':'.$this->http_host));
			}
		}

		function flush_set_key($set = '') {
			if (!$set) {
				$set = $this->_set;
			}
			if ($this->connect() && $this->redis->exists($set)) {
				$this->redis->delete($set);
			}
		}

		function flush_item($set = '', $key = '') {
			if (!$set) {
				$set = $this->_set;
			}
			if (!$key) {
				$key = $this->_key;
			}
			if ($this->connect()) {
				$this->redis->delete($key);		// remove the item's key-value pair
				$this->redis->sRemove($set, $key);	// remove the item's key from its parent set
			}
		}

		// Query the cache for object (usually JSON).  If available, return that
		function try_object_cache() {
			$this->_try_cache();

			$result = false;
			if ($this->_content) {
				$result = array();
				$result['object'] = $this->_content;
				$result['generated'] = $this->_generated;
				$result['expires'] = $this->_expires;
				$result['SetName'] = $this->_category;
				$result['ItemKey'] = $this->_key;
				$result['SetKey'] = $this->_set;
			}
			$this->_clear_buffers();

			return $result;
		}

		// Query the cache for page-level content.  If available, it will be echoed,
		// if not, buffering will be turned on to capture content as it is generated
		function try_page_cache() {

			$this->_try_cache();

			if ($this->_content) {
				// A successful render will result in the script exiting, because we are done right here.
				$this->_cached_stage = REDIS_Cache_stage_CACHED;
				$this->render(true);
				// We exited
				return;
			}

			if ($this->caching_enabled && $this->connected) {

				if ($this->autopilot) {
					// We want to automatically handle caching when a page is completely done.
					register_shutdown_function(array($this, 'page_close_down'));
				}

				// turn on output buffering
				ob_start();

				return true;
			} else {
				return false;
			}
		}

		// Actual method to query cache and handle the result
		protected function _try_cache() {

			$this->_clear_buffers();

			if ($this->connect()) {
				// Do we have a cached copy of the key in our set?
				if ($this->redis->sIsMember($this->_set, $this->_key)) {
					// Query the key, results will be a serialized package array
					$raw = $this->redis->get($this->_key);
					if ($raw) {
						$package = unserialize($raw);
						$this->_content = $package['content'];
						$this->_headers = $package['headers'];
						$this->_generated = $package['generated'];
						$this->_expires = $package['expires'];
						unset($package);
					} else {
						// Perhaps the key expired or was deleted some other way.  Get it out of the set:
						$this->redis->sRemove($this->_set, $this->_key);
					}
					unset($raw);
				}
			}
		}

		function page_close_down() {
			if (!$this->caching_enabled) {
				return;
			}
			// get contents of output buffer
			$headers = headers_list();
			$this->_clear_buffers();
			$this->_content = ob_get_contents();

			// clean output buffer and shut down buffering
			ob_end_clean();

			// We will identify the content type
			$content_type = 'text/html;';

			// Create the cache package object:
			$package = array(
					  'content' => '',
					  'headers' => $headers    // store the response headers as well
					 );

			// But get rid of cookies, and identify content-type:
			$i = 0;
			foreach($package['headers'] as $header) {
				if (substr($header,0,11) == 'Set-Cookie:') {
					unset($package['headers'][$i]);
				}
				if (substr($header,0,13) == 'Content-Type:') {
					$content_type = trim(substr($header,13));
				}
				$i++;
			}

                        if (substr($content_type,0,10) == 'text/html;') {
				// Insert the <!-- [Cache Metadata] --> token, after the </title>
				$this->_content = str_ireplace('</title>',"</title>\n<!-- [Cache Metadata] -->\n", $this->_content);
			}

			$package['content'] = $this->_content;

			// Commit the page package to cache
			$ret = $this->save_to_cache($package);

			// Send the output to the browser
			$this->render();
		}

		function save_to_cache($package = array()) {
			$ret = false;
			if ($this->connect()) {
				$this->_cached_stage = REDIS_Cache_stage_GENERATE;
	
				$package['generated'] = date(DATE_RFC822);
				$package['expires'] = date(DATE_RFC822, time() + $this->_ttl);
	
				if (isset($package['headers'])) {
					// Add last modified and cache-control headers:
					$package['headers'][] = 'Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT';
					$package['headers'][] = 'Cache-Control: max-age='.$this->_cache_control_max_age.', must-revalidate';

					// Add our own cache info headers:
					$package['headers'][] = 'X-Cache-key: '.$this->_key;
					$package['headers'][] = 'X-Cache-set: '.$this->_set;
					$package['headers'][] = 'X-Cache-Content-Created: '.$package['generated'];
					$package['headers'][] = 'X-Cache-expires: '.$package['expires'];
	
					$package['headers'] = array_values($package['headers']);
				}
	
				// store package to redis cache
				if ($this->redis->sAdd($this->_set, $this->_key) && $this->redis->setex($this->_key, $this->_ttl, serialize($package)) !== false) {
					$this->_cached_stage = REDIS_Cache_stage_STORE;
					if (isset($package['headers'])) {
						$this->_headers = $package['headers'];
					}
					$this->_generated = $package['generated'];
					$this->_expires = $package['expires'];
					$ret = true;
				}
			}
			unset($package);

			return $ret;
		}

		function get_cache_status() {
			$status = '';
			switch($this->_cached_stage) {
				case REDIS_Cache_stage_GENERATE:
					$status = 'generating';
					break;
				case REDIS_Cache_stage_STORE:
					$status = 'caching';
					break;
				case REDIS_Cache_stage_CACHED:
					$status = 'cached';
					break;
				default:
					$status = 'not cached';
			}
			return $status;
		}

		protected function render($terminate = false) {
			$content = $this->_content;

			if ($this->_cached_stage == REDIS_Cache_stage_CACHED) {
				
			}
			// Set the cache meta data:
			$cache_meta_data = $this->_get_cache_metadata();
			$content = str_replace('<!-- [Cache Metadata] -->',$cache_meta_data,$content);

			foreach($this->_headers as $header) {
				header($header, true);
			}

			$content = $this->_evaluate_tokens($content, 'RCC-cache-status', $this->get_cache_status());
			$content = $this->_evaluate_tokens($content, 'RCC-cache-expires', $this->_expires);
			$content = $this->_evaluate_tokens($content, 'RCC-cache-generated', $this->_generated);
			$content = $this->_evaluate_tokens($content, 'RCC-cache-duration', $this->_duration);
			$content = $this->_evaluate_tokens($content, 'RCC-cache-key', $this->_key);
			$content = $this->_evaluate_tokens($content, 'RCC-cache-set', $this->_set);

			echo $content;

			$this->_clear_buffers();

			if ($terminate) {
				exit(0);
			}

		}

		protected function _evaluate_tokens($html, $class, $replace) {
			return preg_replace('~<(span([^>]*)class="(.*?)'.$class.'(.*?)")>(.*?)</span>~im', '<$1>'.$replace.'</span>', $html);
		}

		protected function _clear_buffers() {
			$this->_cached_stage = REDIS_Cache_stage_OFF;
			$this->_content = '';
			$this->_generated = '';
			$this->_duration = 0;
			$this->_headers = array();
			$this->_expires = '';
		}

		protected function _get_cache_metadata() {
			$category = $this->_category;
			$key = $this->_key;
			$set = $this->_set;
			$database = $this->database;
			$app_name = $this->app_name;
			$this->_duration = microtime(true) - $this->_start_time;
			$duration = $this->_duration;
                        $date = (empty($this->_generated)) ? date(DATE_RFC822) : $this->_generated;
			$expires = $this->_expires;
			$status = $this->get_cache_status();

			$cache_meta_data = "		<meta name=\"application-name\" content=\"$app_name\"
			data-Cache-SetName=\"$category\"
			data-Cache-SetKey=\"$set\"
			data-Cache-ItemKey=\"$key\"
			data-Cache-db=\"$database\"
			data-Cache-duration=\"$duration\"
			data-Cache-Content-Created=\"$date\"
			data-Cache-Entry-Expires=\"$expires\"
			data-Cache-Status=\"$status\"
			/>";

			return $cache_meta_data;
		}

	}
