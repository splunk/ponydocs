<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "PonyDocs MediaWiki Extension" );
}

/**
 * An instance represents a single PonyDocs manual based upon the short/long name.  It also contains static
 * methods and data for loading the global list of manuals from the special page. 
 */
class PonyDocsProductManual
{
	/**
	 * @access protected
	 * @var string Short/abbreviated name for the manual used in page paths
	 */
	protected $mShortName;

	/**
	 * @access protected
	 * @var string Long name for the manual which functions as the 'display' name in the list of manuals.
	 */
	protected $mLongName;

	/**
	 * @access protected
	 * @var array Categories that this Manual is in
	 */
	protected $mCategories;

	/** 
	 * @access protected
	 * @var string Product name
	 */
	protected $pName;

	/**
	 * @access protected 
	 * @var boolean Stores whether manual is defined as static
	 */
	protected $static;

	/**
	 * @access protected
	 * @static
	 * @var array List of manuals loaded from the special page which have a TOC defined for the currently selected version.
	 */
	static protected $sManualList = array( );

	/**
	 * @access protected
	 * @static
	 * @var array Complete list of manuals.
	 */
	static protected $sDefinedManualList = array( );
	
	/**
	 * @access protected
	 * @static
	 * @var array An array mapping Categories to Manuals which have a TOC defined for the currently selected version
	 */
	static protected $sCategoryMap = array();

	/**
	 * Manual constructor, called primarily by LoadManualsForProduct()
	 *
	 * @param string $shortName Short name used to refernce manual in URLs.
	 * @param string $longName Display name for manual.
	 */
	public function __construct( $pName, $shortName, $longName = '', $categories = '', $static = FALSE ) {
		$this->mShortName = preg_replace( '/([^' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS . '])/', '', $shortName );
		$this->pName = $pName;
		$this->mLongName = strlen( $longName ) ? $longName : $shortName;
		$this->mCategories = $categories && $categories != '' ? explode( ',', $categories ) : array();
		$this->static = $static;
	}

	public function getShortName() {
		return $this->mShortName;
	}
	
	public function getLongName() {
		return $this->mLongName;
	}
	
	/**
	 * Getter for categories
	 * @return array
	 */
	public function getCategories() {
		return $this->mCategories;
	}

	public function getProductName() {
		return $this->pName;
	}

	/**
	 * Is this manual static?
	 * @return boolean
	 */
	public function isStatic() {
		return $this->static;
	}
	
	/**
	 * Get existing static documentation version names for this manual
	 * @return array of versionName strings
	 */
	public function getStaticVersionNames() {
		$return = FALSE;
		if ( $this->static ) {
			$versionNames = array();
			$directory = PONYDOCS_STATIC_DIR . DIRECTORY_SEPARATOR . $this->pName;
			if ( is_dir( $directory ) ) {
				$versionNames = scandir( $directory );
				foreach ( $versionNames as $i => $versionName ) {
					if ( $versionName == '.'
						|| $versionName == '..'
						|| !is_dir(
							$directory . DIRECTORY_SEPARATOR . $versionName . DIRECTORY_SEPARATOR . $this->mShortName ) ) {
						unset( $versionNames[$i] );
					}
				}
			}
			$return = $versionNames;
		}
		
		return $return;
	}

	/**
	 * This loads the list of manuals BASED ON whether each manual defined has a TOC defined for the
	 * currently selected version or not.
	 * 
	 * @param string $productName
	 * @param boolean $reload
	 * @param boolean $respectVersion do we only want manuals with TOCs for the current version? or do we want all the manuals?
	 * @return array
	 */

	static public function LoadManualsForProduct( $productName, $reload = FALSE ) {
		$dbr = wfGetDB( DB_SLAVE );

		/**
		 * If we have content in our list, just return that unless $reload is true.
		 */
		if ( isset(self::$sManualList[$productName]) && sizeof( self::$sManualList[$productName] ) && !$reload ) {
			return self::$sManualList[$productName];
		}

		self::$sManualList[$productName] = array();
		self::$sCategoryMap[$productName] = array();

		// Use 0 as the last parameter to enforce getting latest revision of this article.
		$article = new Article( Title::newFromText( PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productName .
			PONYDOCS_PRODUCTMANUAL_SUFFIX ), 0);
		// The content of this topic should be of the form {{#manual:shortName|longName}}
		$content = $article->getContent( );

		// Gate for no manuals page
		if ( !$article->exists() ) {
			return array();
		}

		// Explode on the closing tag to get an array of manual tags
		$tags = explode( '}}', $content );
		foreach ( $tags as $tag ) {
			$tag = trim( $tag );
			if ( strpos( $tag, '{{#manual:' ) === 0 ) { 
				
				// Remove the opening tag and prefix
				$manual = str_replace( '{{#manual:', '', $tag ); 
				$parameters = explode( '|', $manual );
				$parameters = array_map( 'trim', $parameters );	
				// Pad out array to avoid notices
				$parameters = array_pad( $parameters, 3, '');
				
				// Set static flag if defined as static
				$static = FALSE;
				if ( strpos( $parameters[0], PONYDOCS_PRODUCT_STATIC_PREFIX ) === 0 ) {
					$parameters[0] = substr( $parameters[0], strlen(PONYDOCS_PRODUCT_STATIC_PREFIX ) );
					$static = TRUE;
				}
				$pManual = new PonyDocsProductManual( $productName, $parameters[0], $parameters[1], $parameters[2], $static );

				self::$sDefinedManualList[$productName][strtolower( $pManual->getShortName() )] = $pManual;
				
				// Skip non-static manuals w/o TOCs
				if ( !$static ) {
					$res = PonyDocsCategoryLinks::getTOCByProductManualVersion(
						$productName, $pManual->getShortName(), PonyDocsProductVersion::GetSelectedVersion( $productName ) );
					if ( !$res->numRows() ) {
						continue;
					}
				}

				// Handle Manual Categories
				if ( isset( $parameters[2] ) && $parameters[2] != '' ) {
					$categories = explode( ',', $parameters[2] );
					foreach ( $categories as $category ) {
						self::$sCategoryMap[$productName][$category][$pManual->getShortName()] = $pManual;
					}
				} else {
					self::$sCategoryMap[$productName][PONYDOCS_NO_CATEGORY][$pManual->getShortName()] = $pManual;
				}

				self::$sManualList[$productName][strtolower( $pManual->getShortName() )] = $pManual;
			}
		}

		return self::$sManualList[$productName];
	}

	/**
	 * Return list of ALL defined manuals regardless of selected version.
	 *
	 * @static
	 * @returns array
	 */
	static public function GetDefinedManuals( $productName ) {
		self::LoadManualsForProduct( $productName );
		return self::$sDefinedManualList[$productName];
	}

	/**
	 * Return manuals by category
	 * @static
	 * @return array
	 */
	static public function getManualsByCategory( $productName ) {
		self::LoadManualsForProduct( $productName );
		return self::$sCategoryMap[$productName];
	}
	
	/**
	 * Our manual list is a map of 'short' name to the PonyDocsManual object.  Returns it, or null if not found.
	 *
	 * @static
	 * @param string $shortName
	 * @return PonyDocsManual&
	 */
	static public function & GetManualByShortName( $productName, $shortName ) {
		$convertedName = preg_replace( '/([^' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS . ']+)/', '', $shortName );
		if ( self::IsManual( $productName, $convertedName ) ) {
			return self::$sDefinedManualList[$productName][strtolower( $convertedName )];
		}
	}

	/**
	 * Test whether a given manual exists (is in our list).  
	 *
	 * @static
	 * @param string $shortName
	 * @return boolean
	 */
	static public function IsManual( $productName, $shortName ) {
		// We no longer specify to reload the manual data, because that's just 
		// insanity.
		self::LoadManualsForProduct($productName, false);
		// Should just force our manuals to load, just in case.
		$convertedName = preg_replace( '/([^' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS . ']+)/', '', $shortName );
		return isset( self::$sDefinedManualList[$productName][strtolower($convertedName)] );
	}

	/**
	 * Return the current manual object based on the title object;  returns null otherwise.
	 *
	 * @static
	 * @return PonyDocsManual
	 */
	static public function GetCurrentManual($productName, $title = null ) {
		global $wgTitle;
		$targetTitle = $title == null ? $wgTitle : $title;
		$pcs = explode( ':', $targetTitle->__toString( ));
		if( !isset($pcs[2]) || !self::IsManual( $productName, $pcs[2] ))
			return null;
		return self::GetManualByShortName( $productName, $pcs[2] );
	}

	/**
	 * Create a URL path (e.g. Documentation/Foo/latest/Manual) for a Manual
	 * 
	 * @param string $productName
	 * @param string $manualName
	 * @param string $versionName - Optional. We'll get the selected version (which defaults to 'latest') if empty
	 * 
	 * @return string
	 */
	static public function getManualURLPath( $productName, $manualName, $versionName = NULL ) {
		global $wgArticlePath;

		if (! isset( $versionName ) ) {
			$versionName = PonyDocsProductVersion::GetSelectedVersion( $productName );
		}
		
		$latestVersion = PonyDocsProductVersion::GetLatestReleasedVersion( $productName );
		if ( $latestVersion ) {
			if ( $versionName == $latestVersion->getVersionShortName() ) {
				$versionName = 'latest';
			}
		}
		
		
		$base = str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME, $wgArticlePath );
		return "$base/$productName/$versionName/$manualName";
	}
};
