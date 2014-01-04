<?php
/*
Plugin Name: RCC Functions
Version: R1.0
Author: mrboson
*/

	error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
	ini_set('display_errors', 1);

if (!defined(REDIS_Cache_controller_loaded)) {
	define("REDIS", true);

	// Load the REDIS_Cache_Controller API
	require_once(ABSPATH . "rcc-wordpress-config.php");
	$RCC_AUTOPILOT = false;
	require_once(ABSPATH . "redis_cache_controller/loader.php");

	$GLOBALS['CWP_Cache'] = $CWP_Cache;
}

// We handle posts saved so we can flush the cache
add_action('wp_insert_comment','RCC_insert_comment_flush_cache', 10, 2);
function RCC_insert_comment_flush_cache($id, $comment) {
	if ($comment->comment_approved == 1) {
		$post_id = $comment->comment_post_ID;
		if ($post_id) {
			$post = get_post( $post_id );
			RCC_remove_cache_key($post);
		}
	}
}
add_action('transition_comment_status','RCC_transition_comment_status_flush_cache', 10, 3);
function RCC_transition_comment_status_flush_cache($new_status, $old_status, $comment) {
	if ($old_status == 'approved' || $new_status == 'approved') {
		$post_id = $comment->comment_post_ID;
		if ($post_id) {
			$post = get_post( $post_id );
			RCC_remove_cache_key($post);
		}
	}
}
add_action( 'save_post', 'RCC_saved_post_flush_cache', 10, 2 );
function RCC_saved_post_flush_cache($post_id, $post) {
	RCC_remove_cache_key($post);
}

function RCC_remove_cache_key($post) {
	// WordPress has bits of posts all over the place.  So flush the whole set so all
	// associated pages get regenerated.
	$blog_type = 'default';
	$GLOBALS['CWP_Cache']->flush_set($blog_type);
}


// We add the comment_post_redirect query_arg to the URI redirect after a commented is
// posted.  It indicates to the cache controller that the URI should not be cached.
add_filter('comment_post_redirect', 'RCC_comment_post_redirect',10,2);
function RCC_comment_post_redirect($location, $comment) {

    $parts = split('#', $location);
    $uri = $parts[0];

    $new_uri = add_query_arg( 'comment_post_redirect', $comment->comment_ID, $uri );
    if (isset($parts[1])) {
	return $new_uri . '#' . $parts[1];
    } else {
	return $new_uri;
    }
}

?>
