<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( "PonyDocs MediaWiki Extension" );
}

require_once( "$IP/includes/specialpage/SpecialPage.php" );

/**
 * Register our 'Special' page so it is listed and accessible.
 */
$wgSpecialPages['StaticDocServer'] = 'SpecialStaticDocServer';

/**
 * Special page server static docs, checking permissions
 */
class SpecialStaticDocServer extends SpecialPage {

	/**
	 * Just call the base class constructor and pass the 'name' of the page as defined in $wgSpecialPages.
	 */
	public function __construct() {
		SpecialPage::__construct( 'StaticDocServer', '', FALSE );
	}

	/**
	 * Something to show on the special page list
	 * @return string
	 */
	public function getDescription() {
		return 'Static Documentation Server';
	}

	/**
	 * This is called upon loading the special page. It should write output to the page with $wgOut.
	 * 
	 * @param string $par The portion of the URI after Special:StaticDocServer/
	 */
	public function execute( $par ) {
		#TODO: switch to $this->getOuput() and $this->getRequest() when we upgrade MW
		global $wgOut, $wgRequest;
		$wgOut->disable();

		$found = FALSE;
		list( $productName, $versionName, $path ) = explode( '/', $par, 3 );
		if ( substr( $par, -1, 1 ) == '/') {
			$par .= 'index.html';
		}
		
		// Validate parameters are set
		if ( isset( $productName )
			&& isset( $versionName ) 
			// Validate product exists
			&& PonyDocsProduct::GetProductByShortName( $productName )
			// Validate version exists/is accessible by the current user
			&& PonyDocsProductVersion::GetVersionByName( $productName, $versionName ) ) {
			$filename = PONYDOCS_STATIC_DIR . "/$par";
			if ( file_exists( $filename ) ) {
				$found = TRUE;
			}
		}
		
		if ( !$found ) {
			$wgRequest->response()->header( "HTTP/1.1 404 Not Found" );
			echo "<html>\n";
			echo "<head><title>Not Found</title></head>\n";
			echo "<body>\n";
			echo "<h1>Bad Request</h1>\n";
			echo "<div>The documentation you have requested does not exist.</div>\n";
			echo "</body>\n";
			echo "</html>\n";
		} else {
			$mimeMagic = MimeMagic::singleton();
			$pathParts = pathinfo($filename);

			/* get mime-type for a specific file */
			header('Content-type: ' .  $mimeMagic->guessTypesForExtension($pathParts['extension']));
			readfile( $filename );
		}
	}
}