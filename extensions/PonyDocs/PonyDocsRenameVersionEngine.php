<?php

require_once( 'PonyDocsArticleFactory.php' );

/**
 * Engine to perform version rename functions for PonyDocs Documentation System.
 * Never instantiated, just a container for static methods.
 */
class PonyDocsRenameVersionEngine {

	private function __construct() {
		// It's a static class.
	}

	/**
	 * Replace a version on an existing TOC.
	 * 
	 * @param $product PonyDocsProduct the product the TOC belongs to
	 * @param $manual PonyDocsManual The manual the TOC belongs to
	 * @param $sourceVersion PonyDocsVersion The version being removed
	 * @param $targetVersion PonyDocsVersion The version being added
	 * @returns boolean
	 */
	static function changeVersionOnTOC( $product, $manual, $sourceVersion, $targetVersion ) {
		global $wgTitle;
		$title = PonyDocsBranchInheritEngine::TOCExists( $product, $manual, $sourceVersion );
		if ( $title == false ) {
			throw new Exception( 'TOC does not exist for ' . $manual->getShortName()
				. ' with version ' . $sourceVersion->getVersionName() );
		}
		$title = Title::newFromText( $title );
		$wgTitle = $title;
		$article = new Article( $title );
		if ( !$article->exists() ) {
			throw new Exception( 'TOC does not exist for ' . $manual->getShortName()
				. ' with version ' . $sourceVersion->getVersionName() );
		}
		
		$oldCategory = '[[Category:V:' . $product->getShortName() . ':' . $sourceVersion->getVersionName() . ']]';
		$oldCategoryRegex = '/' . preg_quote( $oldCategory ) . '/';
		$newCategory = '[[Category:V:' . $product->getShortName() . ':' . $targetVersion->getVersionName() . ']]';
		$newCategoryRegex = '/' . preg_quote( $newCategory ) . '/';
		
		// Okay, let's search for the content.
		$content = $article->getContent();
		// If the TOC doesn't contain the source version, then something is odd.
		// TODO: Should we add the new version here anyway?
		if ( !preg_match( $oldCategoryRegex, $content )) {
			throw new Exception(
				'TOC for ' . $manual->getShortName() . ' does not contain source version ' . $sourceVersion->getVersionName());
		// If the TOC already has the new version, then just delete the old version
		} elseif ( preg_match( $newCategoryRegex, $content)) {
			$content = str_replace( $oldCategory, '', $content );
			$message = "Removed version category $oldCategory via RenameVersion";
		// Otherwise replace old with new
		} else {
			$content = preg_replace( $oldCategoryRegex, $newCategory, $content, -1, $count );
			$message = "Renamed version category $oldCategory to $newCategory in $count locations via RenameVersion";
		}
		$article->doEdit( $content, $message, EDIT_UPDATE );
		PonyDocsExtension::ClearNavCache();
		return true;
	}
	
	/**
	 * Replace a version on an existing Topic
	 *
	 * @param $topicTitle string The internal mediawiki title of the article.
	 * @param $sourceVersion PonyDocsVersion The source Version
	 * @param $targetVersion PonyDocsVersion The target Version
	 * @returns boolean
	 */
	static function changeVersionOnTopic(	$topicTitle, $sourceVersion, $targetVersion ) {

		// TODO: modify to actually work
				
		global $wgTitle;
		// Clear any hooks so no weirdness gets called after we save the change
		$wgHooks['ArticleSave'] = array();

		if ( !preg_match( '/^' . PONYDOCS_DOCUMENTATION_PREFIX . '([^:]*):([^:]*):(.*):([^:]*)$/', $topicTitle, $match )) {
			throw new Exception( "Invalid Title to Rename Version: $topicTitle" );
		}

		$productName = $match[1];
		$title = $match[3];

		// Get PonyDocsProduct
		$product = PonyDocsProduct::GetProductByShortName( $productName );

		$title = Title::newFromText( $topicTitle );
		$wgTitle = $title;
		$article = new Article( $title );
		if ( !$article->exists() ) {
			// No such title exists in the system
			throw new Exception( "Invalid Title to Rename Version: $topicTitle");
		}
		$content = $article->getContent();

		$oldCategory = '[[Category:V:' . $product->getShortName() . ':' . $sourceVersion->getVersionName() . ']]';
		$oldCategoryRegex = '/' . preg_quote( $oldCategory ) . '/';
		$newCategory = '[[Category:V:' . $product->getShortName() . ':' . $targetVersion->getVersionName() . ']]';
		$newCategoryRegex = '/' . preg_quote( $newCategory ) . '/';

		$message = '';
		
		// Get conflicts.
		$conflicts = PonyDocsBranchInheritEngine::getConflicts( $product, $topicTitle, $targetVersion );
		if ( !empty( $conflicts )) {
			// If there's already a topic with the target version,
			// then we just want to remove the source version from the source topic
			foreach ( $conflicts as $conflict ) {
				$conflictingArticle = new Article( Title::newFromText( $conflict ));
				// No big deal.  A conflict no longer exists?  Continue.
				if ( !$conflictingArticle->exists() ) {
					continue;
				}
				// Conflict was same as source material. Do nothing with it.
				if ( $conflict == $topicTitle ) {
					continue;
				// Remove source version from source article
				} else {
					$content = str_replace( $oldCategory, '', $content );
					$message = "Removed version category $oldCategory via RenameVersion";
				}
			}
		}

		if ( empty( $message )) {
			// If the Topic doesn't contain the source version, it may have been branched.
			if ( !preg_match( $oldCategoryRegex, $content )) {
				$lastColon = strrpos($topicTitle, ':');
				$baseTopic = substr_replace($topicTitle, '', $lastColon);
				$topicTitle = PonyDocsTopic::GetTopicNameFromBaseAndVersion( $baseTopic, $productName );
				// We found the title in the source version, let's recurse just this once to handle it.
				if ( $topicTitle ) {
					$title = self::changeVersionOnTopic( $topicTitle, $sourceVersion, $targetVersion );
				// We can't find a topic with the source version, so something is odd. Let's complain
				} else {
					throw new Exception(
						"Topic $topicTitle does not contain source version " . $sourceVersion->getVersionName());
				}
			// If the Topic already has the new version, then just delete the old version
			} elseif ( preg_match( $newCategoryRegex, $content)) {
				$content = str_replace( $oldCategory, '', $content );
				$message = "Removed version category $oldCategory via RenameVersion";
			// Otherwise replace old with new
			} else {
				$content = preg_replace( $oldCategoryRegex, $newCategory, $content, -1, $count );
				$message = "Renamed version category $oldCategory to $newCategory in $count locations via RenameVersion";
			}
		}
		
		// Finally we can edit the topic
		if (! empty( $message )) {
			$article->doEdit( $content, $message, EDIT_UPDATE );
		}
		
		return $title;
	}
}