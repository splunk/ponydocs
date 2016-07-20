<?php
if( !defined( 'MEDIAWIKI' ) ) {
	die( "PonyDocs MediaWiki Extension" );
}

/**
 * An instance represents a single PonyDocs product based upon the short/long name.
 * It also contains static methods and data for loading the global list of products from the special page.
 */
class PonyDocsProduct
{
	/**
	 * @access protected
	 * @var string Short/abbreviated name for the Product used in URLs
	 */
	protected $mShortName;

	/**
	 * @access protected
	 * @var string Long name for the Product which functions as the 'display' name in the list of Product and so forth.
	 */
	protected $mLongName;

	/**
	 * @access protected
	 * @var string Description of the Product, displayed on the landing page/product list
	 */
	protected $mDescription;

	/**
	 * @access protected
	 * @var string Parent Product short name
	 */
	protected $mParent;

	/**
	 * @access protected
	 * @var array Categories that this Product is in
	 */
	protected $mCategories;

	/**
	 * @access protected
	 * @var boolean Is the Product static?
	 */
	protected $static;

	/**
	 * @access protected
	 * @static
	 * @var array Products which have a TOC defined for the currently selected version as productName -> PonyDocsProduct
	 * TODO: AFAICT, the above line is a lie, and this is identical to sDefinedProductList
	 */
	static protected $sProductList = array();

	/**
	 * @access protected
	 * @static
	 * @var array Complete list of Products
	 */
	static protected $sDefinedProductList = array();

	/**
	 * @access protected
	 * @static
	 * @var array An array mapping parents to child Products
	 */
	static protected $sParentChildMap = array();

	/**
	 * @access protected
	 * @static
	 * @var array An array mapping Categories to Products which have a TOC defined for the currently selected version
	 */
	static protected $sCategoryMap = array();

	/**
	 * Constructor is simply passed the short and long (display) name.  We convert the short name to lowercase
	 * immediately so we don't have to deal with case sensitivity.
	 *
	 * @param string $shortName  Short name used to refernce product in URLs.
	 * @param string $longName Display name for product.
	 * @param string $status   Status for product. One of: hidden
	 */
	public function __construct( $shortName, $longName = '', $description = '', $parent = '', $categories = '', $static = FALSE ) {
		$this->mShortName = preg_replace( '/([^' . PONYDOCS_PRODUCT_LEGALCHARS . '])/', '', $shortName );
		$this->mLongName = strlen( $longName ) ? $longName : $shortName;
		$this->mDescription = $description;
		$this->mParent = $parent;
		$this->mCategories = $categories && $categories != '' ? explode( ',', $categories ) : array();
	}

	/**
	 * Getter for short name
	 * @return string
	 */
	public function getShortName() {
		return $this->mShortName;
	}

	/**
	 * Getter for long name
	 * Strip forbidden tags from long name
	 * @return string
	 */
	public function getLongName() {
		return strip_tags($this->mLongName, '<del><em><ins><strong><sub><sup>');
	}

	/**
	 * Getter for description
	 * @return string
	 */
	public function getDescription() {
		return $this->mDescription;
	}

	/**
	 * Getter for parent name
	 * @return string
	 */
	public function getParent() {
		return $this->mParent;
	}

	/**
	 * Getter for categories
	 * @return array
	 */
	public function getCategories() {
		return $this->mCategories;
	}

	/**
	 * Is method for static
	 * @return string
	 */
	public function isStatic() {
		return $this->static;
	}

	/**
	 * This loads the list of products.
	 *
	 * @param boolean $reload
	 *
	 * @return array
	 */
	static public function LoadProducts( $reload = FALSE ) {
		$dbr = wfGetDB( DB_SLAVE );

		/**
		 * If we have content in our list, just return that unless $reload is true.
		 */
		if ( sizeof( self::$sProductList ) && !$reload ) {
			return self::$sProductList;
		}

		self::$sProductList = array();

		// Use 0 as the last parameter to enforce getting latest revision of this article.
		$article = new Article( Title::newFromText( PONYDOCS_DOCUMENTATION_PRODUCTS_TITLE ), 0 );
		$content = $article->getContent();

		if ( !$article->exists() ) {
			 // There is no products file found -- just return.
			return array();
		}

		/**
		 * The content of this topic should be of this form:
		 * {{#product:shortName|Long Product Name|description|parent}}{{#product:anotherProduct|...
		 */

		// explode on the closing tag to get an array of products
		$tags = explode( '}}', $content );
		foreach ( $tags as $tag ) {
			$tag = trim( $tag );
			if ( strpos( $tag, '{{#product:' ) === 0 ) {

				// Remove the opening tag and prefix
				$product = str_replace( '{{#product:', '', $tag );
				$parameters = explode( '|', $product );
				$parameters = array_map( 'trim', $parameters );
				// Pad out array to avoid notices
				$parameters = array_pad( $parameters, 5, '');

				// Set static flag if defined as static
				$static = FALSE;
				if ( strpos( $parameters[0], PONYDOCS_PRODUCT_STATIC_PREFIX ) === 0 ) {
					$parameters[0] = substr( $parameters[0], strlen(PONYDOCS_PRODUCT_STATIC_PREFIX ) );
					$static = TRUE;
				}

				// Allow admins to omit optional parameters
				foreach ( array( 1, 2, 3 ) as $index ) {
					if ( !array_key_exists( $index, $parameters ) ) {
						$parameters[$index] = '';
					}
				}

				// Avoid wedging the product page with a fatal error if shortName is omitted by some crazy nihilist
				if ( isset( $parameters[0] ) && $parameters[0] != '' ) {
					$pProduct = new PonyDocsProduct(
						$parameters[0], $parameters[1], $parameters[2], $parameters[3], $parameters[4], $static );
					self::$sDefinedProductList[$pProduct->getShortName()] = $pProduct;
					self::$sProductList[$parameters[0]] = $pProduct;
					// Handle child products
					if ( isset( $parameters[3]) && $parameters[3] != '' ) {
						self::$sParentChildMap[$parameters[3]][] = $parameters[0];
					}
					// Handle product categories
					if ( isset( $parameters[4] ) && $parameters[4] != '' ) {
						$categories = explode( ',', $parameters[4] );
						foreach ( $categories as $category ) {
							self::$sCategoryMap[$category][$parameters[0]] = $pProduct;
						}
					} else {
						self::$sCategoryMap[PONYDOCS_NO_CATEGORY][$parameters[0]] = $pProduct;
					}
				}
			}
		}

		return self::$sProductList;
	}

	/**
	 * Just an alias.
	 *
	 * @static
	 * @return array
	 */
	static public function GetProducts() {
		return self::LoadProducts();
	}

	/**
	 * Return list of ALL defined products regardless of selected version.
	 *
	 * @static
	 * @returns array
	 */
	static public function GetDefinedProducts() {
		self::LoadProducts();
		return self::$sDefinedProductList;
	}

	/**
	 * Return products by category
	 * @static
	 * @return array
	 */
	static public function getProductsByCategory() {
		self::LoadProducts();
		return self::$sCategoryMap;
	}

	/**
	 * Our product list is a map of 'short' name to the PonyDocsProduct object.  Returns it, or null if not found.
	 *
	 * @static
	 * @param string $shortName
	 * @return PonyDocsProduct&
	 */
	static public function GetProductByShortName( $shortName ) {
		$convertedName = preg_replace( '/([^' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)/', '', $shortName );
		if ( self::IsProduct( $convertedName ) ) {
			return self::$sDefinedProductList[$convertedName];
		}
		return NULL;
	}

	/**
	 * Test whether a given product exists (is in our list).
	 *
	 * @static
	 * @param string $shortName
	 * @return boolean
	 */
	static public function IsProduct( $shortName ) {
		// We no longer specify to reload the product data, because that's just insanity.
		PonyDocsProduct::LoadProducts( FALSE );
		// Should just force our products to load, just in case.
		$convertedName = preg_replace( '/([^' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)/', '', $shortName );
		return isset( self::$sDefinedProductList[$convertedName] );
	}

	/**
	 * Return the current product object based on the title object;  returns null otherwise.
	 *
	 * @static
	 * @return PonyDocsProduct
	 */
	static public function GetCurrentProduct( $title = NULL ) {
		global $wgTitle;
		$targetTitle = $title == NULL ? $wgTitle : $title;
		$pcs = explode( ':', $targetTitle->__toString() );
		if ( !PonyDocsProduct::IsProduct( $pcs[1] ) )
			return NULL;
		return PonyDocsProduct::GetProductByShortName( $pcs[1] );
	}

	/**
	 * This returns the selected product for the current user.
	 * This is stored in our session data, whether the user is logged in or not.
	 * A special session variable 'wsProduct' contains it.
	 * If it is not set we must apply some logic to auto-select the proper product.
	 * Typically if it is not set it means the user just loaded the site for the first time this session and is thus not logged in
	 * so its a safe bet to auto-select the most recent RELEASED product.
	 * We're only going to use sessions to track this.
	 *
	 * @static
	 * @return string Currently selected product string.
	 */
	static public function GetSelectedProduct() {
		global $wgUser;

		$groups = $wgUser->getGroups();
		self::LoadProducts();

		/**
		 * Do we have the session var and is it non-zero length?  Could also check if valid here.
		 */
		if ( isset( $_SESSION['wsProduct'] ) && strlen( $_SESSION['wsProduct'] ) ) {
			// Make sure product exists.
			if ( !array_key_exists( $_SESSION['wsProduct'], self::$sProductList ) ) {
				if ( PONYDOCS_DEBUG ) {
					error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] product " . $_SESSION['wsProduct'] . " not found in "
						. print_r( self::$sProductList, true ) );
				}
				if ( PONYDOCS_DEBUG ) {
					error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] unsetting product key " . $_SESSION['wsProduct'] );
				}
				unset( $_SESSION['wsProduct'] );
			} else {
				if ( PONYDOCS_DEBUG ) {
					error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] getting selected product " . $_SESSION['wsProduct'] );
				}
				return $_SESSION['wsProduct'];
			}
		}
		if ( PONYDOCS_DEBUG ) {
			error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] no selected product; will attempt to set default" );
		}
		/// If we are here there is no product set, use default product from configuration
		self::SetSelectedProduct( PONYDOCS_DEFAULT_PRODUCT );
		if ( PONYDOCS_DEBUG ) {
			error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] getting selected product " . $_SESSION['wsProduct'] );
		}
		return $_SESSION['wsProduct'];
	}

	static public function SetSelectedProduct( $p ) {
		//global $_SESSION;
		$_SESSION['wsProduct'] = $p;
		if ( PONYDOCS_DEBUG ) {
			error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] setting selected product to $p");
		}
		return $p;
	}

	/**
	 * Return an array of child products for a given product
	 *
	 * @access public
	 * @static
	 *
	 * @param string $product  short name of a parent product
	 *
	 * @return array  An array of child product short names
	 */
	static public function getChildProducts( $productName ) {
		self::GetProducts();
		$parentChildMap = self::$sParentChildMap;
		if ( isset($parentChildMap[$productName] ) ) {
			return $parentChildMap[$productName];
		} else {
			return array();
		}
	}

	/**
	 * Create a URL path (e.g. Documentation/Foo) for a Product
	 *
	 * @param string $productName - Optional. We'll get the selected product (which defaults to the default product) if empty
	 *
	 * @return string
	 */
	static public function getProductURLPath( $productName = NULL ) {
		global $wgArticlePath;

		if ( !isset( $productName ) || ! self::isProduct( $productName ) ) {
			$productName = getSelectedProduct()->getShortName();
		}

		$base = str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME, $wgArticlePath );
		return "$base/$productName";
	}

	/**
	 * Get existing static documentation versions for this product
	 * @return array of version name strings
	 */
	public function getStaticVersionNames() {
		$return = FALSE;
		if ( $this->static ) {
			$productName = $this->mShortName;
			$versionNames = array();
			$directory = PONYDOCS_STATIC_DIR . DIRECTORY_SEPARATOR . $productName;
			if (is_dir($directory)) {
				$versionNames = scandir($directory);
				foreach ($versionNames as $i => $versionName) {
					if ($versionName == '.' || $versionName == '..') {
						unset($versionNames[$i]);
					}
				}
			}
			$return = $versionNames;
		}

		return $return;
	}
}