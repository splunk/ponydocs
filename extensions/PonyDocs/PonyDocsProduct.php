<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );


/**
 * An instance represents a single PonyDocs product based upon the short/long name.  It also contains static
 * methods and data for loading the global list of products from the special page. 
 */
class PonyDocsProduct
{
	/**
	 * Short/abbreviated name for the product used in page paths;  it is always lowercase and should be
	 * alphabetic but is not required.
	 *
	 * @var string
	 */
	protected $mShortName;

	/**
	 * Long name for the product which functions as the 'display' name in the list of products and so
	 * forth.
	 *
	 * @var string
	 */
	protected $mLongName;

	/**
	 * Long name for the product which functions as the 'display' name in the list of products and so
	 * forth.
	 *
	 * @access protected
	 * @var string
	 */
	protected $mParent;

	/**
	 * Long name for the product which functions as the 'display' name in the list of products and so
	 * forth.
	 * 
	 * @access protected
	 * @var string
	 */
	protected $mDescription;

	/**
	 * Stores whether product instance is defined as static
	 *
	 * @var boolean
	 */
	protected $static;

	/**
	 * Our list of products loaded from the special page, stored statically.  This only contains the products
	 * which have a TOC defined and tagged to the currently selected version.
	 *
	 * @var array
	 */
	static protected $sProductList = array( );

	/**
	 * Our COMPLETE list of products.
	 *
	 * @var array
	 */
	static protected $sDefinedProductList = array( );
	
	/**
	 * @access protected
	 * 
	 * @var array $sParentChildMap An array mapping parents to child products
	 */
	static protected $sParentChildMap = array();
	
	/**
	 * Constructor is simply passed the short and long (display) name.  We convert the short name to lowercase
	 * immediately so we don't have to deal with case sensitivity.
	 *
	 * @param string $shortName  Short name used to refernce product in URLs.
	 * @param string $longName   Display name for product.
	 * @param string $status     Status for product. One of: hidden
	 */
	public function __construct($shortName, $longName = '', $description = '', $parent = '') {
		$this->mShortName = preg_replace( '/([^' . PONYDOCS_PRODUCT_LEGALCHARS . '])/', '', $shortName );
		$this->mLongName = strlen( $longName ) ? $longName : $shortName;
		$this->mDescription = $description;
		$this->mParent = $parent;
	}

	public function getShortName() {
		return $this->mShortName;
	}

	public function getLongName() {
		return $this->mLongName;
	}

	public function getParent() {
		return $this->mParent;
	}

	public function getDescription() {
		return $this->mDescription;
	}
	
	public function setStatic($static) {
		$this->static = $static;
	}

	public function isStatic() {
		return $this->static;
	}

	/**
	 * This loads the list of products BASED ON whether each product defined has a TOC defined for the
	 * currently selected version or not.
	 *
	 * @param boolean $reload
	 * 
	 * @return array
	 */

	static public function LoadProducts($reload = false) {
		$dbr = wfGetDB(DB_SLAVE);

		/**
		 * If we have content in our list, just return that unless $reload is true.
		 */
		if(sizeof(self::$sProductList) && !$reload) {
			return self::$sProductList;
		}

		self::$sProductList = array();

		// Use 0 as the last parameter to enforce getting latest revision of this article.
		$article = new Article(Title::newFromText( PONYDOCS_DOCUMENTATION_PRODUCTS_TITLE), 0);
		$content = $article->getContent();

		if( !$article->exists()) {
			 // There is no products file found -- just return.
			return array( );
		}

		/**
		 * The content of this topic should be of this form:
		 * {{#product:shortName|Long Product Name|description|parent}}{{#product:anotherProduct|...
		 * 
		 * There is a user defined parser hook which converts this into useful output when viewing as well.
		 * 
		 * Then query categorylinks to only add the product if it has a tagged TOC file with the selected version.
		 * Otherwise, skip it!
		 * 
		 * NOTE product is the top entity, we need to verify better it has at least one version defined
		 */

		$tags = explode('}}', $content); // explode on the closing tag to get an array of products
		foreach ($tags as $tag) {
			$tag = trim($tag);
			if (strpos($tag, '{{#product:') === 0) { 
				
				// Remove the opening tag and prefix
				$product = str_replace('{{#product:', '', $tag);   
				$parameters = explode('|', $product);
				$parameters = array_map('trim', $parameters);

				// Set static flag if defined as static
				$static = false;
				if (strpos($parameters[0], PONYDOCS_PRODUCT_STATIC_PREFIX) === 0) {
					$parameters[0] = substr($parameters[0], strlen(PONYDOCS_PRODUCT_STATIC_PREFIX));
					$static = true;
				}

				// Allow admins to omit optional parameters
				foreach (array(1, 2, 3) as $index) {
					if (!array_key_exists($index, $parameters)) {
						$parameters[$index] = '';
					}
				}
				
				// Avoid wedging the product page with a fatal error if shortName 
				// is omitted by some crazy nihilist
				if (isset($parameters[0]) && $parameters[0] != '') {
					$pProduct = new PonyDocsProduct($parameters[0], $parameters[1], $parameters[2], $parameters[3]);
					$pProduct->setStatic($static);
					self::$sDefinedProductList[$pProduct->getShortName()] = $pProduct;
					self::$sProductList[$parameters[0]] = $pProduct;
					if (isset($parameters[3]) && $parameters[3] != '') {
						// key is parent, value is array of children
						self::$sParentChildMap[$parameters[3]][] = $parameters[0];
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
	static public function GetProducts( )
	{
		return self::LoadProducts( );
	}

	/**
	 * Return list of ALL defined products regardless of selected version.
	 *
	 * @static 	
	 * @returns array
	 */
	static public function GetDefinedProducts( )
	{
		self::LoadProducts( );
		return self::$sDefinedProductList;
	}

	/**
	 * Our product list is a map of 'short' name to the PonyDocsProduct object.  Returns it, or null if not found.
	 *
	 * @static
	 * @param string $shortName
	 * @return PonyDocsProduct&
	 */
	static public function GetProductByShortName( $shortName )
	{
		$convertedName = preg_replace( '/([^' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)/', '', $shortName );
		if( self::IsProduct( $convertedName ))
			return self::$sDefinedProductList[$convertedName];
		return null;
	}

	/**
	 * Test whether a given product exists (is in our list).  
	 *
	 * @static
	 * @param string $shortName
	 * @return boolean
	 */
	static public function IsProduct( $shortName )
	{
		// We no longer specify to reload the product data, because that's just 
		// insanity.
		PonyDocsProduct::LoadProducts(false);
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
	static public function GetCurrentProduct($title = null )
	{
		global $wgTitle;
		$targetTitle = $title == null ? $wgTitle : $title;
		$pcs = explode( ':', $targetTitle->__toString( ));
		if( !PonyDocsProduct::IsProduct( $pcs[1] ))
			return null;
		return PonyDocsProduct::GetProductByShortName( $pcs[1] );
	}

	/**
	 * This returns the selected product for the current user.  This is stored in our session data, whether the user is
	 * logged in or not.  A special session variable 'wsProduct' contains it.  If it is not set we must apply some logic
	 * to auto-select the proper product.  Typically if it is not set it means the user just loaded the site for the
	 * first time this session and is thus not logged in, so its a safe bet to auto-select the most recent RELEASED
	 * product. We're only going to use sessions to track this. 
	 *
	 *
	 * @static
	 * @return string Currently selected product string.
	 */
	static public function GetSelectedProduct( )
	{
		global $wgUser;

		$groups = $wgUser->getGroups();
		self::LoadProducts();

		/**
		 * Do we have the session var and is it non-zero length?  Could also check if valid here.
		 */
		if( isset( $_SESSION['wsProduct'] ) && strlen( $_SESSION['wsProduct'] )) {
			// Make sure product exists.
			if(!array_key_exists($_SESSION['wsProduct'], self::$sProductList)) {
				if (PONYDOCS_SESSION_DEBUG) {error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] product " . $_SESSION['wsProduct'] . " not found in " . print_r(self::$sProductList, true));}
				if (PONYDOCS_SESSION_DEBUG) {error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] unsetting product key " . $_SESSION['wsProduct']);}
				unset($_SESSION['wsProduct']);
			}
			else {
				if (PONYDOCS_SESSION_DEBUG) {error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] getting selected product " . $_SESSION['wsProduct']);}
				return $_SESSION['wsProduct'];
			}
		}
		if (PONYDOCS_SESSION_DEBUG) {error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] no selected product; will attempt to set default");}
		/// If we are here there is no product set, use default product from configuration
		self::SetSelectedProduct(PONYDOCS_DEFAULT_PRODUCT);
		if (PONYDOCS_SESSION_DEBUG) {error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] getting selected product " . $_SESSION['wsProduct']);}
		return $_SESSION['wsProduct'];
	}

	static public function SetSelectedProduct( $p )
	{
		//global $_SESSION;
		$_SESSION['wsProduct'] = $p;
		if (PONYDOCS_SESSION_DEBUG) {error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] setting selected product to $p");}
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
	static public function getChildProducts($productName) {
		self::GetProducts();
		$parentChildMap = self::$sParentChildMap;
		if (isset($parentChildMap[$productName])) {
			return $parentChildMap[$productName];
		} else {
			return array();
		}
	}
}

/**
 * End of file.
 */
?>