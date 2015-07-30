<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );

/**
 * Needed since we subclass it;  it doesn't seem to be loaded elsewhere.
 */
require_once( "$IP/includes/specialpage/SpecialPage.php" );

/**
 * Register our 'Special' page so it is listed and accessible.
 */
$wgSpecialPages["RecentProductChanges"] = 'SpecialRecentProductChanges';

/**
 * Implements Special:Recentchanges
 * @ingroup SpecialPage
 */
class SpecialRecentProductChanges extends SpecialRecentChanges {
	var $rcOptions, $rcSubpage;

	public function __construct() {
  		SpecialPage::__construct( 'RecentProductChanges');
	}
	
	/**
	 * Returns a human readable description of this special page.
	 *
	 * @returns string
	 */
	public function getDescription( )
	{
		return 'Recent Product Changes';
	}

	/**
	 * Get a FormOptions object containing the default options
	 *
	 * @return FormOptions
	 */
	public function getDefaultOptions() {
		global $wgUser;
		$opts = parent::getDefaultOptions();

		$opts->add( 'product', isset($_GET['product']) ? $_GET['product'] : PonyDocsProduct::GetSelectedProduct());
		return $opts;
	}
	
	public function outputHeader()
	{
		global $wgOut, $wgContLang;
		
		$short_name = (isset($_GET['product']) ? $_GET['product'] : PonyDocsProduct::GetSelectedProduct());
		$product    = PonyDocsProduct::GetProductByShortName($short_name);
		
		$wgOut->addHTML('<h2>' . $product->getLongName() . '</h2>');
	}

	/**
	 * Return an array of conditions depending of options set in $opts
	 *
	 * @param $opts FormOptions
	 * @return array
	 */
	public function buildMainQueryConds( FormOptions $opts ) {
		global $wgUser;

		$dbr = wfGetDB( DB_SLAVE );
		$conds = array();

		# It makes no sense to hide both anons and logged-in users
		# Where this occurs, force anons to be shown
		$forcebot = false;
		if( $opts['hideanons'] && $opts['hideliu'] ){
			# Check if the user wants to show bots only
			if( $opts['hidebots'] ){
				$opts['hideanons'] = false;
			} else {
				$forcebot = true;
				$opts['hidebots'] = false;
			}
		}

		// Calculate cutoff
		$cutoff_unixtime = time() - ( $opts['days'] * 86400 );
		$cutoff_unixtime = $cutoff_unixtime - ($cutoff_unixtime % 86400);
		$cutoff = $dbr->timestamp( $cutoff_unixtime );

		$fromValid = preg_match('/^[0-9]{14}$/', $opts['from']);
		if( $fromValid && $opts['from'] > wfTimestamp(TS_MW,$cutoff) ) {
			$cutoff = $dbr->timestamp($opts['from']);
		} else {
			$opts->reset( 'from' );
		}

		$conds[] = 'rc_timestamp >= ' . $dbr->addQuotes( $cutoff );
		
		// Selected product changes
		$product = addslashes(isset($_GET['product']) ? $_GET['product'] : PonyDocsProduct::GetSelectedProduct());
		
		$conds[] = 'rc_title LIKE "' . $product . '%"';

		$hidePatrol = $wgUser->useRCPatrol() && $opts['hidepatrolled'];
		$hideLoggedInUsers = $opts['hideliu'] && !$forcebot;
		$hideAnonymousUsers = $opts['hideanons'] && !$forcebot;

		if( $opts['hideminor'] )  $conds['rc_minor'] = 0;
		if( $opts['hidebots'] )   $conds['rc_bot'] = 0;
		if( $hidePatrol )		 $conds['rc_patrolled'] = 0;
		if( $forcebot )		   $conds['rc_bot'] = 1;
		if( $hideLoggedInUsers )  $conds[] = 'rc_user = 0';
		if( $hideAnonymousUsers ) $conds[] = 'rc_user != 0';

		if( $opts['hidemyself'] ) {
			if( $wgUser->getId() ) {
				$conds[] = 'rc_user != ' . $dbr->addQuotes( $wgUser->getId() );
			} else {
				$conds[] = 'rc_user_text != ' . $dbr->addQuotes( $wgUser->getName() );
			}
		}

		# Namespace filtering
		if( $opts['namespace'] !== '' ) {
			if( !$opts['invert'] ) {
				$conds[] = 'rc_namespace = ' . $dbr->addQuotes( $opts['namespace'] );
			} else {
				$conds[] = 'rc_namespace != ' . $dbr->addQuotes( $opts['namespace'] );
			}
		}

		return $conds;
	}

	/**
	 * Get the query string to append to feed link URLs.
	 * This is overridden by RCL to add the target parameter
	 */
	public function getFeedQuery() {
		return 'product='.urlencode(isset($_GET['product']) ? $_GET['product'] : PonyDocsProduct::GetSelectedProduct());
	}
}
