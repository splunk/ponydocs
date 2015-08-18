<?php

/**
 * Determine if the current user is an allowed crawler
 */
class PonyDocsCrawlerPassthrough {
	
	/**
	 * Check IP address and user agent to determine if the current client is an allowed crawler.
	 * 
	 * @global type $wgRequest
	 * @return boolean
	 */
	static function isAllowedCrawler() {
		global $wgRequest;
		if ( $wgRequest->getIP() 
			&& defined( 'PONYDOCS_CRAWLER_ADDRESS' )
			&& $wgRequest->getIP() == PONYDOCS_CRAWLER_ADDRESS
			&& isset( $_SERVER['HTTP_USER_AGENT'] )
			&& defined( 'PONYDOCS_CRAWLER_USERAGENT_REGEX' )
			&& preg_match( PONYDOCS_CRAWLER_USERAGENT_REGEX, $_SERVER['HTTP_USER_AGENT'] ) ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
}