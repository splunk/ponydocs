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
$wgSpecialPages['DocListing'] = 'SpecialDocListing';

/**
 * Special Page which lists all release versions, and their manuals.  Used for a 
 * start page which could be used for search crawl.
 */
class SpecialDocListing extends SpecialPage
{
	/**
	 * Just call the base class constructor and pass the 'name' of the page as defined in $wgSpecialPages.
	 */
	public function __construct( )
	{
		SpecialPage::__construct( "DocListing" );
	}

	public function getDescription( )
	{
		return 'Documentation Listing';
	}

	/**
	 * This is called upon loading the special page.  It should write output to the page with $wgOut.
	 */
	public function execute( )
	{
		global $wgOut, $wgScriptPath;
		global $wgUser;
		
		$dbr = wfGetDB( DB_SLAVE );

		$this->setHeaders( );
		$wgOut->setPagetitle( 'Documentation Listing' );

		$wgOut->addHTML( 	"<p>This page lists all release versions as well as links to the manuals in each version.</p><br><br>" );

		ob_start();

		// Get all versions and iterate

		$product = PonyDocsProduct::GetSelectedProduct();
		$versions = PonyDocsProductVersion::GetReleasedVersions($product, true);

		?>
		<ul>
		<?php
		foreach($versions as $version) {
			?>
			<li><?php echo $version->getVersionName();?>
				<ul>
					<?php
					// Load Manuals for version
					PonyDocsProductVersion::SetSelectedVersion($version->getProductName(), $version->getVersionName());
					$navData = PonyDocsExtension::fetchNavdataForVersion($version->getProductName(), $version->getVersionName());
					foreach($navData as $nav) {
						$url = str_replace(PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $version->getProductName() . "/latest/", PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $version->getProductName() . "/" . $version->getVersionName() . "/", $nav['firstUrl']);
						?>
						<li><a href="<?php echo $url;?>"><?php echo $nav['longName'];?></a></li>
						<?php
					}
					?>
				</ul>
			</li>
			<?php
		}
		?>
		</ul>
		<?php

		$output = ob_get_clean();

		$wgOut->addHTML( $output );
	}
};
?>