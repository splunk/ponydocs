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
			&& isset( $splunkMediaWiki['CrawlerAddress'] )
			&& $wgRequest->getIP() == $splunkMediaWiki['CrawlerAddress']
			&& isset( $_SERVER['HTTP_USER_AGENT'] )
			&& isset( $splunkMediaWiki['CrawlerUserAgentRegex'] )
			&& preg_match( $splunkMediaWiki['CrawlerUserAgentRegex'], $_SERVER['HTTP_USER_AGENT'] ) ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
}