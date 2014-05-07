<?php

require_once('PonyDocsArticleFactory.php');

/**
 * Engine to perform inheritance and branch functions for PonyDocs Documentation System.
 * Never instantiated, just a container for static methods.
 */
class PonyDocsBranchInheritEngine {

	private function __construct() {
		// It's a static class.
	}

	/**
	 * Branches a topic from a source title to a target title.
	 *
	 * @param $topicTitle string The name of the internal topic.
	 * @param $version PonyDocsVersion The target Version
	 * @param $tocSection The TOC section this title resides in.
	 * @param $tocTitle The toc title that references this topic.
	 * @param $deleteExisting boolean Should we purge any existing conflicts?
	 * @param $skipTOC boolean Should we skip adding to the TOC (for performance reasons)
	 * @returns boolean
	 */
	static function branchTopic(
		$topicTitle, $version, $tocSection, $tocTitle, $deleteExisting = false, $split = true, $skipTOC = false ) {
		// Clear any hooks so no weirdness gets called after we create the 
		// branch
		$wgHooks['ArticleSave'] = array();
		if ( !preg_match( '/^' . PONYDOCS_DOCUMENTATION_PREFIX . '([^:]*):([^:]*):(.*):([^:]*)$/', $topicTitle, $match ) ) {
			throw new Exception( "Invalid Title to Branch From" );
		}

		$productName = $match[1];
		$manualName = $match[2];
		$title = $match[3];

		// Get the PonyDocsProduct and PonyDocsProductManual
		$product = PonyDocsProduct::GetProductByShortName( $productName );
		$manual = PonyDocsProductManual::GetManualByShortName( $productName, $manualName );

		// Get conflicts.
		$conflicts = self::getConflicts( $product, $topicTitle, $version );
		if ( !empty( $conflicts ) ) {
			if ( $deleteExisting && !$split ) {
				// We want to purge each conflicting title completely.
				foreach ( $conflicts as $conflict ) {
					$article = new Article( Title::newFromText( $conflict ) );
					if ( !$article->exists() ) {
						// Article doesn't exist. Should never occur, but if it doesn't, no big deal since it was a conflict.
						continue;
					}
					if ( $conflict == $topicTitle ) {
						// Then the conflict is same as source material, do nothing.
						continue;
					}
					else {
						// Do actual delete.
						$article->doDelete( "Requested purge of conficting article when branching topic " . $topicTitle
							. " with version: " . $version->getVersionName(), false );
					}
				}
			}
			elseif ( !$split ) {
				// Ruh oh, there's conflicts and we didn't want to purge or split. Cancel out.
				throw new Exception(
					"When calling branchTitle, there were conflicts and purge was not requested and we're not splitting." );
			}
		}

		// Load existing article to branch from
		$existingArticle = PonyDocsArticleFactory::getArticleByTitle( $topicTitle );
		if ( !$existingArticle->exists() ) {
			// No such title exists in the system
			throw new Exception( "Invalid Title to Branch From. Target Article does not exist:" . $topicTitle );
		}
		$title = PONYDOCS_DOCUMENTATION_PREFIX . $product->getShortName() . ':' . $manual->getShortName() . ':' . $title . ':'
			. $version->getVersionName();

		$newArticle = PonyDocsArticleFactory::getArticleByTitle( $title );
		if ( $newArticle->exists() ) {
			throw new Exception( "Article already exists:" . $title );
		}
		
		// Copy content
		$existingContent = $existingArticle->getContent();
		$newContent = $existingContent;
		// Build the versions which will go into the new array.
		$newVersions = array();
		// Text representation of the versions
		$newVersions[] = $version->getVersionName();
		if ( $split ) {
			// We need to get all versions from PonyDocsVersion
			$rawVersions = PonyDocsProductVersion::GetVersions( $productName );
			$existingVersions = array();
			foreach ( $rawVersions as $rawVersion ) {
				$existingVersions[] = $rawVersion->getVersionName();
			}
			// $existingVersions is now an array of version names in incremental order
			$versionIndex = array_search( $version->getVersionName(), $existingVersions );
			// versionIndex is the index where our target version is
			// we will use this to determine what versions need to be brought over.
			preg_match_all( "/\[\[Category:V:([^\]]*):([^\]]*)\]\]/", $existingContent, $matches );
			foreach ( $matches[2] as $match ) {
				$index = array_search( $match, $existingVersions );
				if ( $index > $versionIndex ) {
					$newVersions[] = $match;
				}
			}
		}
		
		// $newVersions contains all the versions that need to be pulled from the existing Content and put into the new content.
		// So let's now remove it form the original content
		foreach ( $newVersions as $tempVersion ) {
			$existingContent = preg_replace(
				"/\[\[Category:V:" . $productName . ":" . $tempVersion . "\]\]/", "", $existingContent );
		}
		
		// Now let's do the edit on the original content.
		// Set version and manual
		$existingArticle->doEdit(
			$existingContent, "Removed versions from existing article when branching Topic " . $topicTitle, EDIT_UPDATE );
		// Clear categories tags from new article content
		$newContent = preg_replace( "/\[\[Category:V:([^\]]*)]]/", "", $newContent );
		// add new category tags to new content
		foreach ( $newVersions as $version ) {
			$newContent .= "[[Category:V:" . $productName . ":" . $version . "]]";
		}
		$newContent .= "\n";
		// doEdit on new article
		$newArticle->doEdit( $newContent, "Created new topic from branched topic " . $topicTitle, EDIT_NEW );

		if ( !$skipTOC ) {
			self::addToTOC( $product, $manual, $version, $tocSection, $tocTitle );
		}
		return $title;
	}

	/**
	 * Have an existing Topic "inherit" a new version by applying a category 
	 * version tag to it.
	 *
	 * @param $topicTitle string The internal mediawiki title of the article.
	 * @param $version PonyDocsVersion The target Version
	 * @param $tocSection The TOC section this title resides in.
	 * @param $tocTitle The toc title that references this topic.
	 * @param $deleteExisting boolean Should we purge any existing conflicts?
	 * @param $skipTOC boolean Should we skip adding to the TOC (for performance 
	 * 							reasons)
	 * @returns boolean
	 */
	static function inheritTopic( $topicTitle, $version, $tocSection, $tocTitle, $deleteExisting = false, $skipTOC = false ) {
		global $wgTitle;
		// Clear any hooks so no weirdness gets called after we save the inherit
		$wgHooks['ArticleSave'] = array();
		if ( !preg_match('/^' . PONYDOCS_DOCUMENTATION_PREFIX . '([^:]*):([^:]*):(.*):([^:]*)$/', $topicTitle, $match ) ) {
			throw new Exception( "Invalid Title to Inherit From: " . $topicTitle );
		}

		$productName = $match[1];
		$manual = $match[2];
		$title = $match[3];

		// Get PonyDocsProduct
		$product = PonyDocsProduct::GetProductByShortName( $productName );

		// Get conflicts.
		$conflicts = self::getConflicts( $product, $topicTitle, $version );
		if( !empty( $conflicts ) ) {
			if ( !$deleteExisting ) {
				throw new Exception( "When calling inheritTitle, there were conflicts and deleteExisting was false." );
			}
			// We want to purge each conflicting title completely.
			foreach ( $conflicts as $conflict ) {
				$article = new Article( Title::newFromText( $conflict ) );
				if (!$article->exists() ) {
					// No big deal. A conflict no longer exists? Continue.
					continue;
				}
				if ( $conflict == $topicTitle ) {
					// Conflict was same as source material. Do nothing with it.
					continue;
				}
				else {
					$article->doDelete( "Requested purge of conficting article when inheriting topic " . $topicTitle
						. " with version: " . $version->getVersionName(), false );
				}
			}
		}
		$title = Title::newFromText( $topicTitle ); 
		$wgTitle = $title;
		$existingArticle = new Article( $title );
		if ( !$existingArticle->exists() ) {
			// No such title exists in the system
			throw new Exception( "Invalid Title to Inherit From: " . $topicTitle );
		}

		// Okay, source article exists.
		// Add the appropriate version cateogry.
		// Check for existing category.
		$content = $existingArticle->getContent();
		if ( !preg_match(
			"/\[\[Category:V:" . preg_quote($productName . ":" . $version->getVersionName()) . "\]\]/", $content ) ) {
			$content .= "[[Category:V:" . $productName . ":" . $version->getVersionName() . "]]";
			// Save the article as an edit
			$existingArticle->doEdit(
				$content,
				"Inherited topic " . $topicTitle . " with version: " . $productName . ":" . $version->getVersionName(),
				EDIT_UPDATE );
		}
		
		// Okay, update our toc.
		if ( !$skipTOC ) {
			$manual = PonyDocsProductManual::GetManualByShortName( $productName, $manual );
			self::addToTOC( $product, $manual, $version, $tocSection, $tocTitle );
		}
		return $title;
	}

	/**
	 * Determines if TOC exists for a manual and version
	 *
	 * @param $manual PonyDocsManual The manual to check with
	 * @param $version PonyDocsVersion The version to check with
	 * @returns boolean
	 */
	static public function TOCExists( $product, $manual, $version ) {
		$dbr = wfGetDB( DB_SLAVE );
		$query = "SELECT cl_sortkey FROM categorylinks WHERE cl_to = 'V:" . $dbr->strencode($product->getShortName() . ':'
			. $version->getVersionName()) . "' AND LOWER(cast(cl_sortkey AS CHAR)) LIKE 'documentation:"
			. $dbr->strencode(strtolower($product->getShortName()) . ':' . strtolower($manual->getShortName())) . "toc%'";
		$res = $dbr->query( $query, __METHOD__ );

		if ( $res->numRows() ) {
			$row = $dbr->fetchObject( $res );
			return $row->cl_sortkey;
		}
		return false;
	}

	/**
	 * Branch a TOC. Take existing TOC, create new TOC.
	 *
	 * @param $manual PonyDocsManual The manual to create a TOC for.
	 * @param $sourceVersion PonyDocsVersion The source version TOC to copy.
	 * @param $targetVersion PonyDocsVersion The target version for the new TOC.
	 * @returns boolean
	 */
	static function branchTOC( $product, $manual, $sourceVersion, $targetVersion ) {
		global $wgTitle;
		// Perform old TOC operation
		$title = self::TOCExists( $product, $manual, $sourceVersion );
		if ( $title == false ) {
			throw new Exception(
				"TOC does not exist for " . $manual->getShortName() . " with version " . $sourceVersion->getVersionName() );
		}
		$title = Title::newFromText( $title );
		$wgTitle = $title;
		$article = new Article( $title );
		if ( !$article->exists() ) {
			throw new Exception(
				"TOC does not exist for " . $manual->getShortName() . " with version " . $sourceVersion>getVersionName() );
		}
		
		// Let's grab the content and also do an update
		$oldContent = $content = $article->getContent();

		// Remove old Version from old TOC (if exists)
		preg_match_all(
			"/\[\[Category:V:" . $product->getShortName() . ':' . $targetVersion->getVersionName() . "\]\]/",
			$content,
			$matches );
		foreach ( $matches[0] as $match ) {
			$oldContent = str_replace( $match, "", $oldContent );
		}
		$article->doEdit(
			$oldContent,
			"Removed version " . $product->getShortName() . ':' . $targetVersion->getVersionName(),
			EDIT_UPDATE );

		// Now do the TOC for the new version
		if ( self::TOCExists($product, $manual, $targetVersion ) ) {
			throw new Exception(
				"TOC Already exists for " . $manual->getShortName() . " with version: " . $targetVersion->getVersionName() );
		}
		$title = PONYDOCS_DOCUMENTATION_PREFIX . $product->getShortName() . ':' . $manual->getShortName() . 'TOC'
			. $targetVersion->getVersionName();
		$newTitle = Title::newFromText( $title );
		$wgTitle = $newTitle;

		$newArticle = new Article( $newTitle );
		if ( $newArticle->exists() ) {
			throw new Exception( "TOC Already exists." );
		}

		// Remove old versions from and add new version to new TOC
		preg_match_all( "/\[\[Category:V:[^\]]*\]\]/", $content, $matches );
		// identify the last of the old tags
		$lastTag = $matches[0][count( $matches[0] ) - 1];
		// make the new tag
		$newVersionTag = "[[Category:V:" . $product->getShortName() . ':' . $targetVersion->getVersionName() . "]]";
		foreach ( $matches[0] as $match ) {
			// delete tags that aren't the last tag
			if ( $match != $lastTag ) { 
				$content = str_replace( $match, "", $content );
			// replace the last tag with the new tag
			} else {
				$content = str_replace( $match, $newVersionTag, $content );
			}
		}
		$newArticle->doEdit(
			$content,
			"Branched TOC For Version: " . $product->getShortName() . ':' . $sourceVersion->getVersionName() . " from Version: "
				. $product->getShortName() . ':' . $sourceVersion->getVersionName(),
			EDIT_NEW);
		PonyDocsExtension::ClearNavCache();
		return $title;
	}

	/**
	 * Creates new TOC page with a given manual and target version
	 *
	 * @param $manual PonyDocsManual The manual we're going to create a TOC for.
	 * @param $version PonyDocsVersion the version we're going to create a TOC
	 * FIXME $addData is not used!!!
	 * FIXME this method never gets run and it is unclear what it needs to do; @see SpecialBranchInherit.php
	 *
	 */
	static function createTOC( $product, $manual, $version, $addData ) {
		global $wgTitle;
		if ( self::TOCExists($product, $manual, $version ) ) {
			throw new Exception(
				"TOC Already exists for " . $manual->getShortName() . " with version: " . $version->getVersionName() );
		}
		$title = PONYDOCS_DOCUMENTATION_PREFIX . $product->getShortName() . ":" . $manual->getShortName() . 'TOC'
			. $version->getVersionName();

		$newTitle = Title::newFromText( $title );
		$wgTitle = $newTitle;

		$newArticle = new Article( $newTitle );
		if ( $newArticle->exists() ) {
			throw new Exception( "TOC Already exists." );
		}

		// New TOC. Create empty content.
		$newContent = "\n\n[[Category:V:" . $product->getShortName() . ":" . $version->getVersionName() . "]]";
		$newArticle->doEdit(
			$newContent,
			"Created TOC For Version: " . $product->getShortName() . ":" . $version->getVersionName(),
			EDIT_NEW);
		PonyDocsExtension::ClearNavCache();
		return $title;
	}

	/**
	 * Add a version to an existing TOC.
	 * 
	 * @param $manual PonyDocsManual The manual the TOC belongs to.
	 * @param $version PonyDocsVersion The version the TOC belongs to.
	 * @param $newVersion PonyDocsVersion The version to add to the TOC.
	 * @returns boolean
	 */
	static function addVersionToTOC( $product, $manual, $version, $newVersion ) {
		global $wgTitle;
		$title = self::TOCExists( $product, $manual, $version );
		if ( $title == false ) {
			throw new Exception(
				"TOC does not exist for " . $manual->getShortName() . " with version " . $version->getVersionName() );
		}
		$title = Title::newFromText( $title );
		$wgTitle = $title;
		$article = new Article( $title );
		if ( !$article->exists() ) {
			throw new Exception(
				"TOC does not exist for " . $manual->getShortName() . " with version " . $version->getVersionName() );
		}

		// Okay, let's search for the content.
		$content = $article->getContent();
		preg_match_all( "/\[\[Category:V:[^\]]*\]\]/", $content, $matches );
		$lastTag = $matches[0][count($matches[0]) - 1];
		$content = str_replace(
			$lastTag,
			$lastTag . "[[Category:V:" . $product->getShortName() . ':' . $newVersion->getVersionName() . "]]",
			$content );
		$article->doEdit( $content, "Added version " . $product->getShortName() . ':' . $version->getVersionName(), EDIT_UPDATE );
		PonyDocsExtension::ClearNavCache();
		return TRUE;
	}

	/**
	 * Remove entry from TOC. Will remove any instance of the entry from the TOC.
	 *
	 * @param $manual PonyDocsManual The manual the TOC belongs to.
	 * @param $version PonyDocsVersion The version the TOC belongs to.
	 * @returns boolean
	 */
	static function removeFromTOC( $product, $manual, $version, $tocTitle ) {
		global $wgTitle;
		$title = self::TOCExists( $product, $manual, $version );
		if ( $title == false ) {
			throw new Exception(
				"TOC does not exist for " . $manual->getShortName() . " with version " . $version->getVersionName() );
		}
		$title = Title::newFromText($title);
		$wgTitle = $title;
		$article = new Article($title);
		if ( !$article->exists() ) {
			throw new Exception(
				"TOC does not exist for " . $manual->getShortName() . " with version " . $version->getVersionName() );
		}

		// Okay, let's search for the content.
		$content = $article->getContent();
		$content = preg_replace( "/^ \*\s*{{\s*#topic:\s*" . $tocTitle . "\s*}}$/", "", $content );
		$article->doEdit( $content, "Removed topic " . $tocTitle, EDIT_UPDATE );
		PonyDocsExtension::ClearNavCache();
		return TRUE;
	}

	/**
	 * Do a bulk add operation. Take a collection of topics and add them to the TOC if it doesn't already exist.
	 *
	 * @param $manual PonyDocsManual The manual the TOC belongs to.
	 * @param $version PonyDocsVersion The version the TOC belongs to.
	 * @param $collection array A multidimensional array of topics. First keyed with section name, then titles.
	 * @returns boolean
	 */
	static function addCollectionToTOC( $product, $manual, $version, $collection ) {
		global $wgTitle;
		$title = self::TOCExists( $product, $manual, $version );
		if ( $title == FALSE ) {
			throw new Exception(
				"TOC does not exist for " . $manual->getShortName() . " with version " . $version->getVersionName() );
		}
		$title = Title::newFromText($title);
		$wgTitle = $title;
		$article = new Article($title);
		if (!$article->exists() ) {
			throw new Exception(
				"TOC does not exist for " . $manual->getShortName() . " with version " . $version->getVersionName() );
		}

		// Okay, let's search for the content.
		$content = $article->getContent();

		foreach ( $collection as $sectionName => $topics ) {
			// $evalSectionName is the cleaned up section name to look for.
			$evalSectionName = preg_quote( trim( str_replace( '?', "", strtolower( $sectionName ) ) ) );
			foreach ( $topics as $topic ) {
				if ( $topic == NULL ) {
					continue;
				}
				// $topic is the trimmed original version of the topic.
				$topic = trim( $topic );
				// $evalTopic is the clened up topic name to look for
				$evalTopic = preg_quote( str_replace( '?', '', strtolower($topic) ) );
				$content = explode( "\n", $content );
				$found = FALSE;
				$inSection = FALSE;
				$newContent = '';
				foreach ( $content as $line ) {
					$evalLine = trim(str_replace( '?', '', strtolower($line) ) );
					if ( preg_match( "/^" . $evalSectionName . "$/", $evalLine ) ) {
						$inSection = TRUE;
						$newContent .= $line . "\n";
						continue;
					} elseif ( preg_match( "/\*\s*{{\s*#topic:\s*" . $evalTopic . "\s*}}/", $evalLine ) ) {
						if ( $inSection ) {
							$found = TRUE;
						}
						$newContent .= $line . "\n";
						continue;
					} elseif ( preg_match( "/^\s?$/", $evalLine ) ) {
						if ( $inSection && !$found ) {
							$newContent .= "* {{#topic:" . $topic . "}}\n\n";
							$found = true;
							continue;
						}
						$inSection = FALSE;
					}
					$newContent .= $line . "\n";
				}
				if ( !$found ) {
					// Then the section didn't event exist, we should add to TOC and add the item.
					// We need to add it before the Category evalLine.
					$text = $sectionName . "\n" . "* {{#topic:" . $topic . "}}\n\n[[Category";
					$newContent = preg_replace( "/\[\[Category/", $text, $newContent );
				}
				$inSection = FALSE;
				// Reset loop data
				$content = $newContent;
			}
		}
		// Okay, do the edit
		$article->doEdit( $content, "Updated TOC in bulk branch operation.", EDIT_UPDATE );
		PonyDocsExtension::ClearNavCache();
		return true;
	}

	/**
	 * Adds entry to TOC. Will not add if entry already exists in TOC under section.
	 * Note, this isn't used anymore in the inherit/branch process, but is kept for historical purposes in case it's needed.
	 * 
	 * @param $manual PonyDocsManual The Manual the TOC belongs to.
	 * @param $version PonyDocsVersion the Version the TOC belongs to.
	 * @param $tocSection string The TOC section of the title.
	 * @param $tocTitle string The topic title to add.
	 * @returns boolean
	 */
	static function addToTOC( $product, $manual, $version, $tocSection, $tocTitle ) {
		global $wgTitle;

		// Cleanup title
		$tocTitle = preg_replace( "/[^a-zA-Z0-9\s]/", "", $tocTitle );

		$title = self::TOCExists( $product, $manual, $version );
		if ( $title == FALSE ) {
			throw new Exception(
				"TOC does not exist for " . $manual->getShortName() . " with version " . $version->getVersionName() );
		}
		$title = Title::newFromText( $title );
		$wgTitle = $title;
		$article = new Article( $title );
		if(!$article->exists()) {
			throw new Exception(
				"TOC does not exist for " . $manual->getShortName() . " with version " . $version->getVersionName() );
		}
		
		// Okay, let's search for the content.
		$content = $article->getContent();
		$content = explode( "\n", $content );
		$found = FALSE;
		$inSection = FALSE;
		$newContent = '';
		foreach ( $content as $line ) {
			if ( preg_match("/^" . $tocSection . "$/", $line ) ) {
				$inSection = TRUE;
				$newContent .= $line . "\n";
				continue;
			}
			if ( preg_match("/^\*\s*{{\s*#topic:\s*" . $tocTitle . "\s*}}$/", $line ) ) {
				if ( $inSection ) {
					$found = TRUE;
				}
				$newContent .= $line . "\n";
				continue;
			}
			if ( preg_match("/^\s?$/", $line ) ) {
				if ( $inSection && !$found ) {
					$newContent .= "* {{#topic:" . $tocTitle . "}}\n\n";
					$found = TRUE;
					continue;
				}
				$inSection = FALSE;
			}
			$newContent .= $line . "\n";
		}
		
		if ( !$found ) {
			// Then the section didn't event exist, we should add to TOC and add the item.
			// We need to add it before the Category line.
			$text = $tocSection . "\n" . "* {{#topic:" . $tocTitle . "}}\n\n[[Category";
			$newContent = preg_replace( "/\[\[Category/", $text, $newContent );
		}

		// Okay, do the edit
		$article->doEdit( $newContent, "Updated TOC with " . $tocTitle . " in Section " . $tocSection, EDIT_UPDATE );
		PonyDocsExtension::ClearNavCache();
		return TRUE;
	}

	/**
	 * Determine if there is an existing topic that may interfere with a target topic and version.
	 * If conflict(s) exist, return the topic names.
	 *
	 * @param $topicTitle string The Topic name in PONYDOCS_DOCUMENTATION_PREFIX . '.*:.*:.*:.*' format
	 * @param $targetVersion PonyDocsVersion the version to search for
	 * @return Array of conflicting topic names, otherwise false if no conflict exists.
	 */
	static function getConflicts( $product, $topicTitle, $targetVersion ) {
		$dbr = wfGetDB( DB_SLAVE );
		if ( !preg_match( '/' . PONYDOCS_DOCUMENTATION_PREFIX . '(.*):(.*):(.*):(.*)/', $topicTitle, $match ) ) {
			throw new Exception( "Invalid Title to get conflicts for" );
		}
		$productName = $match[1];
		$manual = $match[2];
		$title = $match[3];
		$query = "SELECT cl_sortkey FROM categorylinks WHERE cl_to = 'V:" . $dbr->strencode($product->getShortName() . ':'
			. $targetVersion->getVersionName()) . "' AND LOWER(cast(cl_sortkey AS CHAR)) LIKE '"
			. $dbr->strencode( strtolower( PONYDOCS_DOCUMENTATION_PREFIX . $productName . ":" . $manual . ":" . $title ) )
			. ":%'";
		$res = $dbr->query( $query, __METHOD__ );

		if ( $res->numRows() ) {
			/**
			 * Technically we should only EVER get one result back. But who knows with past doc wiki consistency issues.
			 */
			$conflicts = array();

			// Then let's return the topics that conflict.
			while ( $row = $dbr->fetchObject( $res ) ) {
				$conflicts[] = $row->cl_sortkey;
			}
			return $conflicts;
		}

		// One last ditch effort.
		// Determine if any page exists that doesn't have a category link association
		// or when its base version is not in its categories.
		$destinationTitle =
			PONYDOCS_DOCUMENTATION_PREFIX . $productName . ':' . $manual . ':' . $title . ':' . $targetVersion->getVersionName();
		$destinationArticle = PonyDocsArticleFactory::getArticleByTitle( $destinationTitle );
		if ( $destinationArticle->exists() ) {
			return array( $destinationArticle->metadata['title'] );
		}

		return FALSE;
	}
}