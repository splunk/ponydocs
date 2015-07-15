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

		$this->versions = array();
		
		while ( $row = $dbr->fetchObject( $res ) ) {
			if ( preg_match( '/^v:(.*):(.*)/i', $row->cl_to, $match ) ) {
				$v = PonyDocsProductVersion::GetVersionByName( $match[1], $match[2] );
				if ( $v ) {
					$this->versions[] = $v;
				}
			}
		}

		// Sort by the order on the versions admin page
		usort( $this->versions, "PonyDocs_ProductVersionCmp" );		

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

		if ( preg_match('/__NOTOC__/', $this->pArticle->getContent() ) ) {
			return $sections;
		}

		$matches = $this->parseSections();
		$h2 = FALSE;
		$headReference = array();
		$headCount = 0;
		foreach ( $matches as $match ) {
			$level = strlen( $match[1] );
			if ( !isset($headReference[$match[2]]) ) {
				$headReference[$match[2]] = 1;
			} else {
				$headReference[$match[2]] ++;
			}

			// We don't want to include any H3s that don't have an H2 parent
			if ( $level == 2 || ( $level == 3 && $h2 ) ) {
				if ( $level == 2 ) {
					$h2 = TRUE;
				}
				$headCount = $headReference[$match[2]];
				if ( $headCount > 1 ) {
					$link = '#' . Sanitizer::escapeId(PonyDocsTOC::normalizeSection($match[2]), 'noninitial') . '_' . $headCount;
				} else {
					$link = '#' . Sanitizer::escapeId(PonyDocsTOC::normalizeSection($match[2]), 'noninitial');
				}

				$sections[] = array(
					'level' => $level,
					'link' => $link,
					'text' => $match[2],
					'class' => 'toclevel-' . round($level - 1, 0)
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
	 * Parse out all the headings in the form:
	 * 	= Headings =, == Heading ==, etc.
	 * One set is H1, two is H2, and so forth.
	 * The results array has:
	 * - 0 = Complete match with equal signs.
	 * - 1 = Right-hand side set of equal signs.
	 * - 2 = The heading text inside the equal signs.
	 * - 3 = Left hand side set of equal signs.
	 *
	 * @return array
	 */
	public function parseSections() {
		$content = str_replace("<nowiki>", "", $this->pArticle->mContent);
		$content = str_replace("</nowiki>", "", $content);
		$content = strip_tags($content, '<h1><h2><h3><h4><h5>');	
		$headings = array();
		# A heading is a line that only contains an opening set of '=', some text, and a closing set of '='
		# There can be an arbitrary amount of whitespace before and after each component of the heading
		# To ensure there is nothing else on the line, we start and stop the regex with \n
		# However, \s also matches \n, so we need to use [^\S\n] to match any possible whitespace
		# If we just use \s, headings that immediately follow a heading are suppressed.
		$pattern = "/[^\S\n]*(=+)([^=]*)(=+)[^\S\n]*\n/";
		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as &$match ) {
				if (strlen($match[1]) == strlen($match[3])) {
					$match[2] = trim( $match[2] );
					$headings[] = $match;
				}
			}
		}
		return $headings;
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
			$releasedNames[] = strtolower( $ver->getVersionName() );
		}
		
		$previewVersions = PonyDocsProductVersion::GetPreviewVersions( $productName );
		// Just the names of our preview versions
		$previewNames = array();
		foreach ( $previewVersions as $ver ) {
			$previewNames[] = strtolower( $ver->getVersionName() );
		}
		
		$latestVersion = PonyDocsProductVersion::GetLatestReleasedVersion( $productName );
	
		foreach( $this->versions as $version ) {
			$versionName = strtolower($version->getVersionName());
			
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
		if ( preg_match( '/' . PONYDOCS_DOCUMENTATION_PREFIX . '(.*):(.*):(.*):(.*)/i', $this->pTitle->__toString(), $match ) ) {
			return sprintf( PONYDOCS_DOCUMENTATION_PREFIX . '%s:%s:%s', $match[1], $match[2], $match[3] );
		}

		return '';
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
	 * 
	 * @return string
	 * 
	 * TODO: We should really be passing a topic object into this and not a string
	 */
	static public function getTopicURLPath( $productName, $manualName, $topicName, $versionName = NULL ) {
		global $wgArticlePath;

		if (! isset( $versionName ) ) {
			$versionName = PonyDocsProductVersion::GetSelectedVersion( $productName );
		}
		
		$latestVersion = PonyDocsProductVersion::GetLatestReleasedVersion( $productName );
		if ( $latestVersion ) {
			if ( $versionName == $latestVersion->getVersionName() ) {
				$versionName = 'latest';
			}
		}
		
		$base = str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME, $wgArticlePath );

		return "$base/$productName/$versionName/$manualName/$topicName";
	}
}
