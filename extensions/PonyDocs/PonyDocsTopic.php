<?php
if ( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );

/**
 * Provides special handling methods/functionality for a "topic", which is an article being viewed or updated in some manner.
 * It is passed the article object and provides methods for retrieving data, 
 * processing and static methods for general tool relation functions.
 */

class PonyDocsTopic {
	/**
	 * The Article instance this topic refers to.
	 *
	 * @var Article
	 */
	protected $pArticle = null;

	/**
	 * The Title instance the Article refers to, retrieved from the $pArticle instance.
	 *
	 * @var Title
	 */
	protected $pTitle = null;

	/**
	 * List of PonyDocsVersion objects to which this topic belongs (tagged).
	 *
	 * @var array
	 */
	protected $versions = array( );

	/**
	 * Contains a regex match array of all internal wiki links found in the article content.
	 *
	 * @var array
	 */
	protected $mWikiLinks = array( );

	/**
	 * Contains a regex match array of all section headers found in the article content.
	 *
	 * @var array
	 */
	protected $mSectionHeaders = array( );

	/**
	 * True if this is a TOPIC in Documentation NS?
	 *
	 * @var boolean
	 */
	protected $mIsDocumentationTopic = false;

	/**
	 * Instantiate this topic with a supplied Article instance.
	 *
	 * @param Article $article
	 */
	public function __construct( Article &$article ) {
		$this->pArticle = $article;
		$this->pTitle = $article->getTitle();
		if ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':.*:.*:.*:.*/i', $this->pTitle->__toString() ) ) {
			$this->mIsDocumentationTopic = TRUE;
		}
	}

	/**
	 * Return our instance of Article.
	 *
	 * @return Article
	 */
	public function & getArticle() {
		return $this->pArticle;
	}

	/**
	 * Return our Title instance.
	 *
	 * @return Title
	 */
	public function & getTitle() {
		return $this->pTitle;
	}

	/**
	 * Return an array of versions for the supplied article.
	 * These are saved as category tags so we need to find the page_id from the article and anything in the categorylinks table.
	 * Return a list with each element being a PonyDocsVersion object.
	 * 
	 * @param boolean $reload If true, force reload from database; else used cache copy (if found).
	 * @return array
	 */
	public function getProductVersions( $reload = FALSE ) {
		if ( sizeof( $this->versions ) && !$reload ) {
			return $this->versions;
		}
		
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			'categorylinks',
			'cl_to',
			array(
				'cl_to LIKE "V:%:%"',
				'cl_type = "page"',
				"cl_sortkey = '" . $dbr->strencode( strtoupper( $this->pTitle->getText() ) ) . "'",
			),
			__METHOD__ 
		);

		$this->versions = array();
		
		while ( $row = $dbr->fetchObject( $res ) ) {
			$version = $this->convertCategoryToVersion( $row->cl_to );
			if ( $version ) {
				$this->versions[] = $version;
			}
		}

		// Sort by the order on the versions admin page
		usort( $this->versions, "PonyDocs_ProductVersionCmp" );		
		return $this->versions;
	}
	
	/**
	 * Convert a category tag to a PonyDocsProductVersion
	 * 
	 * @param string $category
	 * @return PonyDocsProductVersion|NULL
	 */
	public function convertCategoryToVersion( $category ) {
		if ( preg_match( '/^v:(.*):(.*)/i', $category, $match ) ) {
			$version = PonyDocsProductVersion::GetVersionByName( $match[1], $match[2] );
		}
		
		if ( $version ) {
			return $version;
		}
	}

	/**
	 * Given a 'base' topic name (Documentation:User:HowToFoo), find the proper topic name based on selected version.
	 * If 2.1 is selected and we have a HowToFoo2.0 tagged for 2.1, return HowToFoo2.0.
	 *
	 * @param string $baseTopic
	 * @param string $productName
	 */
	static public function GetTopicNameFromBaseAndVersion( $baseTopic, $productName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$pathArray = explode(':', $baseTopic);

		// Gate for pathArray - quit if we don't have a proper $baseTopic
		if (count($pathArray) < 4) {
			return FALSE;
		}
		$manualName = $pathArray[2];
		$topicName = $pathArray[3];

		$res = $dbr->select(
			array('categorylinks', 'page'),
			'page_title' ,
			array(
				"cl_from = page_id",
				"page_namespace = '" . NS_PONYDOCS . "'",
				"cl_to = 'V:$productName:" . $dbr->strencode( PonyDocsProductVersion::GetSelectedVersion( $productName ) ) . "'",
				"cl_type = 'page'",
				// Wildcard product to support product inheritance
				"cl_sortkey LIKE '%:" . $dbr->strencode( strtoupper( "$manualName:$topicName" ) ) . ":%'",
			),
			__METHOD__ 
		);
		
		if ( !$res->numRows() ) {
			return FALSE;
		}

		$row = $dbr->fetchObject( $res );

		return PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ":{$row->page_title}";
	}

	/**
	 * It will first check if there is any cache for h1 and return it .
	 * If there is no cache this takes a title (text form) and extracts the H1 content for the title and returns it.
	 * This is used in display (heading and TOC).
	 *
	 * @static
	 * @param string $title The text form of the title to get the H1 content for.
	 * @return string h1 if no h1 it will set the default title for h1
	 */
	static public function FindH1ForTitle( $title, $headerCacheKey = NULL) {
		
		$cache = PonyDocsCache::getInstance();
		$key = '';
		$h1 = NULL;
		if (!empty($headerCacheKey)) {
			$key = "TOPICHEADERCACHE-" . $headerCacheKey;
			$h1 = $cache->getTopicHeaderCache( $key );
		}
		if ( $h1 === NULL ) {
		
			$article = new Article( Title::newFromText( $title ), 0 );
			$content = $article->loadContent();
			$h1 = FALSE;
			if ($article->getParserOutput()) {
				$sections = $article->getParserOutput()->getSections();
				foreach ( $sections as $section ) {
					if ( $section['level'] == 1 ) {
						$h1 = $section['line'];
						break;
					}
				}
			}
			if ( $h1 === FALSE ) {
				$h1 = $title;
			}
			if (!empty($key)) {
				// Okay, let's store in our cache.
				$cache->put( $key, $h1, TOC_CACHE_TTL);
			}
		}
		return $h1;
	}
	/**
	 * This willremove the cache of topic h1 content
	 * @static
	 * @param string $key The text form of the title
	 */
	static public function clearTopicHeadingCache( $key ) {
		error_log( "INFO [PonyDocsTopic::clearTopicHeaderCache] Deleting cache entry of Topic Heading $key");
		$key = "TOPICHEADERCACHE-" . $key;
		$cache = PonyDocsCache::getInstance();
		$cache->remove($key);
	}

	/**
	 * Given a text string, convert it to a wiki topic name.
	 * @FIXME:  This is probably not entirely correct, it probably does NOT handle all possible characters and their conversions.
	 *
	 * @static
	 * @param string $text Text string to convert to a wiki topic.
	 * @return string 
	 */
	static public function textToWikiTopic( $text ) {
		return preg_replace( SPLUNK_TITLE_REMOVE_REGEX, '', $text );
	}

	/**
	 * This loads/parses the sub-contents for the in-page header TOC display.
	 * This display shows the H2s and H3s for the current topic.
	 *
	 * @return array
	 */
	public function getSubContents() {
		$sections = array();

		if ( !preg_match('/__NOTOC__/', $this->pArticle->getContent() )
			&& $this->pArticle->getParserOutput() ) {
			
			$matches = $this->pArticle->getParserOutput()->getSections();
			$h2 = FALSE;
			$headReference = array();
			$headCount = 0;
			foreach ( $matches as $match ) {
				$level = $match['level'];
				if ( !isset( $headReference[$match['line']] ) ) {
					$headReference[$match['line']] = 1;
				} else {
					$headReference[$match['line']] ++;
				}

				// We don't want to include any H3s that don't have an H2 parent
				if ( $level == 2 || ( $level == 3 && $h2 ) ) {
					if ( $level == 2 ) {
						$h2 = TRUE;
					}
					$headCount = $headReference[$match['line']];
					if ( $headCount > 1 ) {
						$link = '#' . Sanitizer::escapeId( PonyDocsTOC::normalizeSection( $match['line'] ), 'noninitial' ) . '_' . $headCount;
					} else {
						$link = '#' . Sanitizer::escapeId( PonyDocsTOC::normalizeSection( $match['line'] ), 'noninitial' );
					}

					$sections[] = array(
						'level' => $level,
						'link' => $link,
						'text' => $match['line'],
						'class' => 'toclevel-' . round( $level - 1, 0 )
					);
				}
			}
		}

		return $sections;
	}

	/**
	 * Not currently used, but wanted to save the code in case we need it.
	 * This takes an article content and extracts all [[]] style links from it into a match array.
	 * Each array in the list contains 3 indices:
	 * - 0 = Complete matching [[ ]] wiki link tag including braces.
	 * - 1 = The actual link name (such as Documentation:User:HowToFoo3.0).
	 * - 2 = Any alternate text should it be provided;  blank otherwise (but still defined).
	 * 
	 * The regular expression was a massive headache.
	 * 
	 * @returns array
	 */
	private function parseWikiLinks() {
		$re = "/\\[\\[([" . Title::legalChars() . "]+)(?:\\|?(.*?))\]\]/sD";
		$content = $this->pArticle->getContent();
		if ( preg_match_all( $re, $content, $matches, PREG_SET_ORDER ) ) {
			return $matches;
		}
		return array();
	}

	/**
	 * This function returns information about the versions on this topic.
	 * - Version permissions: unreleased, preview, or released
	 * - Version age: older, latest, or newer
	 * Since a Topic can have multiple versions, it's possible for a single topic to be in unreleased, preview, released, older, 
	 * latest, AND newer versions AT THE SAME TIME!
	 * This information can be used by skins to change UI based on the version features.
	 *  
	 * @return array
	 */
	public function getVersionClasses() {
		
		$productName = PonyDocsProduct::getSelectedProduct();
		$versionClasses = array();
		
		$releasedVersions = PonyDocsProductVersion::GetReleasedVersions( $productName );
		// Just the names of our released versions
		$releasedNames = array();
		foreach ( $releasedVersions as $ver ) {
			$releasedNames[] = strtolower( $ver->getVersionShortName() );
		}
		
		$previewVersions = PonyDocsProductVersion::GetPreviewVersions( $productName );
		// Just the names of our preview versions
		$previewNames = array();
		if ( $previewVersions ) {
			foreach ( $previewVersions as $ver ) {
				$previewNames[] = strtolower( $ver->getVersionShortName() );
			}
		}
		
		$latestVersion = PonyDocsProductVersion::GetLatestReleasedVersion( $productName );
	
		foreach( $this->versions as $version ) {
			$versionName = strtolower($version->getVersionShortName());
			
			// Is this version released, preview, or unreleased?
			if ( in_array( $versionName, $releasedNames ) ) {
				$versionClasses['released'] = TRUE;
			} elseif ( in_array( $versionName, $previewNames ) ) {
				$versionClasses['preview'] = TRUE;
			} else {
				$versionClasses['unreleased'] = TRUE;
			}

			// Is this version older or later or equal to the current version?
			if ( $latestVersion ) {
				if ( PonyDocs_ProductVersionCmp( $version, $latestVersion ) < 0 ) {
					$versionClasses['older'] = TRUE;
				} elseif ( PonyDocs_ProductVersionCmp( $version, $latestVersion ) > 0 ) {
					$versionClasses['newer'] = TRUE;
				} else {
					$versionClasses['latest'] = TRUE;
				}
			}
		}

		return array_keys($versionClasses);
	}

	/**
	 * Get 'base' topic name, meaning with version stripped off.
	 * Only works if a Documentation NS topic, else returns empty string.
	 *
	 * @return string
	 */
	public function getBaseTopicName() {
		if ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*):(.*):(.*)/i',
			$this->pTitle->__toString(), $match ) ) {
			return sprintf( PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':%s:%s:%s', $match[1], $match[2], $match[3] );
		}

		return '';
	}
	
	/**
	 * Get just the topic part of the title
	 * 
	 * @return string
	 */
	public function getTopicName() {
		if ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*):(.*):(.*)/i', $this->pTitle->__toString(),
			$match ) ) {
			return $match[3];
		}
	}
	
	/**
	 * Return a regex to match the topic parser function
	 * 
	 * @param string $title An optional title to search for. If not supplied, we'll search for any title, using a capture group.
	 * 
	 * @return string
	 */
	static public function getTopicRegex( $title = NULL ) {
		if ( !isset( $title ) ) {
			$title = '(.*)';
		}
		return "{{\s*#topic:\s*$title\s*}}";
	}

	/**
	 * Create a URL path (e.g. Documentation/Foo/latest/Bar/Bas) for a Topic
	 * 
	 * @param string $productName
	 * @param string $manualName
	 * @param string $topicName
	 * @param string $versionName - Optional. We'll get the selected version (which defaults to 'latest') if empty
	 * @param boolean $makeLatestUrl - Optional.
	 *                                 If TRUE (default) replace version string with 'latest' if the latest verson is passed in
	 * 
	 * @return string
	 * 
	 * TODO: We should really be passing a topic object into this and not a string
	 */
	static public function getTopicURLPath( $productName, $manualName, $topicName, $versionName = NULL, $makeLatestUrl = TRUE ) {
		global $wgArticlePath;

		if (! isset( $versionName ) ) {
			$versionName = PonyDocsProductVersion::GetSelectedVersion( $productName );
		}
		
		if ($makeLatestUrl) {
			$latestVersion = PonyDocsProductVersion::GetLatestReleasedVersion( $productName );
			if ( $latestVersion ) {
				if ( $versionName == $latestVersion->getVersionShortName() ) {
					$versionName = 'latest';
				}
			}
		}

		$base = str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME, $wgArticlePath );

		return "$base/$productName/$versionName/$manualName/$topicName";
	}
}

