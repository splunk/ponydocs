<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );

require_once( "$IP/includes/Title.php" );
require_once('PonyDocsArticleTopic.php');
require_once('PonyDocsArticleStatic.php');
require_once('PonyDocsArticleTOC.php');
require_once('PonyDocsDBPage.php');

/**
 * Provides factory implementation for different types of Articles
 */

class PonyDocsArticleFactory
{

	const ARTICLE_TYPE_TOPIC = 'Topic';
	const ARTICLE_TYPE_TOC = 'TOC';
	const ARTICLE_TYPE_OTHER = '';

	/**
	 * Parse title and return article object
	 * @param string $title
	 * @param int $rev revision ID of the article, optional, default 0 for current
	 * @return PonyDocsArticle object
	 * @throw InvalidArgumentException when $title is not a PonyDocs namespace article title
	 */
	static public function getArticleByTitle($title, $rev = 0) {
		// get article type; if not our namespace, throw exception
		$articleMeta = self::getArticleMetadataFromTitle($title);
		if ($articleMeta['type'] === self::ARTICLE_TYPE_OTHER) {
			throw new InvalidArgumentException("Title ($title) is not a PonyDocs documentation namespace article title.");
		}

		$titleToLoad = $title;

		// if case insensitive config, get possible case insensitive matches
		if (!PONYDOCS_CASE_SENSITIVE_TITLES) {
			$pageMatches = PonyDocsDBPage::getCaseInsensitiveMatch($articleMeta['page_title']);
			// take the first match (even if more found)
			if (count($pageMatches) > 0) {
				if (count($pageMatches) > 1) {
					if (PONYDOCS_DEBUG) {error_log('DEBUG [' . __METHOD__ . ':' . __LINE__ . '] ' . $articleMeta['page_title'] . ' matched more than 1 page record.');}
				}
				$titleToLoad = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $pageMatches[0]['page_title'];
			} else {
				// no matches found
				if (PONYDOCS_DEBUG) {error_log('DEBUG [' . __METHOD__ . ':' . __LINE__ . '] no page record matches found for ' . $articleMeta['page_title']);}
			}
		}

		// create object and return
		if ($articleMeta['type'] === self::ARTICLE_TYPE_TOPIC) {
			$article = new PonyDocsArticleTopic(Title::newFromText($titleToLoad), $rev);
		} elseif ($articleMeta['type'] === self::ARTICLE_TYPE_TOC) {
			$article = new PonyDocsArticleTOC(Title::newFromText($titleToLoad), $rev);
		}
		$article->setMetadata($articleMeta);
		return $article;
	}

	/**
	 * Parse title and return static article object
	 * @param string $title
	 * @param string $baseUrl base URI where Mediawiki is installed
	 * @return PonyDocsArticleStatic object
	 */
	static public function getStaticArticleByTitle($title, $baseUrl) {
		$meta = self::getArticleMetadataFromURL($title, $baseUrl);
		$titleToLoad = $meta['namespace'] . ':' . $meta['product'] . ':' . $meta['version'] . ':' . $meta['uri'];
		$article = new PonyDocsArticleStatic(Title::newFromText($titleToLoad), 0);
		$article->setMetadata($meta['product'], $meta['version'], $meta['uri']);
		return $article;
	}

	/**
	 * Parse title and return article metadata
	 * @param string $title
	 * @return array article metadata (type, namespace, page_title, product, manual, topic, base_version)
	 */
	static public function getArticleMetadataFromTitle($title) {
		$meta = array();
		if (preg_match( '/^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(([' . PONYDOCS_PRODUCT_LEGALCHARS . ']*):([' .
			PONYDOCS_PRODUCTMANUAL_LEGALCHARS . ']*):([' . Title::legalChars( ) . ']*):([' .
			PONYDOCS_PRODUCTVERSION_LEGALCHARS . ']*))/i', $title, $match)) {
			// matched topic regex
			$meta['type'] = self::ARTICLE_TYPE_TOPIC;
			$meta['namespace'] = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME;
			$meta['title'] = $title;
			$meta['page_title'] = $match[1];
			$meta['product'] = $match[2];
			$meta['manual'] = $match[3];
			$meta['topic'] = $match[4];
			$meta['base_version'] = $match[5];
		} elseif (preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(([' . PONYDOCS_PRODUCT_LEGALCHARS .
			']*):([' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS . ']*)TOC([' . PONYDOCS_PRODUCTVERSION_LEGALCHARS . ']*))/i',
			$title, $match )) {
			// matched TOC regex
			$meta['type'] = self::ARTICLE_TYPE_TOC;
			$meta['namespace'] = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME;
			$meta['title'] = $title;
			$meta['page_title'] = $match[1];
			$meta['product'] = $match[2];
			$meta['manual'] = $match[3];
			$meta['base_version'] = $match[4];
		} else {
			// no match
			$meta['type'] = self::ARTICLE_TYPE_OTHER;
		}
		return $meta;
	}

	/**
	 * Parse URL and return static article metadata
	 * @param string $url
	 * @return array article metadata (namespace, product, version, uri)
	 */
	static public function getArticleMetadataFromURL($url, $baseUrl) {
		$meta = array();
		if(preg_match('/^' . str_replace("/", "\/", $baseUrl) . '\/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '\/([' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)(\/([' . PONYDOCS_PRODUCTVERSION_LEGALCHARS . ']+))(\/.*)?$/i', $url, $match)) {
			$meta['namespace'] = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME;
			$meta['product'] = $match[1];
			$meta['version'] = isset($match[3]) ? $match[3] : '';
			$meta['uri'] = isset($match[4]) ? $match[4] : '';
		}
		return $meta;
	}

}

?>