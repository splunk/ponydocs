<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );

/**
 * This represents a table of contents for a manual.  A manual may have MULTIPLE table of contents.  Each
 * is bound to a minimum of one TOC page with an initial version but may be tagged for multiple versions.
 * This will basically take the version sought after and FIND in the categorylinks table the TOC Article
 * which defines the table of contents for this manual.
 *
 */
class PonyDocsTOC
{

	/**
	 * Our instance of PonyDocsManual to which this Table of Contents applies.
	 *
	 * @var PonyDocsManual
	 */
	protected $pManual;

	/**
	 * The initial PonyDocsVersion instance to which the TOC is created from.  So, for instance, userTOC3.0
	 * would initially be tagged to version 3.0 and then tagged to additional versions through the
	 * category interface.
	 *
	 * @var PonyDocsVersion
	 */
	protected $pInitialVersion;

	protected $pProduct;

	/**
	 * This is the list of versions for which this TOC is tagged for the given manual.  It will
	 * include the initial version as well.  They are mapped from version name to actual PonyDocsVersion
	 * instance.
	 *
	 * @var array 
	 */
	protected $pVersionList;
	
	/**
	 * This is the Article which represents the TOC loaded.
	 *
	 * @var Article
	 */
	protected $pTOCArticle;

	/**
	 * This is the Title of the TOC article which represents the TOC.
	 *
	 * @var string
	 * */
	protected $mTOCPageTitle;

	/**
	 * This is an array of actual TOC items (i.e. not headers) in order.  Each is a PonyDocsTOCItem instance.
	 * This allows us to get the prev/next links based on the current topic being viewed, if any.
	 *
	 * @var array
	 */
	protected $mItemList = array( );

	/**
	 * This stores the table of contents generated as well as the previous/next links.
	 * @FIXME:  Add 'start' link?
	 */
	protected $mTableOfContents = array( );
	protected $mPreviousTopic ;
	protected $mNextTopic ;

	/**
	 * This stores the index of the currently viewed section so we can just grab the mini-TOC for the top of
	 * the page directly out of the full TOC shown in the left sidebar.
	 */
	protected $mCurrentTopicIndex = -1;
	
	/**
	 * The description of the manual as it should appear on the products page (where manuals are listed)
	 * 
	 * @var string
	 */
	protected $mManualDescription;

	/**
	 * Construct with a PonyDocsManual and initial version.  Stores and performs load (should we load here?)
	 *
	 * @param PonyDocsManual $pManual Manual to find TOC for.
	 * @param PonyDocsVersion $initialVersion Initial version (find manual tagged for this version?).
	 * @param PonyDocsProduct $product The product all of this is for.
	 */
	public function __construct( PonyDocsProductManual& $pManual, PonyDocsProductVersion& $initialVersion, PonyDocsProduct& $product )
	{
		$this->pManual = $pManual;
		$this->pInitialVersion = $initialVersion;
		$this->pProduct = $product;
		$this->load( );
	}

	/**
	 * Add a version to the list of those which the TOC applies to.
	 *
	 * @param PonyDocsVersion $pVersion Version object to add to list.
	 * @return PonyDocsVersion
	 */
	public function & addVersion( PonyDocsProductVersion& $pVersion )
	{
		$this->pVersionList[$pVersion->getVersionName( )] = $pVersion;
		return $pVersion;
	}

	/**
	 * Returns the list of version objects to which this TOC applies for the current manual.
	 *
	 * @return array
	 */
	public function & getVersions( )
	{
		return $this->pVersionList;
	}

	/**
	 * Test whether this TOC applies to the supplied version NAME.
	 *
	 * @param string $versionName Name of version.
	 * @return boolean
	 */
	public function isForVersion( $versionName )
	{
		return isset( $this->pVersionList[$versionName] );
	}

	/**
	 * This actually loads the list of versions for which the TOC is tagged and stores the PonyDocsVersion objects.  
	 *
	 * @return boolean
	 */
	public function load( )
	{
		/**
		 * First define the TOC prefix, which will be something like 'Documentation:<manualShort>TOC'.  We then scan the categorylinks
		 * table for the initial version supplied as 'cl_to'.  This should only return one row, but we're going to ignore anything but
		 * the first just in case.  The resulting 'cl_sortkey' is the actual full name of the TOC page.  From this we then scan the
		 * same table for all 'cl_to' matches for the complete name and add those versions to our list.
		 */
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'categorylinks', 'cl_sortkey', array( 	
					"LOWER(cast(cl_sortkey AS CHAR)) LIKE 'documentation:" . $dbr->strencode( $this->pProduct->getShortName( )) . ":" . $dbr->strencode( strtolower( $this->pManual->getShortName( ))) . "toc%'",
					"cl_to = 'V:" . $dbr->strencode( $this->pProduct->getShortName( )) . ":" . $dbr->strencode( $this->pInitialVersion->getVersionName( )) . "'" ),
					__METHOD__ );

		if( !$res->numRows( ))
		{
			return false;
		}

		$row = $dbr->fetchObject( $res );
		$mTOCPageTitle = $row->cl_sortkey;
		$this->mTOCPageTitle = $mTOCPageTitle;
		
		$res = $dbr->select( 'categorylinks', 'cl_to', "cl_sortkey = '" . $dbr->strencode( $mTOCPageTitle ) . "'", __METHOD__ );
		while( $row = $dbr->fetchObject( $res ))
		{
			if( preg_match( '/^v:(.*):(.*)/i', $row->cl_to, $match ))
			{
				$addV = PonyDocsProductVersion::GetVersionByName( $match[1], $match[2] );
				if( $addV )
					$this->addVersion( $addV );
			}
		}

		/**
		 * Now load the contents of our TOC article itself and store internally.
		 *
		 */
		$this->pTOCArticle = new Article( Title::newFromText( $mTOCPageTitle ), 0);
		$this->pTOCArticle->getContent( );

		return true;
	}

	public function getTOCPageTitle() {
		return $this->mTOCPageTitle;
	}

	/**
	 * Parse the content of the TOC management page.  
	 * 
	 * It should be loaded and stored and this sort of breaks the design in the way 
	 * that it returns the template ready array of data which PonyDocsWiki is really 
	 * supposed to be returning, but I do not see the point or use of an intermediate 
	 * format other than to bloat the code.  
	 * 
	 * It returns an array of arrays, which can be stored as a single array or 
	 * separated using the list() = loadContnet() syntax.
	 *
	 * toc: This is the actual TOC as a list of arrays, each array having a set 
	 *      of keys available to specify the TOC level, text, href, etc.
	 * prev: Assoc array containing the 'previous' link data (text, href), or empty 
	 *       if there isn't one.
	 * next: Assoc array containing the 'next' link data or empty if there isn't one.
	 * start: Assoc array containing the data for the FIRST topic in the TOC.
	 *
	 * These can be captured in a variable when calling and individually accessed 
	 * or captured using the list() construct when calling; i.e.:
	 * list( $toc, $prev, $next, $start ) = $toc->loadContent().
	 *
	 * @FIXME:  Store results internally and then have a $reload flag as param.
	 * $content = $toc-
	 * 
	 * @return array
	 */
	public function loadContent() {
		global $wgArticlePath;
		global $wgTitle;
		global $wgScriptPath;
		global $wgPonyDocs;

		global $title;

		/**
		 * From this we have the page ID of the TOC page to use -- fetch it then 
		 * parse it so we can produce an output TOC array.
		 * 
		 * This array will contain one array per item with the following keys:
		 * - 'level': 0= Arbitary Section Name, 1= Actual topic link.
		 * - 'link': Link (wiki path) to item;  may be unset for section headers 
		 *   (or set to first section H1)?
		 * - 'text': Text to show in sidebar TOC.
		 * - 'current': 1 if this is the currently selected topic, 0 otherwise.
		 * 
		 * We also have to store the index of the current section in our loop. 
		 * 
		 * The reason for this is so that we can remove any sections which have no 
		 * defined/valid topics listed. 
		 * 
		 * This will also assist in our prev/next links which are stored in special 
		 * indices.
		 */

		// Our title is our url.
		// We should check to see if latest is our version.
		// If so, we want to FORCE the URL to include /latest/ as the version instead 
		// of the version that the user is currently in.
		$tempParts = explode("/", $title);
		$latest = false;

		if(isset($tempParts[1]) && !strcmp($tempParts[1], "latest")) {
			$latest = true;
		}

		$selectedProduct = $this->pProduct->getShortName();
		$selectedVersion = $this->pInitialVersion->getVersionName();
		$selectedManual = $this->pManual->getShortName();

		// Okay, let's determine if the VERSION that the user is in is latest, 
		// if so, we should set latest to true.
		if (PonyDocsProductVersion::GetLatestReleasedVersion($selectedProduct) != null) {
		  	if ($selectedVersion == PonyDocsProductVersion::GetLatestReleasedVersion($selectedProduct)->getVersionName()) {
				$latest = true;
			}
		}

		$cache = PonyDocsCache::getInstance();
		$key = "TOCCACHE-" . $selectedProduct . "-" . $selectedManual . "-" . $selectedVersion;
		$toc = $cache->get($key);
		if ($toc === null) {
			// Cache did not exist, let's load our content is build up our cache 
			// entry.
			$toc = array( );
			$idx = 0; 				// The current index of the element in $toc we will work on
			$section = -1;
			$lines = explode( "\n", $this->pTOCArticle->mContent );
			foreach ($lines as $line) {
				/**
				* Indicates an arbitrary section header if it does not begin with a bullet point.  This is level 0 in our TOC and is
				* not a link of any type (?).
				*/
				if ((!isset($line[0])) ||  $line[0] != '*') {
					/**
					* See if we are CLOSING a section (i.e. $section != -1).  If so, check 'subs' and ensure its >0, otherwise we
					* need to remove the section from the list.
					*/
					if (($section != -1 ) && !$toc[$section]['subs']) {
							unset( $toc[$section] );
					}
					if (isset($line[0]) && ctype_alnum( $line[0] )) {
						$toc[$idx] = array(
							'level' => 0,
							'subs' => 0,
							'link' => '',
							'text' => $line,
							'current' => false
						);
						$section = $idx;
					}
				}
				/**
				 * This is a bullet point and thus an actual topic which can be linked to in MediaWiki. 
				 * {{#topic:H1 Of Topic Page}}
				 */
				else {
					if (-1 == $section) {
						continue;
					}
					if (!preg_match('/{{#topic:(.*)}}/i', $line, $matches)) {
						continue ;
					}

					$baseTopic = $matches[1];

					$title_suffix = preg_replace('/([^' . str_replace(' ', '', Title::legalChars()) . '])/', '', $baseTopic);
					$title = PONYDOCS_DOCUMENTATION_PREFIX . "$selectedProduct:$selectedManual:$title_suffix";
					$newTitle = PonyDocsTopic::GetTopicNameFromBaseAndVersion($title, $selectedProduct);

					/**
					 * Hide topics which have no content (i.e. have not been created 
					 * yet) from the user viewing.  
					 * 
					 * Authors must go to the TOC page in order to view and edit these.
					 * 
					 * The only way to do this (the cleanest/quickest) is to create
					 * a Title object then see if its article ID is 0.  
					 * 
					 * @tbd:  Fix so that the section name is hidden if no topics are visible?
					 */
					$t = Title::newFromText($newTitle);
					if (!$t || !$t->getArticleID()) {
						continue;
					}

					/**
					 * Obtain H1 content from the article -- WE NEED TO CACHE THIS!
					 */
					$h1 = PonyDocsTopic::FindH1ForTitle($newTitle);
					if ($h1 === false) {
						$h1 = $newTitle;
					}

					$href = str_replace('$1', 
						PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . "/$selectedProduct/$selectedVersion/$selectedManual/$title_suffix", 
						$wgArticlePath);

					$toc[$idx] = array(
						'level' => 1,
						'page_id' => $t->getArticleID(),
						'link' => $href,
						'toctitle' => $baseTopic,
						'text' => $h1,
						'section' => $toc[$section]['text'],
						'title' => $newTitle,
						'class' => 'toclevel-1',
					);
					$toc[$section]['subs']++;
				}
				$idx++;
			}
			if (!$toc[$section]['subs']) {
				unset( $toc[$section] );
			}
			// Okay, let's store in our cache.
			$cache->put($key, $toc, time() + 3600);
		}

		$currentIndex = -1;
		$start = array( );
		// Go through and determine start, prev, next and current elements.

		foreach ($toc as $idx => &$entry) {
			// Not using $entry.  Only interested in $idx.
			// This allows us to process tocs with removed key indexes.
			if ($toc[$idx]['level'] == 1) {
				if (empty($start)) {
					$start = $toc[$idx];
				}
				// Determine current
				$toc[$idx]['current'] = strcmp($wgTitle->mPrefixedText, $toc[$idx]['title']) ? false : true;
				if ($toc[$idx]['current']) {
					$currentIndex = $idx;
				}
				// Now rewrite link with latest, if we are in latest
				if ($latest) {
					$safeVersion = preg_quote($selectedVersion, '#');
					// Lets be specific and replace the version and not some other part of the URI that might match...
					$toc[$idx]['link'] = preg_replace(
						'#^/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME .
							'/([' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)/' . "$safeVersion#",
						'/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/$1/latest',
						$toc[$idx]['link'],
						1);
				}
			}
		}

		/**
		 * Figure out previous and next links.
		 * 
		 * Previous should point to previous topic regardless of section, so our 
		 * best bet is to skip any 'level=0'.  
		 * 
		 * Next works the same way.
		 */
		$prev = $next = $idx = -1;

		if ($currentIndex >= 0) {
			$idx = $currentIndex;
			while ($idx >= 0) {
				--$idx;
				if (isset($toc[$idx]) &&  $toc[$idx]['level'] == 1) {
					$prev = $idx;
					break;
				} 
			}

			$idx = $currentIndex;
			// Array is sparse, so sizeof() truncates the end. Use max key instead.
			while ($idx <= max(array_keys($toc))) {
				++$idx;
				if (isset($toc[$idx]) &&  $toc[$idx]['level'] == 1) {
					$next = $idx;
					break;
				}
			}

			if ($prev != -1) {
				$prev = array(
					'link' => $toc[$prev]['link'],
					'text' => $toc[$prev]['text']
				);
			}
			if ($next != -1) {
				$next = array(
					'link' => $toc[$next]['link'],
					'text' => $toc[$next]['text']
				);
			}
		}

		/**
		 * You should typically capture this by doing:
		 * list( $toc, $prev, $next, $start ) = $ponydocstoc->loadContent( );
		 *
		 * @FIXME:  Previous and next links change based on the page you are on, so we cannot CACHE those!
		 *
		 * $obj = new stdClass();
		 * $obj->toc = $toc;
		 * $obj->prev = $prev;
		 * $obj->next = $next;
		 * $obj->start = $start;
		 * $cache->addKey($tocKey, $obj);
		 */
		
		// Last but not least, get the manual description if there is one.
		if (preg_match('/{{#manualDescription:([^}]*)}}/', $this->pTOCArticle->mContent, $matches)) {
			$this->mManualDescription = $matches[1];
		}

		return array( $toc, $prev, $next, $start );
	}

	public function getManualDescription()
	{
		return isset($this->mManualDescription) ? $this->mManualDescription : NULL;
	}

	/**
	 * Normalize a section name by converting its text to an anchor.  This should strip strange characters (which?) and convert
	 * spaces to underscores, which is slightly different than normalizing a topic name.
	 *
	 * @static
	 * @param string $secText Section content (H2, H3, etc.)
	 * @return string
	 */
	static public function normalizeSection( $secText )
	{
		$secText = str_replace( ' ', '_', preg_replace( '/^\s*|\s*$/', '', $secText ));
		return $secText;
	}

	static public function clearTOCCache($manual, $version, $product) {
		error_log("INFO [PonyDocsTOC::clearTOCCache] Deleting cache entry of TOC for product " . $product->getShortName() . " manual " . $manual->getShortName() . ' and version ' . $version->getVersionName());
		$key = "TOCCACHE-" . $product->getShortName() . "-" . $manual->getShortName() . "-" . $version->getVersionName();
		$cache = PonyDocsCache::getInstance();
		$cache->remove($key);
	}
	
	/**
	 * Create a URL path (e.g. Documentation/Foo/BARTOC1.0) for a TOC
	 * 
	 * @param string $productName
	 * @param string $TOCName
	 * 
	 * @return string
	 */
	static public function getTOCURLPath( $productName, $TOCName ) {
		$base = str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME, $wgArticlePath );
		return "$base/$productName/$TOCname";
	}
}

/**
 * End of file.
 */
?>