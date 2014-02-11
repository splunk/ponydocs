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
		error_log("TOC title: $title");
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
		$newCategory = '[[Category:V:' . $product->getShortName() . ':' . $targetVersion->getVersionName() . ']]';
		error_log("$oldCategory => $newCategory");
		
		// Okay, let's search for the content.
		$content = $article->getContent();
		$content = preg_replace( '/' . preg_quote( $oldCategory ) . '/', $newCategory, $content, -1, $count );
		$article->doEdit( $content, "Renamed version category $oldCategory to $newCategory in $count locations", EDIT_UPDATE );
		PonyDocsExtension::ClearNavCache();
		return true;
	}
	
	/**
	 * Replace a version on an existing Topic
	 *
	 * @param $topicTitle string The internal mediawiki title of the article.
	 * @param $sourceVersion PonyDocsVersion The source Version
	 * @param $targetVersion PonyDocsVersion The target Version
	 * @param $deleteExisting boolean Should we purge any existing conflicts?
	 * @returns boolean
	 */
	static function changeVersionOnTopic(
		$topicTitle, $sourceVersion, $targetVersion, $deleteExisting = false ) {

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

		// Get conflicts.
		$conflicts = PonyDocsBranchInheritEngine::getConflicts( $product, $topicTitle, $targetVersion );
		if ( !empty( $conflicts )) {
			if ( !$deleteExisting ) {
				// TODO: We should continue here, or check for conflicts in advance,
				// otherwise we'll end up in an inconsistent state
				throw new Exception( 'When calling rename version, there were conflicts and deleteExisting was false.' );
			}
			// We want to purge each conflicting title completely.
			foreach ( $conflicts as $conflict ) {
				$article = new Article( Title::newFromText( $conflict ));
				if ( !$article->exists() ) {
					// No big deal.  A conflict no longer exists?  Continue.
					continue;
				}
				if ( $conflict == $topicTitle ) {
					// Conflict was same as source material. Do nothing with it.
					continue;
				} else {
					$article->doDelete( "Requested purge of conficting article when inheriting topic $topicTitle"
						. ' with version: ' . $sourceVersion->getVersionName(), false);
				}
			}
		}
		$title = Title::newFromText( $topicTitle );
		$wgTitle = $title;
		$article = new Article( $title );
		if ( !$article->exists() ) {
			// No such title exists in the system
			throw new Exception( "Invalid Title to Rename Version: $topicTitle");
		}
		
		// Okay, source article exists.
		// Replace the old category with the new one
		$oldCategory = '[[Category:V:' . $product->getShortName() . ':' . $sourceVersion->getVersionName() . ']]';
		$newCategory = '[[Category:V:' . $product->getShortName() . ':' . $targetVersion->getVersionName() . ']]';
		error_log("$oldCategory => $newCategory");
		
		$content = $article->getContent();
		$content = preg_replace( '/' . preg_quote( $oldCategory ) . '/', $newCategory, $content, -1, $count );		$article->doEdit( $content, "Renamed version category $oldCategory to $newCategory in $count locations", EDIT_UPDATE );
		return $title;
	}
}