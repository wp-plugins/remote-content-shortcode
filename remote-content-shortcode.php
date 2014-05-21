<?php
/*
Plugin Name: Remote Content Shortcode
Plugin URI: http://www.doublesharp.com
Description: Embed remote content with a shortcode
Author: Justin Silver
Version: 1.3
Author URI: http://doublesharp.com
License: GPL2
*/

if ( ! class_exists( 'RemoteContentShortcode' ) ):

class RemoteContentShortcode {

	private static $instance;

	private function __construct() { }

	public static function init() {
		if ( ! is_admin() && ! self::$instance ) {
			self::$instance = new RemoteContentShortcode();
			self::$instance->add_shortcode();
		}
	}

	public function add_shortcode(){
		add_shortcode( 'remote_content', array( &$this, 'remote_content_shortcode' ) );
	}

	public function remote_content_shortcode( $atts, $content=null ) {
		// decode and remove quotes, if we wanted to (for use with SyntaxHighlighter)
		if ( isset( $atts['decode_atts'] ) ) {
			switch ( strtolower( html_entity_decode( $atts['decode_atts'] ) ) ) {
			 	case 'true': case '"true"':
					foreach ( $atts as $key => &$value ) {
						$value = html_entity_decode( $value );
						if ( strpos( $value, '"' ) === 0 ) $value = substr( $value, 1, strlen( $value ) - 2 );
					}
			 		break;
			 	default:
			 		break;
			}
		}

		$atts = shortcode_atts( 
			array(
				'userpwd' => false,
				'method' => 'GET',
				'timeout' => 10,
				'url' => '',
				'selector' => false,
				'remove' => false,
				'find' => false,
				'replace' => false,
				'htmlentities' => false,
				'strip_tags' => false,
				'cache' => true,
				'cache_ttl' => 3600,
			), 
			$atts
		);

		// convert %QUOT% to "
		$atts['find'] = $this->quote_replace( $atts['find'] );
		$atts['replace'] = $this->quote_replace( $atts['replace'] );

		// extract attributes
		extract( $atts );

		// normalize parameters
		$is_cache = strtolower( $cache ) != 'false';
		$is_htmlentities = strtolower( $htmlentities ) == 'true';
		$is_strip_tags = strtolower( $strip_tags ) == 'true';
		$method = strtoupper( $method );

		$group = 'remote_content_cache';
		$key = implode( $atts );
		$error = false;
		if ( ! $is_cache || false === ( $response = wp_cache_get( $key, $group ) ) ){

			// if we don't have a url, don't bother
			if ( empty( $url ) ) return;

			// get the user:password BASIC AUTH
			if ( ! empty( $userpwd ) ){
				global $post;
				if ( false!==( $meta_userpwd = get_post_meta( $userpwd, $post->ID, true ) ) ) {
					// if the userpwd is a post meta, use that
					$userpwd = $meta_userpwd;
				} elseif ( false !== ( $option_userpwd = get_option( $userpwd ) ) ) {
					// if the userpwd is a site option, use that
					$userpwd = $option_userpwd;
				} elseif ( defined( $userpwd ) ) {
					// if the userpwd is a constant, use that
					$userpwd = constant( $userpwd );
				} 
				/* lastly assume the userpwd is plaintext, this is not safe as it will be
				 displayed in the browser if this plugin is disabled */
			}

			$ch = curl_init();									// set up curl
			curl_setopt( $ch, CURLOPT_URL, $url );				// the url to request
			curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );		// set a timeout
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );	// return to variable
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );	// don't verify host ssl cert
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );	// don't verify peer ssl cert
			if ( ! empty( $userpwd ) )
				curl_setopt( $ch, CURLOPT_USERPWD, $userpwd );	// send a user:password
			if ( $method == 'POST' )
				curl_setopt( $ch, CURLOPT_POST, true );			// optionally POST
			if ( ! empty( $content ) )
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $content ); // send content of tag
			if ( false === ( $response = curl_exec( $ch ) ) )	// fetch remote contents
				$error = curl_error( $ch );						// if we get an error, use that
			curl_close( $ch );									// close the resource

			if ( $response ){
				
				if ( $selector || $remove ){
					include_once( 'inc/phpQuery.php' );			// include phpQuery	
					phpQuery::newDocument( $response );			// load the response HTML DOM
					if ( $remove )								// $remove defaults to false
						pq( $remove )->remove();				// remove() the elements
					$response = pq( $selector );				// use a CSS selector or default to everything
				}

				if ( $find )									// perform a regex find and replace
					$response = preg_replace( $find, $replace | '', $response );

				if ( $is_strip_tags ) 
					$response = strip_tags( $response );		// strip the tags

				if ( $is_htmlentities )
					$response = htmlentities( $response );		// HTML encode the response

			} else {
				$response = $error;								// send back the error unmodified so we can debug
			}

			if ( $is_cache )
				wp_cache_set( $key, $response, $group, $cache_ttl );	// Cache the result based on the TTL
		}
		return $response;
	}

	private function quote_replace( $input ){
		if ( ! $input ) return false;
		return str_replace( '%QUOT%', '"', strval( $input ) );
	}
}

// init the class/shortcode
RemoteContentShortcode::init();

endif; //class exists
