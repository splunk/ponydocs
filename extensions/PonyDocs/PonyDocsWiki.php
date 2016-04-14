<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );

/**
 * Singleton class to manage our PonyDocs customized wiki.  This manages things likes the manual list, version list, and
 * methods to manage them.  Any hooks/extension type methods belong in our PonyDocsExtension class as static methods so
 * we can contain them in one place.  This will primarily be used as an access point and provide methods for returning
 * sets of data in template-ready form (as simple arrays) so that the templates never need to directly use our PonyDocs
 * classes.
 */
class PonyDocsWiki
{
	/**
	 * Our singleton instance.
	 *
	 * @var PonyDocsWiki
	 */
	static protected $instance = array();

	/**
	 * Made private to enforce singleton pattern.  On instantiation (through the first call to 'getInstance') we cache our
	 * versions and manuals [we don't save them we just cause them to load -- is this necessary?].
	 */
	private function __construct( $product )
	{
		/**
		 * @FIXME:  Only necessary in Documentation namespace!
		 */
		PonyDocsProductVersion::LoadVersionsForProduct( $product, true );
		PonyDocsProductManual::LoadManualsForProduct( $product, true );
	}

	/**
	 * Return our static singleton instance of the class or initialize if not existing.
	 *
	 * @static
	 * @return PonyDocsWiki
	 */
	static public function &getInstance( $product )
	{
		if( !isset(self::$instance[$product]) )
			self::$instance[$product] = new PonyDocsWiki( $product );
		return self::$instance[$product];
	}

	/**
	 * This returns the list of available products for template output in a more useful way for templates.  
	 * It is a simple list with each element being an associative array containing three keys: name status, and parent
	 * 
	 * @FIXME:  If a product has NO defined versions it should be REMOVED from this list.
	 *
	 * @return array  array of product arrays, keyed by shortname
	 */
	public function getProductsForTemplate() {
		$product = PonyDocsProduct::GetProducts();
		$productAry = array();

		foreach ($product as $p) {

			// Only add product to list if it has versions visible to this user
			$valid = FALSE;
			$versions = PonyDocsProductVersion::LoadVersionsForProduct($p->getShortName());
			if (!empty($versions)) {
				$valid = TRUE;
			} elseif (empty($versions)) {
				// Check for children with visibile versions
				foreach (PonyDocsProduct::getChildProducts($p->getShortName()) as $childProductName) {
					$childVersions = PonyDocsProductVersion::LoadVersionsForProduct($childProductName);
					if (!empty($childVersions)) {
						$valid = TRUE;
						break;
					}
				}
			}

			if ($valid) {
				$productAry[$p->getShortname()] = array(
					'name' => $p->getShortName(),
					'label' => $p->getLongName(),
					'description' => $p->getDescription(),
					'parent' => $p->getParent(),
					'categories' => $p->getCategories(),
				);
			}
		}

		return $productAry;
	}

	/**
	 * Return a simple associative array format for template output of all versions which apply to the supplied topic.
	 *
	 * @param PonyDocsTopic $pTopic Topic to obtain versions for.
	 * @return array
	 */
	public function getVersionsForTopic( PonyDocsTopic &$pTopic ) {
		global $wgArticlePath;

		$versions = array();
		foreach ( $pTopic->getProductVersions() as $productVersion ) {
			$versions[] = array(
				'name' => $productVersion->getVersionShortName(),
				'href' => str_replace(
					'$1', 
					'Category:V:' . $productVersion->getProductName() . ':' . $productVersion->getVersionShortName(),
					$wgArticlePath )
			);
		}

		return $versions;
	}

	/**
	 * This returns the list of available versions for template output in a more useful way for templates.  It is a simple list
	 * with each element being an associative array containing two keys:  name and status.
	 * 
	 * @FIXME:  If a version has NO defined manuals (i.e. no TOC pages for a manual tagged to it) it should be REMOVED from this
	 * list.
	 *
	 * @return array
	 */
	public function getVersionsForProduct( $productName )
	{
		$dbr = wfGetDB( DB_SLAVE );
		$version = PonyDocsProductVersion::GetVersions( $productName );
		$validVersions = $out = array( );

		/**
		 * This should give us one row per version which has 1 or more TOCs tagged to it.  So basically, if its not in this list
		 * it should not be displayed.
		 */
		$res = PonyDocsCategoryLinks::getTOCCountsByProductVersion( $productName );

		while( $row = $dbr->fetchObject( $res ))
			$validVersions[] = $row->cl_to;			

		foreach( $version as $v )
		{
			/**
			 * 	Only add it to our available list if its in our list of valid versions.
			 *	NOTE disabled for now
			 */
			//if( in_array( 'V:' . $v->getVersionShortName( ), $validVersions ))
				$out[] = array( 'name' => $v->getVersionShortName( ), 'status' => $v->getVersionStatus( ));
		}

		return $out;
	}
	
	/**
	 * This returns the list of available manuals (active ones) in a more useful way for templates.  It is an associative array
	 * where the key is the short name of the manual and the value is the display/long name.
	 *
	 * @return array
	 */
	public function getManualsForProduct( $product )
	{
		PonyDocsProductVersion::LoadVersionsForProduct($product); 	// Dependency
		PonyDocsProductVersion::getSelectedVersion($product);
		$manuals = PonyDocsProductManual::LoadManualsForProduct($product);	// Dependency

		$out = array( );
		foreach( $manuals as $m )
			$out[$m->getShortName( )] = $m->getLongName( );

		return $out;
	}

	/**
	 * Populate toolbox link set and return.  Should be based on user groups/access.  Only used if MediaWiki:Sidebar is EMPTY.
	 *
	 * @return array
	 */
	public function generateSideBar( )
	{
		global $wgArticlePath, $wgScriptPath, $wgUser, $wgPonyDocsEmployeeGroup;
		$authProductGroup = PonyDocsExtension::getDerivedGroup(PonyDocsExtension::ACCESS_GROUP_PRODUCT);

		$g = $wgUser->getAllGroups( );

		$sidebar = array( 'navigation' => array(
			array( 'text' => 'Main Page', 'href' => str_replace( '$1', 'Main_Page', $wgArticlePath )),
			array( 'text' => 'Help', 'href' => str_replace( '$1', 'helppage', $wgArticlePath ))
			)
		);

		/**
		 * Show Special pages if employee or author.
		 */
		if( in_array( $authProductGroup, $g ) || in_array( $wgPonyDocsEmployeeGroup, $g ))
			$sidebar['navigation'][] = array( 'text' => 'Special Pages', 'href' => str_replace( '$1', 'Special:Specialpages', $wgArticlePath ));

		/**
		 * TOC List Mgmt if author.
		 */
		if( in_array( $authorGroupByProduct, $g ))
			$sidebar['navigation'][] = array( 'text' => 'TOC List Mgmt', 'href' => str_replace( '$1', 'Special:TOCList', $wgArticlePath ));

		//echo '<pre>'; print_r( $sidebar ); die( );

		return $sidebar;
	}
}

/**
 * End of file
 */
?>
