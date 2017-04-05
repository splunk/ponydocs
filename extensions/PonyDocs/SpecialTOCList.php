<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "PonyDocs MediaWiki Extension" );
}

/**
 * Needed since we subclass it;  it doesn't seem to be loaded elsewhere.
 */
require_once( "$IP/includes/specialpage/SpecialPage.php" );

/**
 * Register our 'Special' page so it is listed and accessible.
 */
$wgSpecialPages['TOCList'] = 'SpecialTOCList';

/**
 * Simple 'Special' MediaWiki page which must list all defined TOC management pages (as links) along with the
 * list of versions for which they are tagged.  Additionally it provides links to the special Manuals and
 * Versions management pages for easier access to this functionality.
 */
class SpecialTOCList extends SpecialPage {
	/**
	 * Just call the base class constructor and pass the 'name' of the page as defined in $wgSpecialPages.
	 */
	public function __construct() {
		SpecialPage::__construct( "TOCList" );
	}
	
	public function getDescription() {
		return 'Table of Contents Management';
	}

	/**
	 * This is called upon loading the special page.  It should write output to the page with $wgOut.
	 * @param string $par the URL path following the special page name
	 */
	public function execute( $par ) {
		global $wgOut, $wgArticlePath;

		$dbr = wfGetDB( DB_SLAVE );

		$this->setHeaders();

		/**
		 * We need to select ALL pages of the form:
		 * 	Documentation:<productShortName>:<manualShortName>TOC*
		 * We should group these by manual and then by descending version order.  The simplest way is by assuming that every TOC
		 * page is linked to at least one version (category) and thus has an entry in the categorylinks table.  So to do this we
		 * must run this query for each manual type, which involes getting the list of manuals defined.
		 */
		$out = array();

		// Added for WEB-10802, looking for product name passed in
		// e.g. /Special:TOCList/Splunk
		$parts = explode( '/', $par );
		if ( isset($parts[0]) && is_string($parts[0]) and $parts[0] != '') {
			$productName = $parts[0];
		} else {
			$productName = PonyDocsProduct::GetSelectedProduct();
		}
		$manuals = PonyDocsProductManual::GetDefinedManuals( $productName );
		$allowed_versions = array();

		$product = PonyDocsProduct::GetProductByShortName( $productName );
		$wgOut->setPagetitle( 'Table of Contents Management' );
		if ( $product ) {
			$wgOut->addHTML( '<h2>Table of Contents Management Pages for ' . $product->getLongName() . '</h2>' );

			foreach ( PonyDocsProductVersion::GetVersions( $productName ) as $v ) {
				$allowed_versions[] = $v->getVersionShortName();
			}

			foreach ( $manuals as $pMan ) {

				$res = $dbr->select(
					array( 'categorylinks', 'page' ),
					array( 'page_title', 'GROUP_CONCAT( cl_to separator "|") categories' ),
					array(
						"cl_from = page_id",
						"page_namespace = '" . NS_PONYDOCS . "'",
						"page_title LIKE '%:" . $dbr->strencode( $pMan->getShortName() ) . "TOC%'",
						"cl_to LIKE 'V:$productName:%'",
						"cl_type = 'page'",
					),
					__METHOD__,
					array( 'GROUP BY' => 'page_title' )
				);

				while ( $row = $dbr->fetchObject( $res ) ) {
					$versions = array();
					$categories = explode( '|', $row->categories );
					foreach ( $categories as $category ) {
						$categoryParts = explode ( ':', $category );
						if ( !empty( $categoryParts[2] ) && in_array( $categoryParts[2], $allowed_versions ) ) {
							$versions[] = $categoryParts[2];
						}

					}

					if ( sizeof( $versions ) ) {
						$wgOut->addHTML( '<a href="' 
							. str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ":{$row->page_title}", $wgArticlePath )
							. '">' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ":{$row->page_title}" . '</a> - Versions: ' 
							. implode( ' | ', $versions ) . '<br />' );
					}
				}
			}

			$html = '<h2>Other Useful Management Pages</h2>' .
					'<a href="' . str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productName .
						PONYDOCS_PRODUCTVERSION_SUFFIX, $wgArticlePath ) .
						'">Version Management</a> - Define and update available ' . $productName .
						' versions.<br />' .
					'<a href="' . str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productName
						. PONYDOCS_PRODUCTMANUAL_SUFFIX, $wgArticlePath )
						. '">Manuals Management</a> - Define the list of available manuals for the Documentation namespace.'
						. '<br/><br/>';

			$wgOut->addHTML( $html );
		} else { 
			$wgOut->addHTML( "<h2>Table of Contents Management Page</h2>Error: Product $productName does not exist." );
		}
	}
}