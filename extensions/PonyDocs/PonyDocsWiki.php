<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "PonyDocs MediaWiki Extension" );
}

/**
 * Singleton class that initializes various values for PonyDocs
 * There are also some template helper methods here that should probably be moved out to the skins or a separate helper class
 */
class PonyDocsWiki {
	/**
	 * @var $instance PonyDocsWiki
	 */
	static protected $instance;
	
	/**
	 * @var $currentProduct PonyDocsProduct Product in current request
	 * @var $currentVersion PonyDocsProductVersion Version in current request
	 * @var $currentManual PonyDocsProductManual Manual in current request
	 */
	private $currentProduct;
	private $currentVersion;
	private $currentManual;
	
	/**
	 * @var $requestType string Type of request
	 * @var $requestSubtype string Subtype of request
	 */
	private $requestType;
	private $requestSubtype = '';

	/**
	 * Return singleton instance of the class or initialize if not existing.
	 *
	 * @static
	 * @return PonyDocsWiki
	 */
	static public function &getInstance() {
		if ( !isset( self::$instance ) ) {
			self::$instance = new PonyDocsWiki();
		}
		
		return self::$instance;
	}

	/**
	 * Made private to enforce singleton pattern.
	 * On instantiation (through the first call to 'getInstance')
	 * - Initialize version list for the requested product as a static value in PonyDocsProductVersion
	 * - Initialze manuals list for the requested product as a static value in PonyDocsProductManual
	 * - Determine the type of page we're on from the URL. All other code that parses URL or title should be replaced
	 * - Set class variables for current product, version, and manual
	 */
	private function __construct() {
		// Normalize path
		$path = $this->getPath();
		
		if ( $this->isPonyDocsPath( $path ) ) {
			// We need to extract the product name first and initialize manuals and versions before we can run the path typer
			$this->setProductFromPath( $path );
			$this->currentProduct = PonyDocsProduct::GetProductByShortName( PonyDocsProduct::GetSelectedProduct() );
			// If product page lacks default product, we're in trouble.
			if ( !empty( $this->currentProduct ) ) {
				PonyDocsProductVersion::LoadVersionsForProduct( $this->currentProduct->getShortName(), TRUE );
				PonyDocsProductManual::LoadManualsForProduct( $this->currentProduct->getShortName(), TRUE );
				list ( $this->requestType, $this->requestSubtype ) = $this->parsePath( $path );
			}
		}
	}

	/**
	 * Get a normalized path from either PATH_INFO or the query string title parameter
	 * @global string $wgScriptPath
	 * @return string
	 */
	private function getPath() {
		global $wgScriptPath;

		$path = $_SERVER['PATH_INFO'];
		$path = preg_replace("#^/$wgScriptPath#", '', $path);
		
		if ( strpos( $path, 'index.php' ) === 0 ) {
			parse_str( $_SERVER['QUERY_STRING'], $queryParts );
			$path = isset( $queryParts['title'] ) ? $queryParts['title'] : '';
		} 

		return $path;
	}

	/**
	 * Determine if the first part of the path is the ponydocs namespace
	 * 
	 * @param string $path
	 * @return boolean
	 */
	private function isPonyDocsPath( $path ) {
		if ( preg_match( '#^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '$#', $path )
			|| preg_match( '#^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '[/:]#', $path ) ) {
			return TRUE;
		}
		
		return FALSE;
	}

	/**
	 * Set selected product from path
	 * 
	 * @param string $path
	 */
	private function setProductFromPath( $path ) {
		$pathParts = preg_split( '#[/:]#', $path );
		if ( count( $pathParts ) > 1 ) {
			PonyDocsProduct::SetSelectedProduct( $pathParts[1] );
		}
	}

	/**
	 * Parse path and return type array
	 * As a side effect, set currentManual and currentVersion class parameters.
	 * We need to support the following title formats:
	 * - Landing
	 *   - Documentation
	 * - Product or Static Product
	 *   - Documentation/<Product>
	 *   - Documentation/<Product>/<Version>
	 * - Static Manual
	 *   - Documentation/<Product>/<Version>/<Manual>
	 * - Product Management
	 *   - Documentation:Products
	 * - Version Management
	 *   - Documentation:<Product>:<Version>
	 * - Manual Management
	 *   - Documentation:<Product>:<Manual>
	 * - TOC
	 *   - Documentation:<Product>:<Manual>TOC<BaseVersion>
	 * - Topic
	 *   - Documentation/<Product>/<Version>/<Manual>/<Topic>
	 *   - Documentation:<Product>:<Manual>:<Topic>:<BaseVersion>
	 * 
	 * @param string $path
	 * 
	 * @return array of type and subtype
	 */
	private function parsePath( $path ) {
		$type = '';
		$subtype = '';
		
		// Page: Landing. Path: Documentation
		if ( $path == PONYDOCS_DOCUMENTATION_NAMESPACE_NAME ) {
			$type = 'landing';
		// Page: Product management. Path: Documentation:Products
		} elseif ( $path == PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':Products' ) {
			$type = 'management';
			$subtype = 'product';
		} else {
			// Handle slash-separated paths, all of which are either product or static.
			if ( strpos( $path, '/' ) !== FALSE ) {
				$pieces = explode( '/', $path );
				
				// If the first piece isn't a valid product, these won't match
				if ( $pieces[1] == $this->currentProduct->getShortName() ) {
					// Page: Product or Static product. Title: Documentation/<Product>
					if ( count($pieces) == 2 ) {
						if ( $this->currentProduct->isStatic() ) {
							$type = 'static';
							$subtype = 'product';
						} else {
							$type = 'product';
						}
					// Page: Product or Static product. Path: Documentation/<Product>/<Version>
					} elseif ( count( $pieces ) == 3
						&& preg_match(PONYDOCS_PRODUCTVERSION_REGEX, $pieces[2] ) ) {

						$selectedVersionName = PonyDocsProductVersion::SetSelectedVersion( $pieces[1], $pieces[2] );
						
						if (! isset( $selectedVersionName ) ) {
							// this version isn't available to this user; go away
							$defaultRedirect = PonyDocsExtension::getDefaultUrl();
							if ( PONYDOCS_DEBUG ) {
								error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] redirecting to $defaultRedirect" );
							}
							header( "Location: " . $defaultRedirect );
							exit;
						}

						if ( PonyDocsProductVersion::GetSelectedVersion( $pieces[1], FALSE ) ) {
							$this->currentVersion = PonyDocsProductVersion::GetVersionByName( $pieces[1], $pieces[2] );
						}
						
						if ( $this->currentProduct->isStatic() ) {
							$type = 'static';
							$subtype = 'product';
						} else {
							$type = 'product';
						}
					// Page: Static manual. Path: Documentation/<Product>/<Version>/<Manual>
					} elseif ( count( $pieces ) == 4
						&& preg_match( PONYDOCS_PRODUCTVERSION_REGEX, $pieces[2] )
						&& preg_match( PONYDOCS_PRODUCTMANUAL_REGEX, $pieces[3] ) ) {

						$selectedVersionName = PonyDocsProductVersion::SetSelectedVersion( $pieces[1], $pieces[2] );
						if (! isset( $selectedVersionName ) ) {
							// this version isn't available to this user; go away
							$defaultRedirect = PonyDocsExtension::getDefaultUrl();
							if ( PONYDOCS_DEBUG ) {
								error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] redirecting to $defaultRedirect" );
							}
							header( "Location: " . $defaultRedirect );
							exit;
						}

						if ( PonyDocsProductVersion::GetSelectedVersion( $pieces[1], FALSE ) ) {
							$this->currentVersion = PonyDocsProductVersion::GetVersionByName( $pieces[1], $pieces[2] );
						}

						$this->currentManual = PonyDocsProductManual::GetManualByShortName( $pieces[1], $pieces[3] );
						if ( isset( $this->currentManual ) && $this->currentManual->isStatic() ) {
							$type = 'static';
							$subtype = 'manual';
						}
					// Page: Topic. Path: Documentation/<Product>/<Version>/<Manual>/<Topic>
					} elseif ( count ($pieces ) == 5
						&& $pieces[1] == $this->currentProduct->getShortName()
						&& preg_match( PONYDOCS_PRODUCTVERSION_REGEX, $pieces[2] )
						&& preg_match( PONYDOCS_PRODUCTMANUAL_REGEX, $pieces[3] ) ) {

						$this->currentManual = PonyDocsProductManual::GetManualByShortName( $pieces[1], $pieces[3] );
						
						$selectedVersionName = PonyDocsProductVersion::SetSelectedVersion( $pieces[1], $pieces[2] );
						if (! isset( $selectedVersionName ) ) {
							// this version isn't available to this user; go away
							$defaultRedirect = PonyDocsExtension::getDefaultUrl();
							if ( PONYDOCS_DEBUG ) {
								error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] redirecting to $defaultRedirect" );
							}
							header( "Location: " . $defaultRedirect );
							exit;
						}

						$selectedVersion = PonyDocsProductVersion::GetSelectedVersion( $pieces[1], FALSE );
						if ( $selectedVersion ) {
							$this->currentVersion = PonyDocsProductVersion::GetVersionByName( $pieces[1], $selectedVersion );
						}

						$type = 'topic';
						
					}
				}

			// Handle colon-separated paths
			} elseif (strpos( $path, ':') !== FALSE ) {
				$pieces = explode( ':', $path );
				if ( count( $pieces ) == 3
					&& $pieces[1] == $this->currentProduct->getShortName() ) {
					// Page: TOC. Path: Documentation:<Product>:<Manual>TOC<BaseVersion>
					if ( strpos($pieces[2], 'TOC' ) !== FALSE) {
						$type = 'toc';
						$tocPieces = explode( 'TOC', $pieces[2] );
						$this->currentManual = PonyDocsProductManual::GetManualByShortName( $pieces[1], $tocPieces[0] );

						// When there's a base version, only set the version if there isn't already a version set
						if ( !PonyDocsProductVersion::GetSelectedVersion( $pieces[1], FALSE ) ) {
							$selectedVersionName = PonyDocsProductVersion::SetSelectedVersion( $pieces[1], $tocPieces[1] );
							if (! isset( $selectedVersionName ) ) {
								// this version isn't available to this user; go away
								$defaultRedirect = PonyDocsExtension::getDefaultUrl();
								if ( PONYDOCS_DEBUG ) {
									error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] redirecting to $defaultRedirect" );
								}
								header( "Location: " . $defaultRedirect );
								exit;
							}
						}

						if ( PonyDocsProductVersion::GetSelectedVersion( $pieces[1], FALSE ) ) {
							$this->currentVersion = PonyDocsProductVersion::GetVersionByName( $pieces[1], $tocPieces[1] );
						}
					// Page: Manual Management. Path: Documentation:<Product>:Manuals
					} elseif ( $pieces[1] == 'Manuals' ) {
						$type = 'management';
						$subtype = 'manual';
					// Page: Version Management. Path: Documentation:<Product>:Versions
					} elseif ( $pieces[1] == 'Versions' ) {
						$type = 'management';
						$subtype = 'version';
					}
				// Page: Topic. Path: Documentation:<Product>:<Manual>:<Topic>:<BaseVersion>
				} elseif (count($pieces) == 5
					&& $pieces[1] == $this->currentProduct->getShortName()
					&& preg_match( PONYDOCS_PRODUCTMANUAL_REGEX, $pieces[2] )
					&& preg_match( PONYDOCS_PRODUCTVERSION_REGEX, $pieces[4] ) ) {
					
					$this->currentManual = PonyDocsProductManual::GetManualByShortName( $pieces[1], $pieces[2] );

					// When there's a base version, only set the version if there isn't already a version set
					if (! PonyDocsProductVersion::GetSelectedVersion( $pieces[1], FALSE ) ) {
						$selectedVersioName = PonyDocsProductVersion::SetSelectedVersion( $pieces[1], $pieces[4] );
						if (! isset( $selectedVersionName ) ) {
							// this version isn't available to this user; go away
							$defaultRedirect = PonyDocsExtension::getDefaultUrl();
							if ( PONYDOCS_DEBUG ) {
								error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] redirecting to $defaultRedirect" );
							}
							header( "Location: " . $defaultRedirect );
							exit;
						}
					}

					if ( PonyDocsProductVersion::GetSelectedVersion( $pieces[1], FALSE ) ) {
						$this->currentVersion = PonyDocsProductVersion::GetVersionByName( $pieces[1], $pieces[4] );
					}
					
					$type = 'topic';
				}
			}
		}
		
		return array($type, $subtype);
	}
	
	/**
	 * Getters
	 */
	
	public function getCurrentProduct() {
		return $this->currentProduct;
	}
	
	public function getCurrentVersion() {
		return $this->currentVersion;
	}
	
	public function getCurrentManual() {
		return $this->currentManual;
	}
	
	public function getRequestType() {
		return $this->requestType;
	}
	
	public function getRequestSubtype() {
		return $this->requestSubtype;
	}

	/**
	 * Template helper methods
	 */
	
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
			if ( !empty( $versions ) ) {
				$valid = TRUE;
			} elseif ( empty( $versions ) ) {
				// Check for children with visibile versions
				foreach ( PonyDocsProduct::getChildProducts( $p->getShortName() ) as $childProductName ) {
					$childVersions = PonyDocsProductVersion::LoadVersionsForProduct( $childProductName );
					if ( !empty($childVersions ) ) {
						$valid = TRUE;
						break;
					}
				}
			}

			if ( $valid ) {
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
					$wgArticlePath ),
				'longName' => $productVersion->getVersionLongName(),
				'productName' => $productVersion->getProductName(),
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
	public function getVersionsForProduct( $productName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$version = PonyDocsProductVersion::GetVersions( $productName );
		$validVersions = $out = array();

		/**
		 * This should give us one row per version which has 1 or more TOCs tagged to it.  So basically, if its not in this list
		 * it should not be displayed.
		 */
		$res = PonyDocsCategoryLinks::getTOCCountsByProductVersion( $productName );

		while ( $row = $dbr->fetchObject( $res ) ) {
			$validVersions[] = $row->cl_to;
		}

		foreach ( $version as $v ) {
			/**
			 * 	Only add it to our available list if its in our list of valid versions.
			 *	NOTE disabled for now
			 */
			//if( in_array( 'V:' . $v->getVersionShortName( ), $validVersions ))
				$out[] = array( 'name' => $v->getVersionShortName(), 'status' => $v->getVersionStatus(), 'longName' => $v->getVersionLongName() );
		}

		return $out;
	}
	
	/**
	 * This returns the list of available manuals (active ones) in a more useful way for templates.  It is an associative array
	 * where the key is the short name of the manual and the value is the display/long name.
	 *
	 * @return array
	 */
	public function getManualsForProduct( $product ) {
		PonyDocsProductVersion::LoadVersionsForProduct( $product ); 	// Dependency
		PonyDocsProductVersion::getSelectedVersion( $product );
		$manuals = PonyDocsProductManual::LoadManualsForProduct( $product );	// Dependency

		$out = array();
		foreach ( $manuals as $m )
			$out[$m->getShortName()] = $m->getLongName();

		return $out;
	}
}