<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "PonyDocs MediaWiki Extension" );
}
/**
 * Class to manage product versions in PonyDocs MediaWiki. 
 * Each instance represents a defined product version based on the
 * PONYDOCS_DOCUMENTATION_NAMESPACE_NAME  . ':' . [ProductShortName] . PONYDOCS_PRODUCTVERSION_SUFFIX special page.
 * It also statically contains the complete list of defined versions per product and their mappings.
 */
class PonyDocsProductVersion {
	/**
	 * Constants for status types available.
	 *
	 * @staticvar
	 */
	const STATUS_RELEASED = 0;
	const STATUS_UNRELEASED = 1;
	const STATUS_PREVIEW = 2;
	const STATUS_UNKNOWN = -1;
	const STATUS_INVALID = -2;

	/**
	 * The short name of the version.
	 * This can be of any form but is typically a decimal point delimited version number (3.9.1) or a "code name."
	 *
	 * @var string
	 */
	protected $vShortName = '';

	/**
	 * The status of this release;  one of:  released, unreleased, preview.  Should be checked.
	 * @var string
	 */
	protected $vStatus = '';

	/**
	 * The long name of the version.
	 * 
	 * @var string
	 */
	protected $vLongName;
	
	/**
	 * Status code (see consts above).
	 * @var integer
	 */
	protected $vStatusCode = self::STATUS_UNKNOWN;

	/**
	 * Product Name
	 * @var string
	 */
	protected $pName = '';

	/**
	 * Version group
	 * @var string
	 */
	protected $versionGroup;

	/**
	 * Version group message
	 * @var string
	 */
	protected $versionGroupMessage;

	/**
	 * STATIC MEMBERS
	 * 
	 * This Class has dual use; these hold info on ALL versions defined.
	 * Each contains a list and a map.
	 * The list is just simple PHP list storing objects.
	 * We need this to be able to select the 'most recent' easily by using size-1.
	 * The map is an associative array of version NAME to object,
	 * which is needed for lookups but cannot be used in a size-1 sort of way (?).
	 * It's messy and I hate it but it works for now. We store a complete list then a separate list for each type.
	 */
	static protected $sVersionList = array( );
	static protected $sVersionMap = array( );

	static protected $sVersionListReleased = array( );
	static protected $sVersionMapReleased = array( );

	static protected $sVersionListUnreleased = array( );
	static protected $sVersionMapUnreleased = array( );

	static protected $sVersionListPreview = array( );
	static protected $sVersionMapPreview = array( );

	static protected $sProductList = array( );
	static protected $sProductMap = array( );

	/**
	 * Construct a representation of a single version.
	 * 
	 * @param string $versionShortName Actual name of version, such as 1.0.2 or Foo.
	 * @param string $status Version status: released, unreleased, preview.
	 * @param string $versionLongName Long name of version.
	 */
	public function __construct( $productNameShort, $versionShortName, $versionStatus, $versionLongName ) {
		if ( !preg_match( PONYDOCS_PRODUCTVERSION_REGEX, $versionShortName ) 
			|| !preg_match( PONYDOCS_PRODUCT_REGEX, $productNameShort ) ) {
			$this->vStatusCode = self::STATUS_INVALID;
			return;
		}
		$this->pName = $productNameShort;
		$this->vShortName = $versionShortName;
		$this->vStatus = strtolower( $versionStatus );
		$this->vStatusCode = self::StatusToInt( $this->vStatus );
		$this->vLongName = $versionLongName;
		$this->vLongName = strlen( $versionLongName ) ? $versionLongName : $versionShortName;
	}

	/**
	 * Converts a status string to the integer const.
	 *
	 * @static
	 * @param string $status
	 * @returns integer
	 */
	static public function StatusToInt( $status ) {
		$status = strtolower( $status );
		if ( !strcmp( $status, 'released' ) ) {
			return self::STATUS_RELEASED;
		} elseif ( !strcmp( $status, 'unreleased' ) ) {
			return self::STATUS_UNRELEASED;
		} elseif ( !strcmp( $status, 'preview' ) ) {
			return self::STATUS_PREVIEW;
		} else {
			return self::STATUS_UNKNOWN;
		}
	}

	/**
	 * Return the short name of the version.
	 *
	 * @return string Short Name of version.
	 */
	public function getVersionShortName() {
		return $this->vShortName;
	}

	/**
	 * Return the status of the version (released, unreleased, or preview).
	 *
	 * @return string Status string.
	 */
	public function getVersionStatus() {
		return $this->vStatus;
	}

	/**
	 * Return the long name of the version.
	 *
	 * @return string Long Name of version.
	 */
	public function getVersionLongName() {
		return $this->vLongName;
	}
	
	/**
	 * Return the name of the product.
	 *
	 * @return string Name of product.
	 */
	public function getProductName() {
		return $this->pName;
	}

	/**
	 * Return the status of the version (released, unreleased, or preview) as integer code.
	 *
	 * @return integer
	 */
	public function getStatusCode() {
		return $this->vStatusCode;
	}

	/**
	 * Return true if status code is valid, false if not.
	 *
	 * @return boolean
	 */
	public function isValid() {
		return (( $this->vStatusCode == self::STATUS_UNKNOWN ) || ( $this->vStatusCode == self::STATUS_INVALID )) ? FALSE : TRUE;
	}

	/**
	 * Get version group message
	 *
	 * @return string Version group message
	 */
	public function getVersionGroupMessage() {
		return $this->versionGroupMessage;
	}

	/**
	 * Set version group and optionally group message
	 *
	 * @param string Version group
	 */
	public function setVersionGroup( $group, $message = null ) {
		$this->versionGroup = $group;
		$this->versionGroupMessage = $message;
	}

	/**
	 * This returns the selected version for the current user.
	 * This is stored in our session data, whether the user is logged in or not.
	 * A special session variable 'wsVersion' contains it.
	 * If it is not set we must apply some logic to auto-select the proper version.
	 * Typically if it is not set it means the user just loaded the site for the first time this session
	 * and is thus not logged in, so its a safe bet to auto-select the most recent RELEASED version.
	 * We're only going to use sessions to track this.
	 * 
	 * @param string $productName product short name for which selected version will be retrieved
	 * @param boolean $setDefault optional whether to attempt to set default version if none is currently set
	 * @static
	 * 
	 * @return string Currently selected version string.
	 */
	static public function GetSelectedVersion( $productName, $setDefault = true ) {

		self::LoadVersionsForProduct( $productName );
		
		/**
		 * Do we have the session var and is it non-zero length?  Could also check if valid here.
		 */
		if ( isset( $_SESSION['wsVersion'][$productName] )
			&& strlen( $_SESSION['wsVersion'][$productName] )
			&& isset( self::$sVersionMap[$productName]) && count(self::$sVersionMap[$productName] ) ) {
			// Make sure version exists.
			if ( !array_key_exists( $_SESSION['wsVersion'][$productName], self::$sVersionMap[$productName] ) ) {
				if ( PONYDOCS_DEBUG ) {
					error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] unsetting version key $productName/"
						. $_SESSION['wsVersion'][$productName] );
				}
				unset( $_SESSION['wsVersion'][$productName] );
			} else {
				if ( PONYDOCS_DEBUG ) {
					error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] getting selected version $productName/"
						. $_SESSION['wsVersion'][$productName] );
				}
				return $_SESSION['wsVersion'][$productName];
			}
		}

		if ( $setDefault 
			&& isset( self::$sVersionList[$productName] )
			&& is_array( self::$sVersionList[$productName] )
			&& sizeof( self::$sVersionList[$productName] ) > 0 ) {
			if ( PONYDOCS_DEBUG ) {
				error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__
					. "] no selected version for $productName; will attempt to set default." );
			}

			/**
			* If we're here, we don't have a version set previously.
			* Get latest version the current user can see (released, unreleased or preview)
			* and set the our active version to it. Check for released
			* first.
			*/
			if ( isset( self::$sVersionListReleased[$productName] ) && sizeof( self::$sVersionListReleased[$productName] ) ) {
				$versionIndex = count( self::$sVersionListReleased[$productName] ) - 1;
				$versionName = self::$sVersionListReleased[$productName][$versionIndex]->getVersionShortName();
			} else {
				$versionIndex = count( self::$sVersionList[$productName] ) - 1;
				$versionName = self::$sVersionList[$productName][$versionIndex]->getVersionShortName();
			}
			self::SetSelectedVersion( $productName, $versionName );
			if ( PONYDOCS_DEBUG ) {
				error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__
					. "] setting selected version to $productName/$versionName" );
			}
			
		}

		if ( isset( $_SESSION['wsVersion'][$productName] ) ) {
			if ( PONYDOCS_DEBUG ) {
				error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] getting selected version $productName/"
					. $_SESSION['wsVersion'][$productName] );
			}
			return $_SESSION['wsVersion'][$productName];
		}
		if ( PONYDOCS_DEBUG ) {
			error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] getting selected version NULL" );
		}
		return NULL;
	}

	/**
	 * This sets the selected version by updating the session variable.
	 * There are times it seems this isn't working, with older sessions...fix this somehow?
	 * Call session_start() if session_id() has no strlen?
	 *
	 * @static
	 * @param string $v Version name to set.
	 * @return string Version which was set.
	 */
	static public function SetSelectedVersion( $productName, $v ) {

		self::LoadVersionsForProduct( $productName );

		if ( $v == "latest" ) {
			$latest = self::GetLatestReleasedVersion( $productName );
			if ( $latest != NULL ) {
				$v = $latest->vShortName;
			}
		}

		if ( isset( self::$sVersionMap[$productName][$v] ) ) {
			$_SESSION['wsVersion'][$productName] = $v;
			if ( PONYDOCS_DEBUG ) {
				error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] setting selected version to $productName/$v" );
			}
			return $v;
		} else {
			if ( PONYDOCS_DEBUG ) {
				error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__
					. "] not setting selected version; returning null. $productName/$v");
			}
			return NULL;
		}
		
	}

	/**
	 * Loads our version data from the special page.
	 * These are defined in the form: {{#version:shortName|longName|status}}
	 *
	 * There is a special parser hook to handle outputting this in a clean form when viewing the page.
	 * This updates our internal static maps and lists of versions (total and by each state) from this page.
	 * The first call will load from the file, subsequent will just return the stored list (unless $reload=true).
	 *
	 * The list created does NOT simply contain all versions in the defined page
	 * it is dependent upon the GROUPS to which the current user belongs.
	 *
	 *	- Anonymous (all):  Released ONLY.
	 *  - Customers/users:  Released AND preview ONLY.
	 *  - Emp/Author:		All.
	 *
	 * @static
	 * @param boolean $reload True to force reload from the wiki page.
	 * @return array LIST of all versions (not map!).
	 * 
	 * @todo: Cache this?
	 * @todo: Throw an exception if $productName is not set
	 */
	static public function LoadVersionsForProduct( $productName, $reload = false, $ignorePermissions = false ) {
		global $wgPonyDocsEmployeeGroup, $wgUser;
		
		/**
		 * If we have content in our list, just return that unless $reload is true.
		 */
		if ( isset( self::$sVersionList[$productName] ) && !$reload ) {
			return self::$sVersionList[$productName];
		}
		$groups = $wgUser->getGroups();

		self::$sVersionList[$productName] = array();
		
		$article = new Article(
			Title::newFromText( PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productName .
			PONYDOCS_PRODUCTVERSION_SUFFIX ), 0 );

		$content = $article->getContent();
			
		if ( !$content ) {
			/**
			 * There is no versions file found -- just return.
			 */
			return array();
		}

		/**
		 * Parse our versions content which should be of the form:
		 * {{#version:name|status}}
		 * ...
		 * Validate 'STATUS' is valid; if it is not, we ignore it.  THis will populate
		 * $this->versionsList (and others) properly and return it.
		 */
		$authProductGroup = PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_PRODUCT, $productName );
		$authPreviewGroup = PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_VERSION, $productName );
		$versions = explode( "\n", $content );
		$currentGroup = NULL;
		$currentGroupMessage = NULL;
	
		foreach( $versions as $v ) {
			// versiongroup
			if ( preg_match( '/{{#versiongroup:\s*([^}]*)\s*}}/i', $v ) ) {
				$matches = preg_replace( '/{{#versiongroup:\s*([^}]*)\s*}}/i', '\\1', $v );
				$pcs = explode( '|', trim( $matches ), 2 );
				if ( count( $pcs ) === 1 && trim( $pcs[0] ) === '' ) {
					// reset group and message
					$currentGroup = NULL;
					$currentGroupMessage = NULL;
				} else {
					// set group name and message, if present
					$currentGroup = $pcs[0];
					if ( isset( $pcs[1] ) ) {
						$currentGroupMessage = $pcs[1];
					}
				}
			// version (if it's neither versiongroup nor version it's a blank line or other garbage and we can skip it)
			} elseif ( preg_match( '/{{#version:/', $v ) ) {
				$matches = preg_replace( '/{{#version:\s*(.*)\s*}}/i', '\\1', $v );
				$pcs = explode( '|', trim( $matches ), 3 );
				$versionLongName = (isset($pcs[2])) ? $pcs[2] : '';
			
				$pVersion = new PonyDocsProductVersion( $productName, $pcs[0], $pcs[1], $versionLongName );
				if ( !$pVersion->isValid() ) {
					continue;
				}

				if ( isset($currentGroup) ) {
					$pVersion->setVersionGroup( $currentGroup, $currentGroupMessage );
				}
				if ( !strcasecmp( $pcs[1], 'UNRELEASED' ) ) {
					// unreleased versions are only instantiated if the user can accces them
					if ( in_array( $wgPonyDocsEmployeeGroup, $groups )
						|| in_array( $authProductGroup, $groups )
						|| PonyDocsCrawlerPassthrough::isAllowedCrawler()
						|| $ignorePermissions) {
							self::$sVersionList[$productName][]
								= self::$sVersionListUnreleased[$productName][]
								= self::$sVersionMap[$productName][$pcs[0]]
								= self::$sVersionMapUnreleased[$productName][$pcs[0]]
								= $pVersion;
					}
				} elseif ( !strcasecmp( $pcs[1], 'RELEASED' ) ) {
					self::$sVersionList[$productName][]
						= self::$sVersionListReleased[$productName][]
						= self::$sVersionMap[$productName][$pcs[0]]
						= self::$sVersionMapReleased[$productName][$pcs[0]]
						= $pVersion;
				} elseif ( !strcasecmp( $pcs[1], 'PREVIEW' ) ) {
					if ( in_array( $wgPonyDocsEmployeeGroup, $groups )
						|| in_array( $authProductGroup, $groups )
						|| in_array( $authPreviewGroup, $groups )
						|| PonyDocsCrawlerPassthrough::isAllowedCrawler()
						|| $ignorePermissions ) {
							self::$sVersionList[$productName][]
								= self::$sVersionListPreview[$productName][]
								= self::$sVersionMap[$productName][$pcs[0]]
								= self::$sVersionMapPreview[$productName][$pcs[0]]
								= $pVersion;
					}
				}
			}
		}

		return self::$sVersionList[$productName];
	}

	/**
	 * Returns whether or not a supplied version is defined.
	 *
	 * @param string $productName
	 * @param string $versionName Version name OR old-style Version Category tag
	 * @return boolean
	 * 
	 * @static
	 * @TODO: Remove old-style Version Category tag option, since we no longer use tags like that (so it must be a dead path)
	 */
	static public function IsVersion( $productName, $versionName ) {
                $versionName = preg_replace( '/([^' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)/', '', $versionName );
		if ( preg_match( '/^v:(.*)/i', $versionName, $match ) ) {
			$versionName = $match[1];
		}
		return isset( self::$sVersionMap[$productName][$versionName] );
	}

	/**
	 * Returns whether or not a supplied version is defined.
	 *
	 * @param string $productName
	 * @param string $versionName
	 * @return boolean
	 * 
	 * @static
	 */
	static public function isReleasedVersion( $productName, $versionName ) {
		return isset( self::$sVersionMapReleased[$productName][$versionName] );
	}

	/**
	 * Retrieve a PonyDocsProductVersion instance by product and version name, or return FALSE if it does not exist.
	 *
	 * @param string $productName
	 * @param string $name Version short name OR deprecated "V:VERSION" format
	 * @return PonyDocsProductVersion OR boolean
	 */
	static public function GetVersionByName( $productName, $name ) {
		// Ensure versions list has been initialized
		self::LoadVersionsForProduct( $productName );
		
		// Extract version name from category tag
		// TODO: I think this is unused
		if ( preg_match( '/^v:(.*)/i', $name, $match ) ) {
			$name = $match[1];
		}
		if ( isset( self::$sVersionMap[$productName][$name] ) ) {
			return self::$sVersionMap[$productName][$name];
		}

		return FALSE;
	}

	/**
	 * Alias for LoadVersions.
	 *
	 * @static
	 * @return array Returns out version LIST.
	 */
	static public function GetVersions( $productName, $asMap = FALSE ) {
		$v = self::LoadVersionsForProduct( $productName );
		if ( $asMap ) {
			return self::$sVersionMap[$productName];
		}
		return $v;
	}

	/**
	 * Return a list of our versions in released state.
	 *
	 * @param boolean $asMap Should we return the map version
	 * @static
	 * @return array
	 */
	static public function & GetReleasedVersions( $productName, $asMap = FALSE ) {
		if ( $asMap ) {
			return self::$sVersionMapReleased[$productName];
		}
		return self::$sVersionListReleased[$productName];
	}

	/**
	 * Return a list of our unreleased versions.
	 *
	 * @static 	
	 * @return array
	 */	
	static public function & GetUnreleasedVersions( $productName ) {
		return self::$sVersionListUnreleased[$productName];
	}

	/**
	 * Return a list of our preview versions.
	 *
	 * @static 
	 * @return array
	 */
	static public function & GetPreviewVersions( $productName ) {
		return self::$sVersionListPreview[$productName];
	}

	static public function & GetLatestVersion( $productName ) {
		if ( sizeof( self::$sVersionList[$productName] ) ) {
			return self::$sVersionList[$productName][sizeof( self::$sVersionList[$productName] ) - 1];
		}
	}

	static public function & GetLatestReleasedVersion( $productName ) {
		if ( isset( self::$sVersionListReleased[$productName] ) && sizeof( self::$sVersionListReleased[$productName] ) ) {
			return self::$sVersionListReleased[$productName][sizeof( self::$sVersionListReleased[$productName] ) - 1];
		}
	}

	static public function & GetLatestUnreleasedVersion( $productName ) {
		if ( sizeof( self::$sVersionListUnreleased[$productName] ) ) {
			return self::$sVersionListUnreleased[$productName][sizeof( self::$sVersionListUnreleased[$productName] ) - 1];
		}
	}

	static public function & GetLatestPreviewVersion( $productName ) {
		if ( sizeof( self::$sVersionListPreview[$productName] ) ) {
			return self::$sVersionListPreview[$productName][sizeof( self::$sVersionListPreview[$productName] ) - 1];
		}
	}

	/**
	 * If the Version does not exist, return the latest released Version.
	 * 
	 * @param string $productName
	 * @param string $versionName
	 * @return PonyDocsProductVersion $version
	 */
	static public function getVersionOrLatestReleasedVersion( $productName, $versionName ) {
		if ( self::IsVersion( $productName, $versionName ) ) {
			$version = self::$sVersionMap[$productName][$versionName];
		} else {
			$version = self::GetLatestReleasedVersion( $productName );
		}
		
		return $version;
	}

	/**
	 * Return an array listing versions in ascending order that the current user has access to based on group membership.
	 * - sysop/author/employee:	All.
	 * - user: All released and preview versions.
	 * - anon: All released versions.
	 *
	 * Result is a mapping of version name to PonyDocsProductVersion objects.
	 *
	 * @FIXME: I don't think we need this?
	 *         The LoadVersions() method only loads versions from the defined list based on the user's permissions anyway.
	 *
	 * @static
	 * @return array Map of PonyDocsProductVersion instances (name => object).
	 */
	static public function GetVersionsForUser( $productName ) {
		global $wgPonyDocsEmployeeGroup, $wgUser;
		$groups = $wgUser->getGroups( );
		$authProductGroup = PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_PRODUCT, $productName );
		$authPreviewGroup = PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_VERSION, $productName );

		if ( in_array( $authProductGroup, $groups )
			|| in_array( $wgPonyDocsEmployeeGroup, $groups )
			|| PonyDocsCrawlerPassthrough::isAllowedCrawler() ) {
			return self::$sVersionMap[$productName];
		} elseif ( in_array( $authPreviewGroup, $groups ) ) {
			$retList = array();
			foreach ( self::$sVersionMap[$productName] as $pVersion ) {
				if ( ( $pVersion->getStatusCode( ) == self::STATUS_RELEASED )
					|| ( $pVersion->getStatusCode( ) == self::STATUS_PREVIEW ) ) {
					$retList[] = $pVersion;
				}
			}
			return $retList;
		} else {
			return self::$sVersionMapReleased[$productName];
		}
	}

	/**
	 * Given a LIST of PonyDocsProductVersion objects determine which of them is the "earliest" version based upon our internal list of
	 * versions (which are ordered).
	 *
	 * @static
	 * @param array $versionList
	 * @return PonyDocsProductVersion
	 */
	static public function findEarliest( $productName, $versionList = array() ) {
		$earliest = -1;

		foreach ( $versionList as $pV ) {
			$idx = array_search( $pV, self::$sVersionList[$productName] );
			if ( $idx === false ) {
				continue;
			}
			if ( -1 == $earliest ) {
				$earliest = $idx;
			} elseif ( $idx < $earliest ) {
				$earliest = $idx;
			}
		}

		return isset( self::$sVersionList[$productName][$earliest] ) ? self::$sVersionList[$productName][$earliest] : NULL;
	}

	static public function clearNAVCache( PonyDocsProductVersion $version ) {
		error_log( "INFO [" . __METHOD__ . "] Deleting cache entry of NAV for product " . $version->getProductName()
			. " version " . $version->getVersionShortName());
		$cache = PonyDocsCache::getInstance();
		$key = "NAVDATA-" . $version->getProductName() . "-" . $version->getVersionShortName();
		$cache->remove( $key );
	}

	/**
	 * Create a URL path (e.g. Documentation/Foo/latest) for a ProductVersion
	 * 
	 * @param string $productName - Optional. We'll get the selected product (which defaults to the default product) if empty
	 * @param string $versionName - Optional. We'll get the selected version (which defaults to the latest release) if empty
	 * 
	 * @return string
	 */
	static public function getVersionURLPath( $productName = NULL, $versionName = NULL ) {
		global $wgArticlePath;
		
		if ( !isset( $productName ) || ! PonyDocsProduct::isProduct( $productName ) ) {
			$productName = getSelectedProduct()->getShortName();
		}
		
		if ( !isset( $versionName ) ) {
			$versionName = self::GetSelectedVersion( $productName );
		}
		
		$latestVersion = PonyDocsProductVersion::GetLatestReleasedVersion( $productName );
		if ( $latestVersion ) {
			if ( $versionName == $latestVersion->getVersionShortName() ) {
				$versionName = 'latest';
			}
		}
		
		$base = str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME, $wgArticlePath );
		return "$base/$productName/$versionName";
	}
}

/**
 * This is a callback for usort() which compares two versions to determine which is earlier.
 * It is passed two PonyDocsProductVersion instances and returns
 * -1 if $vA is earlier than $vB, 0 if they are the same, and 1 if
 * $vB is earlier than $vA.
 * You can then use usort( $versionList, _ponydocs_versionCmp ) and $versionList will come back sorted.
 * 
 * @param PonyDocsProductVersion $vA First version to compare.
 * @param PonyDocsProductVersion $vB Second version to compare.
 * @return integer
 */
function PonyDocs_versionCmp( $vA, $vB ) {
	$versions = PonyDocsProductVersion::GetVersions( $productName );

	$indexA = array_search( $vA, $versions );
	$indexB = array_search( $vB, $versions );

	if ( $indexA == $indexB ) {
		return 0;
	}
	return ( $indexA < $indexB ) ? -1 : 1;
}

/**
 * This is a callback for usort() which compares two versions to determine which is earlier.
 * It is passed two PonyDocsProductVersion instances and returns
 * -1 if $vA is earlier than $vB, 0 if they are the same, and 1 if $vB is earlier than $vA.
 * You can then use usort( $versionList, _ponydocs_versionCmp )and $versionList will come back sorted.
 * 
 * @param PonyDocsProductVersion $vA First version to compare.
 * @param PonyDocsProductVersion $vB Second version to compare.
 * @return integer
 */
function PonyDocs_ProductVersionCmp( PonyDocsProductVersion $vA, PonyDocsProductVersion $vB ) {
	$versions = PonyDocsProductVersion::GetVersions( $vA->getProductName() );

	$indexA = array_search( $vA, $versions );
	$indexB = array_search( $vB, $versions );

	if ( $indexA == $indexB ) {
		return 0;
	}
	return ( $indexA < $indexB ) ? -1 : 1;
}
