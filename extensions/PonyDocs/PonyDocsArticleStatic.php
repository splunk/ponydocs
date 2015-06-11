<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );

require_once('PonyDocsArticle.php');

/**
 * Provides implementation for Static article type
 */

class PonyDocsArticleStatic extends PonyDocsArticle
{

	public $product;
	public $version;
	public $uri;
	protected $exists = 0;

	/**
	 * Sets metadata for article: product, version, and rest of the URI
	 * @param string $product Ponydocs product short name
	 * @param string $version Ponydocs version name
	 * @param string $uri static content URI requested (string after product/version)
	 */
	public function setMetadata($product, $version, $uri) {
		$this->product = $product;
		$this->version = $version;
		$this->uri = $uri;
	}

	/**
	 * Simply return the already loaded content.
	 *
	 * @param integer $oldid
	 * @return string
	 */
	public function getContent( $oldid = 0 )
	{
		if (!$this->mContentLoaded) {
			$this->loadContent();
		}
		return $this->mContent;
	}

	/**
	 * Overrides the fetchContent function of the core Article object
	 *
	 * Checks whether the requested static content exists and loads it;
	 * also sets other relevant article properties as needed by the core.
	 * @param integer $oldid revision to load; irrelevant for static content
	 * @return string content
	 */
	public function fetchContent($oldid = 0) {
		$path = PONYDOCS_STATIC_DIR . DIRECTORY_SEPARATOR . $this->product . DIRECTORY_SEPARATOR .
				$this->version . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->uri);
		if (file_exists($path) && is_readable($path)) {
			$this->mContent = file_get_contents($path);
			$this->mContentLoaded = true;
			$this->mDataLoaded = true;
			$this->exists = 1;
		}
		return $this->mContent;
	}

	/**
	 * For Mediawiki to recognize the article has loaded it needs a non zero ID
	 * @return integer 1 when loaded, 0 otherwise as default
	 */
	public function getID() {
		return $this->exists;
	}

}

?>