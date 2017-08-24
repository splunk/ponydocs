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
	static public function isAllowedCrawler() {
		global $wgRequest;
		if ( $wgRequest->getIP() 
			&& defined( 'PONYDOCS_CRAWLER_ADDRESS' )
			&& $wgRequest->getCustomisedIP() == PONYDOCS_CRAWLER_ADDRESS
			&& isset( $_SERVER['HTTP_USER_AGENT'] )
			&& defined( 'PONYDOCS_CRAWLER_USERAGENT_REGEX' )
			&& preg_match( PONYDOCS_CRAWLER_USERAGENT_REGEX, $_SERVER['HTTP_USER_AGENT'] ) ) {
			$return = TRUE;
		} else {
			$return = FALSE;
		}
		
		if ( PONYDOCS_DEBUG ) {
			error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "]"
			. " ip={$wgRequest->getIP()} crawlerAddress=" . PONYDOCS_CRAWLER_ADDRESS
			. " useragent=\"{$_SERVER['HTTP_USER_AGENT']}\" regex=\"" . PONYDOCS_CRAWLER_USERAGENT_REGEX . "\""
			. " crawler=$return");
		}
		return $return;
	}
}