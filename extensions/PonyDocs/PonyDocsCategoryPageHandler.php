<?php
if( !defined( 'MEDIAWIKI' )) {
	die( "PonyDocs MediaWiki Extension" );
}

require_once( "$IP/includes/page/CategoryPage.php" );

class PonyDocsCategoryPageHandler extends CategoryViewer {
	protected $articleCount = 0;

	/**
	 * Our hook which is processed whenever a Category Page is viewed.  When 
	 * this happens, we hijack the normal mediawiki flow and create an instance 
	 * of our CategoryViewer(PonyDocsCategoryPageHandler) which has a special 
	 * addPage method which adds the H1 of the article content, instead of the 
	 * title itself.
	 *
	 * @param CategoryArticle $categoryArticle a reference to the 
	 * CategoryArticle which fired off the event.
	 * @return boolean We return false to make mediawiki stop processing the 
	 * normal flow.
	 */
	static function onCategoryPageView(&$categoryArticle) {
		global $wgOut, $wgRequest;
		$from = $wgRequest->getVal('from');
		$until = $wgRequest->getVal('until');
		$cacheKey = "category-" . $categoryArticle->getTitle() . "-$from-$until";
		$res = null;
		$cache = PonyDocsCache::getInstance();
		// See if this exists in our cache
		$res = $cache->get($cacheKey);
		if($res == null) {
			// Either cache is disabled, or cached entry does not exist
			$categoryViewer = new PonyDocsCategoryPageHandler($categoryArticle->getTitle(), $from, $until);
			$res = $categoryViewer->getHTML();
			// Store in our cache
			$cache->put($cacheKey, $res, CATEGORY_CACHE_TTL);
		}
		$wgOut->addHTML($res);
		return false; // We don't want to continue processing the "normal" mediawiki path, so return false here.
	}

	/**
	 * Our special overridden addPage.  Adds the H1 of the article content, 
	 * instead of the title itself.
	 *
	 * @param $title Title the Title obj which represents the article.
	 * @param $sortkey string What key are we sorted on.
	 * @param $pageLength integer Ignored in our implementation.
	 *
	 */
	function addPage($title, $sortkey, $pageLength, $isRedirect = false) {
		global $wgContLang;
		$this->articleCount++;
		$textForm = $title->getText();
		if($isRedirect) {
			// In rare chances where the title will conflict with another, make 
			// all article elements sub-arrays
			if(!is_array($this->articles[strtoupper($textForm[0])])) {
				$this->articles[strtoupper($textForm[0])] = array();
			}
			$this->articles[strtoupper($textForm[0])] = '<span class="redirect-in-category">' . $this->getSkin()->makeKnownLinkObj( $title ) . '</span>';
		}
		else {
			// Not a redirect, a regular article.  Let's grab the h1, if 
			// available.
			// Make sure we run the ArticleFromTitle hook...
			$article = null;
			wfRunHooks('ArticleFromTitle', array(&$title, &$article));
			if(!$article) {
				$article = new Article($title);
			}
			$article->LoadContent();
			preg_match('/^\s*=(.*)=.*\n?/', $article->getContent(), $matches);
			if(isset($matches[1])) {
				// We found a header in the content, use that as our h1
				$h1 = trim($matches[1]);
				if(strlen($h1) == 0) {
					$h1 = trim($textForm);
				}
			}
			else {
				// Could not find a header, use the text form of our title
				$h1 = trim($textForm); 
			}
			// Let's get the namespace, if any.
			$nsText = $title->getNsText();
			if($nsText) {
				// Not in default namespace, add in parenthesis.
				$h1 .= " ($nsText)";
			}

			// In rare chances where the title will conflict with another, make 
			// all article elements sub-arrays
			if(!(isset($this->articles[strtoupper($h1[0])]) && is_array($this->articles[strtoupper($h1[0])]))) {
				$this->articles[strtoupper($h1[0])] = array();
			}
			$this->articles[strtoupper($h1[0])][] = $this->getSkin()->makeKnownLinkObj($title, htmlentities($h1));
		}
	}

	/**
	 * Format a list of articles chunked by letter in a one-column
	 * list, ordered vertically.
	 *
	 * @param array $articles Our collection of articles...
	 * @param array $articles_start_char Totally ignored.
	 * @return string
	 * @private
	 */
	static function columnList( $articles, $articles_start_char ) {
		$result = ksort($articles, SORT_STRING);
		$r = '';

		$prev_start_char = 'none';
		foreach($articles as $key => $subArticles) {
			$r .= "<h3>" . htmlspecialchars($key) . "</h3><ul>";
			foreach($subArticles as $article) {
				$r .= "<li>{$article}</li>";
			}
			$r .= "</ul>";
		}
		return $r;
	}

	/**
	 * Format a list when a list is short.  Basically performs 
	 * same function as our columnList.
	 *
	 * @see columnList
	 *
	 */
	static function shortList($articles, $articles_start_char) {
		return $this->columnList($articles, $articles_start_char);
	}

	/**
	 * Format a list of categories chunked by letter.
	 * This is overridden to provide a one column layout much like our column 
	 * list method.
	 *
	 * @param array $articles
	 * @param array $articles_start_char
	 * @return string
	 * @private
	 */
	function categoryList( $articles, $articles_start_char ) {
		// divide list into three equal chunks
		$prev_start_char = 'none';
		for ($index = 0 ; $index < count($articles); $index++ ) {
			// check for change of starting letter or begining of chunk
			if ($articles_start_char[$index] != $articles_start_char[$index - 1]) {
				if($prev_start_char != 'none') {
					$r .= "</ul>\n";
				}
				$r .= "<h3>" . htmlspecialchars( $articles_start_char[$index] ) . "</h3>\n<ul>";
				$prev_start_char = $articles_start_char[$index];
			}
			$r .= "<li>{$articles[$index]}</li>";
		}
		return $r;
	}

	/**
	 * Overridden getPagesSection which the only change is using our internal 
	 * article counter to show the total articles returned.
	 */
	function getPagesSection() {
		$ti = htmlspecialchars( $this->title->getText() );
		# Don't show articles section if there are none.
		$r = '';
		$c = $this->articleCount;
		if( $c > 0 ) {
			$r = "<div id=\"mw-pages\">\n";
			$r .= '<h2>' . wfMsg( 'category_header', $ti ) . "</h2>\n";
			$r .= wfMsgExt( 'Category-article-count', array( 'parse' ), $c, '{{NUMBEROFARTICLES}}' );
			$r .= $this->formatList( $this->articles, $this->articles_start_char );
			$r .= "\n</div>";
		}
		return $r;
	}

	/**
	 * Our overridden getSubcategorySection.
	 * Will call categoryList.
	 */
	function getSubcategorySection() {
		# Don't show subcategories section if there are none.
		$r = '';
		$c = count( $this->children );
		if( $c > 0 ) {
			# Showing subcategories
			$r .= "<div id=\"mw-subcategories\">\n";
			$r .= '<h2>' . wfMsg( 'subcategories' ) . "</h2>\n";
			$r .= wfMsgExt( 'subcategorycount', array( 'parse' ), $c );
			$r .= $this->categoryList( $this->children, $this->children_start_char );
			$r .= "\n</div>";
		}
		return $r;
	}
}

?>