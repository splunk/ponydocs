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
		//$this->pArticle->loadContent( );
		//echo '<pre>' . $article->getContent( ) . '</pre>';
		$this->pTitle = $article->getTitle();
		if ( preg_match( '/' . PONYDOCS_DOCUMENTATION_PREFIX . '.*:.*:.*:.*/i', $this->pTitle->__toString() ) )
			$this->mIsDocumentationTopic = true;
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
	public function getProductVersions( $reload = false ) {
		if( sizeof( $this->versions ) && !$reload ) {
			return $this->versions;
		}
		
		$dbr = wfGetDB( DB_SLAVE );
		$revision = $this->pArticle->mRevision;

		//$res = $dbr->select( 'categorylinks', 'cl_to', "cl_from = '" . $revision->mPage . "'", __METHOD__ );
		$res = $dbr->select(
			'categorylinks', 'cl_to', "cl_sortkey = '" . $dbr->strencode(  $this->pTitle->__toString( )) . "'", __METHOD__ );

		$tempVersions = array();

		while ( $row = $dbr->fetchObject( $res ) ) {
			if ( preg_match( '/^v:(.*):(.*)/i', $row->cl_to, $match ) ) {
				$v = PonyDocsProductVersion::GetVersionByName( $match[1], $match[2] );
				if ( $v ) {
					$tempVersions[] = $v;
				}
			}
		}
		// Sort by Version, by doing a natural sort. Also remove any duplicates.
		/// FIXME - what is this really doing? tempVersions index is int per above code!
		$sortArray = array();
		foreach ( $tempVersions as $index => $version ) {
			if ( !in_array($version->getVersionName(), $sortArray) ) {
				$sortArray[(string)$index] = $version->getVersionName();
			}
		}
		natsort( $sortArray );
		foreach ( $sortArray as $targetIndex => $verName ) {
			$this->versions[] = $tempVersions[$targetIndex];
		}

		return $this->versions;
	}

	/**
	 * Given a 'base' topic name (Documentation:User:HowToFoo), find the proper topic name based on selected version.
	 * If 2.1 is selected and we have a HowToFoo2.0 tagged for 2.1, return HowToFoo2.0.
	 *
	 * @static
	 * @param string $baseTopic
	 * @param string $product
	 */
	static public function GetTopicNameFromBaseAndVersion( $baseTopic, $product ) {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select( 'categorylinks', 'cl_sortkey', array(
			"LOWER(cast(cl_sortkey AS CHAR)) LIKE '" . $dbr->strencode( strtolower( $baseTopic )) . ":%'",
			"cl_to = 'V:" . $product . ':' . PonyDocsProductVersion::GetSelectedVersion( $product ) . "'" ), __METHOD__ );

		if ( !$res->numRows() ) {
			return false;
		}

		$row = $dbr->fetchObject( $res );

		return $row->cl_sortkey;
	}

	/**
	 * CHANGE THIS TO CACHE THESE IN MEMCACHE OR WHATEVER.
	 *
	 * This takes a title (text form) and extracts the H1 content for the title and returns it.
	 * This is used in display (heading and TOC).
	 * 
	 * @static
	 * @param string $title The text form of the title to get the H1 content for.
	 * @return string The resulting H1 content; or boolean false if title not found.
	 */
	static public function FindH1ForTitle( $title ) {
		$article = new Article( Title::newFromText( $title ), 0 );
		$content = $article->loadContent();

		if ( !preg_match( '/^\s*=(.*)=/D', $article->getContent(), $matches ) ) {
			return false;
		}
		return $matches[1];
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
		
		if ( preg_match( '/__NOTOC__/', $this->pArticle->getContent() ) ) {
			return $sections;
		}
		
		$matches = $this->parseSections();
		$h2 = FALSE;
		foreach ( $matches as $match ) {
			$level = strlen( $match[1] );		
			// We don't want to include any H3s that don't have an H2 parent
			if ( $level == 2 || ( $level == 3 && $h2 ) )  {
				if ( $level == 2 ) {
					$h2 = TRUE;
				}
				$sections[] = array(
					'level' => $level,
					'link' => '#' . Sanitizer::escapeId( PonyDocsTOC::normalizeSection( $match[2] ) ),
					'text' => $match[2],
					'class' => 'toclevel-' . round( $level - 1, 0 )
				);
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
		if ( preg_match_all( $re, $this->pArticle->mContent, $matches, PREG_SET_ORDER ) ) {
			return $matches;
		}
		return array();
	}

	/**
	 * parses out all the headers in the form:
	 * 	= Header =
	 * It requires valid MediaWiki markup, so it must have the same number of '=' on each side.
	 * One set is H1, two is H2, and so forth.  The
	 * results array has:
	 * - 0 = Complete match with equal signs.
	 * - 1 = The header text inside the equal signs.
	 * - 2 = This will contain the left hand side set of equal signs, so strlen() this to get the header level.
	 *
	 * @return array
	 */
	public function parseSections()	{
		$content = str_replace("<nowiki>", "", $this->pArticle->mContent);
		$content = str_replace("</nowiki>", "", $content);

		// We don't need such a long regex.  Simply encapsulating everything in header element.
		$re = "/(=+)([^=]*)(=+)\n/";
		if ( preg_match_all( $re, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as &$match ) {
				$match[2] = trim(str_replace( "=", "", $match[2] ) );
			}
			return $matches;
		}
		return array();
	}

	/**
	 * This function determines the version category this applies to.  For instance, we want a slight skinning change or notice
	 * in the display when viewing a topic (in Documentation namespace only) for each of the following possible conditions:
	 *
	 * Applies to latest released version (current)
	 * Applies to a preview version (preview)
	 * Applies to a previously released version (older) 
	 * None of the above (unknown)
	 * 
	 * @return integer
	 */
	public function getVersionClass() {
		if ( !preg_match(
			'/' . PONYDOCS_DOCUMENTATION_PREFIX . '(.*):(.*):(.*):(.*)/i', $this->pTitle->__toString( ), $matches ) ) {
			// This is not a documentation title.
			return "unknown";
		}
		$productName = $matches[1];
		/**
		 * Test if topic applies to latest released version (current).
		 */
		$releasedVersions = PonyDocsProductVersion::GetReleasedVersions( $productName );
		$releasedNames = array(); // Just the names of our released versions
		foreach ( $releasedVersions as $ver ) {
			$releasedNames[] = strtolower( $ver->getVersionName() );
		}
		$previewVersions = PonyDocsProductVersion::GetPreviewVersions( $productName );
		$previewNames = array(); // Just the names of our preview versions
		foreach ( $previewVersions as $ver ) {
			$previewNames[] = strtolower( $ver->getVersionName() );
		}
		$isPreview = false;
		$isOlder = false;
		foreach( $this->versions as $v ) {
			$ver = strtolower($v->getVersionName());
			if ( PonyDocsProductVersion::GetLatestReleasedVersion( $productName ) != null
				&& !strcasecmp( $ver, PonyDocsProductVersion::GetLatestReleasedVersion($productName)->getVersionName() ) ) {
				// Return right away, as current is our #1 class
				return "current";
			}
			if ( in_array( $ver, $releasedNames ) ) {
				$isOlder = true;
			}
			if ( in_array( $ver, $previewNames ) ) {
				$isPreview = true;
			}
		}
		if ( $isPreview ) {
			return "preview";
		}
		if ( $isOlder ) {
			return "older";
		}
		// Default return
		return "unknown";
	}

	/**
	 * Get 'base' topic name, meaning with version stripped off.
	 * Only works if a Documentation NS topic, else returns empty string.
	 *
	 * @return string
	 */
	public function getBaseTopicName() {
		if ( preg_match( '/' . PONYDOCS_DOCUMENTATION_PREFIX . '(.*):(.*):(.*):(.*)/i', $this->pTitle->__toString(), $match ) ) {
			return sprintf( PONYDOCS_DOCUMENTATION_PREFIX . '%s:%s:%s', $match[1], $match[2], $match[3] );
		}

		return '';
	}
};