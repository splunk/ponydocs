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
$wgSpecialPages['TOCList'] = 'SpecialTOCList';

/**
 * Simple 'Special' MediaWiki page which must list all defined TOC management pages (as links) along with the
 * list of versions for which they are tagged.  Additionally it provides links to the special Manuals and
 * Versions management pages for easier access to this functionality.
 */
class SpecialTOCList extends SpecialPage
{
	/**
	 * Just call the base class constructor and pass the 'name' of the page as defined in $wgSpecialPages.
	 */
	public function __construct( )
	{
		SpecialPage::__construct( "TOCList" );
	}
	
	public function getDescription( )
	{
		return 'Table of Contents Management';
	}

	/**
	 * This is called upon loading the special page.  It should write output to the page with $wgOut.
	 */
	public function execute( )
	{
		global $wgOut, $wgArticlePath;

		$dbr = wfGetDB( DB_SLAVE );

		$this->setHeaders( );

		/**
		 * We need to select ALL pages of the form:
		 * 	Documentation:<productShortName>:<manualShortName>TOC*
		 * We should group these by manual and then by descending version order.  The simplest way is by assuming that every TOC
		 * page is linked to at least one version (category) and thus has an entry in the categorylinks table.  So to do this we
		 * must run this query for each manual type, which involes getting the list of manuals defined.
		 */
		$out			  = array( );
		$product		  = PonyDocsProduct::GetSelectedProduct( );
		$manuals		  = PonyDocsProductManual::GetDefinedManuals( $product );
		$allowed_versions = array();

		$p = PonyDocsProduct::GetProductByShortName($product);
		$wgOut->setPagetitle( 'Table of Contents Management' );
		$wgOut->addHTML( '<h2>Table of Contents Management Pages for ' . $p->getLongName() . '</h2>' );
		
		foreach (PonyDocsProductVersion::GetVersions($product) as $v) $allowed_versions[] = $v->getVersionName();
		
		foreach( $manuals as $pMan ) {
			$qry = "SELECT DISTINCT( cl_sortkey, cl_sortkey_prefix )"
				. " FROM categorylinks"
				. " WHERE cl_sortkey LIKE 'DOCUMENTATION:" . $dbr->strencode( strtoupper( $product ) ) . ':'
				. $dbr->strencode( strtoupper( $pMan->getShortName( ))) . "TOC%'";

			$res = $dbr->query( $qry );

			while( $row = $dbr->fetchObject( $res ) ) {
				$subres = $dbr->select(
					'categorylinks', 'cl_to', "cl_sortkey = '" . $dbr->strencode( $row->cl_sortkey ) . "'", __METHOD__ );
				$versions = array();

				while( $subrow = $dbr->fetchObject( $subres ) ) {
					if ( preg_match( '/^V:' . $product . ':(.*)/i', $subrow->cl_to, $vmatch )
						&& in_array( $vmatch[1], $allowed_versions ) ) {
						$versions[] = $vmatch[1];
					}
				}

				if ( sizeof( $versions ) ) {
					$wgOut->addHTML( '<a href="' . str_replace( '$1', $row->cl_sortkey_prefix, $wgArticlePath ) . '">'
						. $row->cl_sortkey_prefix . '</a> - Versions: ' . implode( ' | ', $versions ) . '<br />' );
				}
			}
		}

		$html = '<h2>Other Useful Management Pages</h2>' .
				'<a href="' . str_replace( '$1', PONYDOCS_DOCUMENTATION_PREFIX . $product . PONYDOCS_PRODUCTVERSION_SUFFIX, $wgArticlePath ) . '">Version Management</a> - Define and update available ' . $product . ' versions.<br />' .
				'<a href="' . str_replace( '$1', PONYDOCS_DOCUMENTATION_PREFIX . $product . PONYDOCS_PRODUCTMANUAL_SUFFIX, $wgArticlePath ) . '">Manuals Management</a> - Define the list of available manuals for the Documentation namespace.<br/><br/>';

		$wgOut->addHTML( $html );
	}
}